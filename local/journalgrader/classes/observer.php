<?php

namespace local_journalgrader;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Queue submission for auto grading.
     */
    public static function queue_submission(
        $event
    ): void {

        global $DB;

        /*
         * Resolve user ID.
         */

        $userid = 0;

        /*
         * Prefer related user.
         */

        if (!empty($event->relateduserid)) {

            $userid =
                (int) $event->relateduserid;
        }

        /*
         * Fallback to event user.
         */

        if (
            $userid <= 0
            &&
            !empty($event->userid)
        ) {

            $userid =
                (int) $event->userid;
        }

        if ($userid <= 0) {

            mtrace(
                'Journal grader: Invalid user ID'
            );

            return;
        }

        /*
         * Validate course module context.
         */

        $context =
            $event->get_context();

        if (
            !$context
            ||
            $context->contextlevel
                != CONTEXT_MODULE
        ) {

            mtrace(
                'Journal grader: Invalid context'
            );

            return;
        }

        $cmid =
            (int) $context->instanceid;

        if ($cmid <= 0) {

            mtrace(
                'Journal grader: Invalid CMID'
            );

            return;
        }

        /*
         * Ensure answer key exists.
         */

        $answerkey =
            grader::load_answer_key(
                $cmid
            );

        if (!$answerkey) {

            mtrace(
                'Journal grader: Answer key missing for CMID '
                . $cmid
            );

            return;
        }

        /*
         * Normalize grading type.
         */

        $type =
            strtolower(
                trim(
                    str_replace(
                        ' ',
                        '_',
                        $answerkey['type']
                        ?? 'journal'
                    )
                )
            );

        $supportedtypes = [

            'journal',

            'ledger',

            'profit_and_loss',

            'balance_sheet'
        ];

        if (
            !in_array(
                $type,
                $supportedtypes,
                true
            )
        ) {

            mtrace(
                'Journal grader: Unsupported type => '
                . $type
            );

            return;
        }

        /*
         * Prevent duplicate pending queue entries.
         */

        $existing =
            $DB->get_record(

                'local_journalgrader_queue',

                [

                    'cmid' => $cmid,

                    'userid' => $userid,

                    'processed' => 0
                ]
            );

        if ($existing) {

            mtrace(
                'Journal grader: Queue already exists for user '
                . $userid
            );

            return;
        }

        /*
         * Insert grading queue record.
         */

        $record = (object) [

            'cmid' => $cmid,

            'userid' => $userid,

            'timecreated' => time(),

            'processed' => 0
        ];

        try {

            mtrace(
                'Journal grader queue insert => '
                . 'CMID: '
                . $cmid
                . ' USERID: '
                . $userid
                . ' TYPE: '
                . $type
            );

            $DB->insert_record(
                'local_journalgrader_queue',
                $record
            );

        } catch (\Throwable $e) {

            mtrace(
                'Journal grader queue insert failed: '
                . $e->getMessage()
            );
        }
    }
}