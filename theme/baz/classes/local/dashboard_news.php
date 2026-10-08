<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cached dashboard news/podcast feed HTML for theme_baz.
 *
 * The student dashboard used to render the rss_client block inline, which made
 * every /my/ hit fetch up to ten third-party feeds over the network *during page
 * render*. SimplePie's own file cache lives in $CFG->cachedir, which on this
 * deployment is a per-pod emptyDir, so it was cold on every pod start and the
 * block_rss_client refreshfeeds cron task warmed only the cron pod's copy. The
 * result: /my/ blocked for seconds on techcrunch/forbes/etc. while holding the
 * user's session lock, so every other request from that user queued behind it and
 * then died on "Cannot obtain session lock".
 *
 * This helper moves the fetch off the request path entirely. refresh() does the
 * blocking work in a scheduled task and stores the rendered HTML in a database table;
 * get() only ever reads that table and never touches the network.
 *
 * The store is deliberately the database and not a MUC application cache. On this
 * deployment MUC runs on core defaults, so MODE_APPLICATION resolves to
 * cachestore_file inside $CFG->cachedir — the same per-pod emptyDir that caused the
 * original bug, which would leave the cron pod unable to warm any web pod. A two-row
 * indexed table is shared by every pod, survives restarts, and costs one trivial
 * query per dashboard render.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads/refreshes the cached rss_client HTML used by the student dashboard.
 */
class dashboard_news {

    /** @var string Cache key holding the "Business" feed block HTML. */
    const KEY_BUSINESS = 'business';

    /** @var string Cache key holding the "Podcast" feed block HTML. */
    const KEY_PODCAST = 'podcast';

    /**
     * Which dashboard card maps to which rss_client block title substring.
     *
     * The dashboard picks a block instance by a case-insensitive match on the
     * instance's configured title, exactly as the old inline lookup did.
     *
     * @var array cache key => title substring
     */
    const FEEDS = [
        self::KEY_BUSINESS => 'Business',
        self::KEY_PODCAST  => 'Podcast',
    ];

    /** @var string Table holding the pre-rendered HTML. */
    const TABLE = 'theme_baz_dashboard_news';

    /**
     * Read stored feed HTML. Never fetches; a miss simply yields an empty card.
     *
     * @param string $key one of the KEY_* constants
     * @return string block HTML, or '' when nothing is stored yet
     */
    public static function get(string $key): string {
        global $DB;

        try {
            $html = $DB->get_field(self::TABLE, 'content', ['cachekey' => $key], IGNORE_MISSING);
            return is_string($html) ? $html : '';
        } catch (\Throwable $e) {
            // Table missing (upgrade not yet run) or DB hiccup — show an empty card
            // rather than breaking the whole dashboard.
            debugging('theme_baz dashboard_news get: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Re-render every configured feed block and store its HTML in the shared cache.
     *
     * Intended to be called only from the scheduled task, where blocking on slow
     * third-party feeds costs nobody a pageview. Each feed is isolated so one dead
     * source cannot lose the others, and a failure leaves the previous cached value
     * in place rather than blanking the card.
     *
     * @return array cache key => number of bytes stored (0 when the feed yielded nothing)
     */
    public static function refresh(): array {
        global $DB;

        $result = [];

        foreach (self::FEEDS as $key => $titlecontains) {
            try {
                $html = self::render_rss_block($titlecontains);
            } catch (\Throwable $e) {
                // Keep whatever is already cached; an empty card is better than a stale error.
                debugging('theme_baz dashboard_news refresh ' . $key . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                $result[$key] = -1;
                continue;
            }

            if ($html === '') {
                // Nothing renderable (all feeds failing / skipuntil set). Leave the
                // last good value alone so a transient outage does not empty the card.
                $result[$key] = 0;
                continue;
            }

            $existing = $DB->get_record(self::TABLE, ['cachekey' => $key], 'id', IGNORE_MISSING);
            if ($existing) {
                $DB->update_record(self::TABLE, (object) [
                    'id' => $existing->id,
                    'content' => $html,
                    'timemodified' => time(),
                ]);
            } else {
                $DB->insert_record(self::TABLE, (object) [
                    'cachekey' => $key,
                    'content' => $html,
                    'timemodified' => time(),
                ]);
            }

            $result[$key] = strlen($html);
        }

        return $result;
    }

    /**
     * Render the first my-index rss_client block instance whose title matches.
     *
     * This is the old student_dashboard::render_block() lookup, unchanged, so the
     * cached HTML is byte-for-byte what the dashboard used to render inline and the
     * existing parser keeps working.
     *
     * @param string $titlecontains case-insensitive substring of the instance title
     * @return string block HTML, or '' if no instance matched or produced content
     */
    protected static function render_rss_block(string $titlecontains): string {
        global $DB, $PAGE, $CFG;

        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

        $instances = $DB->get_records_select(
            'block_instances',
            'blockname = :bn AND ' . $DB->sql_like('pagetypepattern', ':ptp'),
            ['bn' => 'rss_client', 'ptp' => 'my-index%']
        );

        foreach ($instances as $instance) {
            $block = block_instance('rss_client', $instance, $PAGE);
            if (!$block) {
                continue;
            }

            $title = isset($block->config->title) ? (string) $block->config->title : '';
            if (stripos($title, $titlecontains) === false) {
                continue;
            }

            $content = $block->get_content();
            if ($content && trim((string) $content->text) !== '') {
                return (string) $content->text;
            }
        }

        return '';
    }
}
