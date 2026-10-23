<?php
//include 'editor.html';

require( '../../config.php' );
global $CFG, $PAGE, $USER;
require_once($CFG->dirroot.'/lib/form/filemanager.php');
require_once(dirname(__FILE__).'/lib.php');
$CFG->cachejs = true;

// Require Login.
require_login();
$blockinstance = required_param('bui_edit', PARAM_INT);
require_capability(
    "block/edwiseradvancedblock:cancustomizelive",
    \context_block::instance($blockinstance)
);

// $context = context_system::instance();
$baseurl = $CFG->wwwroot . '/local/edwiserpagebuilder/editor.php';
/**
 * End Loading block editor css.
 */

$PAGE->set_pagelayout( 'popup' );
$PAGE->set_context( context_system::instance() );
$PAGE->set_url( '/local/edwiserpagebuilder/editor.php' );
$PAGE->set_title( get_string( 'eb_block_editor_title', 'local_edwiserpagebuilder' ) );
$PAGE->set_cacheable( false );
$themeclass = 'theme_' . $PAGE->theme->name;
$PAGE->add_body_classes( array( 'edwiserpagebuilder', $themeclass ) );
if(!is_epb_pro_allowed()){
    $PAGE->add_body_classes( array( 'epb-free-version' ) );
} else {
    $PAGE->add_body_classes( array( 'epb-pro-version' ) );
}
/**
 * Start Loading block editor Js files.
 */

// $PAGE->requires->jquery();
// $PAGE->requires->jquery_plugin( 'ui' );
// $PAGE->requires->jquery_plugin( 'ui-css' );
// $PAGE->requires->js(new moodle_url("/local/edwiserpagebuilder/js/jquery.hotkeys.js"));

$PAGE->requires->css( '/local/edwiserpagebuilder/styles/editor.css' );
$PAGE->requires->css( '/local/edwiserpagebuilder/styles/edw_editor.css' );
$PAGE->requires->css( '/local/edwiserpagebuilder/styles/vvvebjs-editor-helpers.css' );

$PAGE->requires->js(new moodle_url("/local/edwiserpagebuilder/libs/builder/undo.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/inputs.js"));
// $PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/plugin-media.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/plugin-google-fonts.js"));

$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-common.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/plugin-aos.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-html.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-elements.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/section.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-bootstrap5.js"));

$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-widgets.js"));
// $PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/oembed.js"));
// $PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-embeds.js"));

// $PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/demo/landing/sections/sections.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/sections-bootstrap4.js"));
$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/blocks-bootstrap4.js"));
// $PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/plugin-ai-assistant.js"));





$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/js/edwiserpagebuilder.js" ));
$PAGE->requires->js_call_amd('local_edwiserpagebuilder/edwiserpagebuilder', 'init', [ 'contextid' => $PAGE->context->id ]);

// $licensestatus = get_epb_license_status();
// if ($licensestatus !== 'valid') {
//     throw new moodle_exception(get_string('licensenotactive', 'local_edwiserpagebuilder'));
// }


/**
 * Completed block editor js loading.
 */
$formsavailable = check_plugin_available('local_edwiserform');

$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-edwiser.js" ) );
$PAGE->requires->js_call_amd('local_edwiserpagebuilder/components-edwiser', 'init', array($formsavailable));

$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-edwslider.js" ) );
$PAGE->requires->js_call_amd('local_edwiserpagebuilder/components-edwslider',  'init');

$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-edwtestimonial.js" ) );
$PAGE->requires->js_call_amd('local_edwiserpagebuilder/components-edwtestimonial',  'init');

$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-imagegallery.js" ) );
$PAGE->requires->js_call_amd('local_edwiserpagebuilder/components-imagegallery',  'init');

$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-profilesliders.js" ) );
$PAGE->requires->js_call_amd('local_edwiserpagebuilder/components-profilesliders',  'init');

$PAGE->requires->js( new moodle_url("/local/edwiserpagebuilder/libs/builder/components-tabwithaccordioan.js" ) );
$PAGE->requires->js_call_amd('local_edwiserpagebuilder/components-tabwithaccordioan',  'init');

if(!is_epb_pro_allowed()){
    $PAGE->requires->js_init_code("
        !function(e,t,n){function a(){var e=t.getElementsByTagName('script')[0],n=t.createElement('script');n.type='text/javascript',n.async=!0,n.src='https://beacon-v2.helpscout.net',e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],'complete'===t.readyState)return a();e.attachEvent?e.attachEvent('onload',a):e.addEventListener('load',a,!1)}(window,document,window.Beacon||function(){});
        window.Beacon('init', '8099719e-d2d1-45db-ae26-e326c9fd3c08');
    ");
}

echo $OUTPUT->header();
echo $OUTPUT->container_start();
require_once('editor-template.php');
echo $OUTPUT->container_end();
echo $OUTPUT->footer();
