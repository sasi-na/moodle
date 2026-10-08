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
 * Shared "Ask JO" chat builder for the bespoke student views.
 *
 * @package   theme_baz
 * @copyright 2026 onlinejain.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the Ask JO (block_openai_chat) chat log + input control bar and queues
 * the chat JS, so the panel can be embedded on any bespoke student page rather
 * than only the dashboard. Mirrors block_openai_chat::get_content() against the
 * existing my-index instance (avoids block_instance(), which would pull sibling
 * content-region blocks onto the page and queue their JS against absent DOM).
 */
class askjo {

    /**
     * @return array|null ['blockid','log','control'] or null when unavailable.
     */
    public static function build(): ?array {
        global $DB, $PAGE, $OUTPUT;
        try {
            $instance = $DB->get_record_select(
                'block_instances',
                'blockname = :bn AND ' . $DB->sql_like('pagetypepattern', ':ptp'),
                ['bn' => 'openai_chat', 'ptp' => 'my-index%'],
                '*',
                IGNORE_MULTIPLE
            );
            if (!$instance || empty(get_config('block_openai_chat', 'apikey'))) {
                return null;
            }

            $assistantname = get_config('block_openai_chat', 'assistantname')
                ?: get_string('defaultassistantname', 'block_openai_chat');
            $username = get_config('block_openai_chat', 'username')
                ?: get_string('defaultusername', 'block_openai_chat');

            $PAGE->requires->js_call_amd('block_openai_chat/lib', 'init', [[
                'blockId' => (int) $instance->id,
                'api_type' => get_config('block_openai_chat', 'type') ?: 'chat',
                'persistConvo' => get_config('block_openai_chat', 'persistconvo'),
            ]]);

            $log = '<script>var assistantName = ' . json_encode($assistantname) . '; var userName = '
                . json_encode($username) . ';</script>'
                . '<style>.openai_message.user:before{content:' . json_encode($username) . '}'
                . '.openai_message.bot:before{content:' . json_encode($assistantname) . '}</style>'
                . '<div id="openai_chat_log" role="log"></div>';

            $control = $OUTPUT->render_from_template('block_openai_chat/control_bar', [
                'logging_enabled' => get_config('block_openai_chat', 'logging'),
                'is_edit_mode'    => false,
                'pix_popout'      => '/blocks/openai_chat/pix/arrow-up-right-from-square.svg',
                'pix_arrow_right' => '/blocks/openai_chat/pix/arrow-right.svg',
                'pix_refresh'     => '/blocks/openai_chat/pix/refresh.svg',
            ]);

            return ['blockid' => (int) $instance->id, 'log' => $log, 'control' => $control];
        } catch (\Throwable $e) {
            debugging('theme_baz askjo: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }
}
