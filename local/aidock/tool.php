<?php
/**
 * AI Dock - landing page for one AI tool (used when the tool has no chat on the current page).
 *
 * @package    local_aidock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');

$t = required_param('t', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
require_login();

$tools = \local_aidock\tools::all();
if (!isset($tools[$t])) {
    throw new moodle_exception('invalidparameter');
}
$tool = $tools[$t];
$name = get_string($t, 'local_aidock');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/aidock/tool.php', ['t' => $t, 'courseid' => $courseid]));
$PAGE->set_title($name);
$PAGE->set_heading($name);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo html_writer::start_div('card p-4 mb-3');
echo html_writer::tag('h3', '<i class="fa ' . $tool['icon'] . '" style="color:' . $tool['color'] . '"></i> ' . s($name)
    . ' <span class="badge" style="color:#fff;background:' . ($tool['ready'] ? '#16a34a' : '#64748b') . '">'
    . get_string($tool['ready'] ? 'statuson' : 'statusoff', 'local_aidock') . '</span>');

$a = $t === 'geniai' ? (get_config('local_geniai', 'geniainame') ?: 'GeniAI') : null;
echo html_writer::tag('p', get_string('tool_' . $t, 'local_aidock', $a), ['class' => 'lead']);

if (!$tool['ready']) {
    if (is_siteadmin()) {
        echo html_writer::link($tool['setup'], get_string('adminsetup', 'local_aidock'), ['class' => 'btn btn-primary']);
    } else {
        echo $OUTPUT->notification(get_string('notready', 'local_aidock', $name), 'info');
    }
} else if ($t === 'tutorai' || $t === 'geniai') {
    echo $OUTPUT->notification(get_string('opencourse', 'local_aidock', $name), 'info');
    echo html_writer::link(new moodle_url('/my/courses.php'), get_string('mycourses'), ['class' => 'btn btn-primary']);
} else if ($t === 'edugears') {
    $typeid = \local_aidock\tools::edugears_typeid();
    echo html_writer::tag('h5', get_string('youractivities', 'local_aidock'));
    $list = [];
    foreach (enrol_get_my_courses(['id'], 'fullname ASC') as $c) {
        $modinfo = get_fast_modinfo($c->id);
        foreach ($modinfo->get_instances_of('lti') as $cm) {
            if (!$cm->uservisible) { continue; }
            $typ = $DB->get_field('lti', 'typeid', ['id' => $cm->instance]);
            if ((int)$typ === $typeid) {
                $list[] = html_writer::link($cm->url, format_string($c->fullname) . ' – ' . $cm->get_formatted_name());
            }
        }
    }
    echo $list ? html_writer::alist($list) : $OUTPUT->notification(get_string('noactivities', 'local_aidock'), 'info');
} else if ($t === 'raison') {
    if (\local_corolair\local\launch_access::can_launch($courseid)) {
        $url = new moodle_url('/local/corolair/trainer.php', $courseid ? ['raisonsourcecourse' => $courseid] : []);
        echo html_writer::link($url, get_string('openraison', 'local_aidock'), ['class' => 'btn btn-primary']);
    } else {
        echo $OUTPUT->notification(get_string('raisonstudent', 'local_aidock'), 'info');
    }
}
echo html_writer::div(html_writer::link($courseid ? new moodle_url('/course/view.php', ['id' => $courseid]) : new moodle_url('/my/'),
    get_string('back', 'local_aidock')), 'mt-3');
echo html_writer::end_div();
echo $OUTPUT->footer();
