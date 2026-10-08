<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Live Recording Stats block.
 */
class block_liverecordingstats extends block_base {

    /**
     * Init.
     */
    public function init() {

        $this->title = get_string(
            'pluginname',
            'block_liverecordingstats'
        );
    }

    /**
     * Applicable formats.
     *
     * @return array
     */
    public function applicable_formats() {

        return [
            'all' => true
        ];
    }

    /**
     * Allow multiple.
     *
     * @return bool
     */
    public function instance_allow_multiple() {

        return false;
    }

    /**
     * Has config.
     *
     * @return bool
     */
    public function has_config() {

        return false;
    }

    /**
     * Hide header.
     *
     * @return bool
     */
    public function hide_header() {

        return false;
    }

    /**
     * Get content.
     *
     * @return stdClass
     */
    public function get_content() {

        global $COURSE, $USER, $PAGE;

        /*
        |--------------------------------------------------------------------------
        | Cache
        |--------------------------------------------------------------------------
        */

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        $this->content->text = '';
        $this->content->footer = '';

        /*
        |--------------------------------------------------------------------------
        | Guest protection
        |--------------------------------------------------------------------------
        */

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        /*
        |--------------------------------------------------------------------------
        | Ignore front page
        |--------------------------------------------------------------------------
        */

        if (
            empty($COURSE->id) ||
            (int)$COURSE->id === SITEID
        ) {
            return $this->content;
        }

        /*
        |--------------------------------------------------------------------------
        | Context
        |--------------------------------------------------------------------------
        */

        $context = context_course::instance(
            $COURSE->id
        );

        /*
        |--------------------------------------------------------------------------
        | User checks
        |--------------------------------------------------------------------------
        */

        $isenrolled = is_enrolled(
            $context,
            $USER->id,
            '',
            true
        );

        $canviewstats = has_capability(
            'block/liverecordingstats:viewstats',
            $context
        );

        /*
        |--------------------------------------------------------------------------
        | Completely hide only if neither student nor faculty
        |--------------------------------------------------------------------------
        */

        if (
            !$isenrolled &&
            !$canviewstats
        ) {
            return $this->content;
        }

        /*
        |--------------------------------------------------------------------------
        | CSS
        |--------------------------------------------------------------------------
        */

        $PAGE->requires->css(
            '/blocks/liverecordingstats/styles.css'
        );

        /*
        |--------------------------------------------------------------------------
        | HTML start
        |--------------------------------------------------------------------------
        */

        $html = '';

        $html .= '<div class="liverecordingstats-wrapper"><div class="liverecordingstats-cards">';

        /*
        |--------------------------------------------------------------------------
        | Student stats
        |--------------------------------------------------------------------------
        */

        if ($isenrolled) {

            try {

                $stats =
                    \block_liverecordingstats\helper::get_stats(
                        (int)$COURSE->id,
                        (int)$USER->id
                    );

            } catch (Exception $e) {

                $stats = [
                    'total' => 0,
                    'completed' => 0,
                    'summaries' => 0
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Total recordings
            |--------------------------------------------------------------------------
            */

            $html .= '
                <div class="liverecordingstats-card">
                    <div class="liverecordingstats-icon"><i class="fa fa-film" aria-hidden="true"></i></div>
                    <div class="liverecordingstats-number">' . (int)($stats['total'] ?? 0) . '</div>
                    <div class="liverecordingstats-label">Total</div>
                </div>';

            /*
            |--------------------------------------------------------------------------
            | Completed recordings
            |--------------------------------------------------------------------------
            */

            $html .= '
                <div class="liverecordingstats-card">
                    <div class="liverecordingstats-icon"><i class="fa fa-check-circle" aria-hidden="true"></i></div>
                    <div class="liverecordingstats-number">' . (int)($stats['completed'] ?? 0) . '</div>
                    <div class="liverecordingstats-label">Watched</div>
                </div>';

            /*
            |--------------------------------------------------------------------------
            | Summaries
            |--------------------------------------------------------------------------
            */

            $html .= '
                <div class="liverecordingstats-card">
                    <div class="liverecordingstats-icon"><i class="fa fa-pencil" aria-hidden="true"></i></div>
                    <div class="liverecordingstats-number">' . (int)($stats['summaries'] ?? 0) . '</div>
                    <div class="liverecordingstats-label">Summaries</div>
                </div>';

            // Close the cards row and add completion progress bar.
            $total    = (int)($stats['total'] ?? 0);
            $completed = (int)($stats['completed'] ?? 0);
            $recpct   = $total > 0 ? round(($completed / $total) * 100) : 0;

            $html .= '</div>'; // .liverecordingstats-cards

            $html .= '
                <div class="lrs-progress-wrap">
                    <div class="lrs-progress-header">
                        <span>Recordings watched</span>
                        <span class="lrs-pct">' . $recpct . '%</span>
                    </div>
                    <div class="lrs-bar">
                        <div class="lrs-bar-fill" style="width:' . $recpct . '%"></div>
                    </div>
                </div>';
        }

        /*
        |--------------------------------------------------------------------------
        | Faculty stats button
        |--------------------------------------------------------------------------
        */

        if ($canviewstats) {

            $statsurl = new moodle_url(
                '/blocks/liverecordingstats/index.php',
                [
                    'courseid' => $COURSE->id
                ]
            );

            $html .= '

                <div
                    style="
                        margin-top: 16px;
                        width: 100%;
                    "
                >

                    <a
                        href="' . $statsurl->out(false) . '"
                        class="btn btn-primary"

                        style="
                            width: 100%;
                            text-align: center;
                            padding: 12px;
                            font-weight: 600;
                            border-radius: 8px;
                            display: inline-block;
                            box-sizing: border-box;
                        "
                    >

                        Recorded Session Stats

                    </a>

                </div>
            ';
        }

        /*
        |--------------------------------------------------------------------------
        | HTML end
        |--------------------------------------------------------------------------
        */

        $html .= '</div>'; // .liverecordingstats-wrapper

        $this->content->text = $html;

        return $this->content;
    }
}