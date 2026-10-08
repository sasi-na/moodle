<?php

namespace block_liverecordingstats;

defined('MOODLE_INTERNAL') || die();

/**
 * Navigation helper.
 */
class navigation {

    /**
     * Extend navigation.
     *
     * @param navigation_node $navigation
     * @param stdClass $course
     * @param context_course $context
     */
    public static function extend(
        \navigation_node $navigation,
        \stdClass $course,
        \context_course $context
    ) {

        if (
            !has_capability(
                'block/liverecordingstats:viewstats',
                $context
            )
        ) {
            return;
        }

        $url = new \moodle_url(
            '/blocks/liverecordingstats/index.php',
            [
                'courseid' => $course->id
            ]
        );

        $navigation->add(

            'Recorded Session Stats',

            $url,

            \navigation_node::TYPE_SETTING,

            null,

            'recordedsessionstats',

            new \pix_icon(
                'i/report',
                ''
            )
        );
    }
}