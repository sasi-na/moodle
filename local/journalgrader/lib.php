<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Add answer key upload field
 * to assignment settings.
 */
function local_journalgrader_coursemodule_standard_elements(

    $formwrapper,

    $mform

) {

    $current =
        $formwrapper->get_current();

    /*
     * Only for assignments.
     */

    if (

        empty($current->modulename)

        ||

        $current->modulename !== 'assign'

    ) {
        return;
    }

    /*
     * Prepare existing draft area.
     */

    $draftitemid =
        file_get_submitted_draft_itemid(
            'journalanswerkey'
        );

    if (
        !empty($current->coursemodule)
    ) {

        $context =
            context_module::instance(
                $current->coursemodule
            );

        file_prepare_draft_area(

            $draftitemid,

            $context->id,

            'local_journalgrader',

            'answerkey',

            0,

            [

                'subdirs' => 0,

                'maxfiles' => 1,

                'accepted_types' => [
                    '.json'
                ]
            ]
        );
    }

    /*
     * Section header.
     */

    $mform->addElement(

        'header',

        'local_journalgrader_header',

        get_string(
            'pluginname',
            'local_journalgrader'
        )
    );

    /*
     * File manager.
     */

    $mform->addElement(

        'filemanager',

        'journalanswerkey',

        get_string(

            'journalanswerkey',

            'local_journalgrader'
        ),

        null,

        [

            'subdirs' => 0,

            'maxfiles' => 1,

            'maxbytes' => 0,

            'accepted_types' => [
                '.json'
            ]
        ]
    );

    /*
     * Help button.
     */

    $mform->addHelpButton(

        'journalanswerkey',

        'journalanswerkey',

        'local_journalgrader'
    );

    /*
     * Default value.
     */

    $mform->setDefault(

        'journalanswerkey',

        $draftitemid
    );
}

/**
 * Save uploaded answer key.
 */
function local_journalgrader_coursemodule_edit_post_actions(

    $moduleinfo,

    $course

) {

    /*
     * Only for assignments.
     */

    if (

        empty($moduleinfo->modulename)

        ||

        $moduleinfo->modulename !== 'assign'

    ) {

        return $moduleinfo;
    }

    /*
     * Ensure field exists.
     */

    if (
        !isset(
            $moduleinfo->journalanswerkey
        )
    ) {

        return $moduleinfo;
    }

    /*
     * Get module context.
     */

    $context =
        context_module::instance(
            $moduleinfo->coursemodule
        );

    /*
     * Save uploaded JSON.
     */

    file_save_draft_area_files(

        $moduleinfo->journalanswerkey,

        $context->id,

        'local_journalgrader',

        'answerkey',

        0,

        [

            'subdirs' => 0,

            'maxfiles' => 1,

            'accepted_types' => [
                '.json'
            ]
        ]
    );

    return $moduleinfo;
}