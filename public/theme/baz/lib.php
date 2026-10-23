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
 * Theme functions.
 *
 * @package   theme_baz
 * @copyright Copyright © 2021 onwards Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

/**
 * Get theme setting
 *
 * @param string $setting
 * @param bool $format
 * @return string
 */
function theme_baz_get_setting($setting, $format = false) {
    $theme = theme_config::load('baz');

    if (empty($theme->settings->$setting)) {
        return false;
    }

    if (!$format) {
        return $theme->settings->$setting;
    }

    if ($format === 'format_text') {
        return format_text($theme->settings->$setting, FORMAT_PLAIN);
    }

    if ($format === 'format_html') {
        return format_text($theme->settings->$setting, FORMAT_HTML, array('trusted' => true, 'noclean' => true));
    }

    return format_string($theme->settings->$setting);
}

/**
 * Post process the CSS tree.
 *
 * @param string $tree The CSS tree.
 * @param theme_config $theme The theme config object.
 */
function theme_baz_css_tree_post_processor($tree, $theme) {
    error_log('theme_baz_css_tree_post_processor() is deprecated. Required' .
        'prefixes for Bootstrap are now in theme/baz/scss/moodle/prefixes.scss');
    $prefixer = new theme_baz\autoprefixer($tree);
    $prefixer->prefix();
}

/**
 * Get the current user preferences that are available
 *
 * @return array[]
 */
function theme_baz_user_preferences(): array {
    return [
        'drawer-open-block' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'drawer-open-index' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'mycourses-on' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'mycourses-hidden-on' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'mycourses-inprogress-on' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'darkmode-on' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'sidepre-open' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'drawer-open-nav' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_baz_get_extra_scss($theme) {
    $content = '';

    // Sets the login background image.
    // Check login layout, only layout #1 has background image.
    $loginlayout = theme_baz_get_setting('setloginlayout');
    $loginlayoutimg = false;

    if ($loginlayout == 1 || $loginlayout == 4 || $loginlayout == 5) {
        $loginlayoutimg = true;
    }
    if ($loginlayout == 2 || $loginlayout == 3) {
        $loginlayoutimg = false;
    } else {
        $loginlayoutimg = false;
    }

    // Sets the background image, and its settings.
    $imageurl = $theme->setting_file_url('bodybgimg', 'bodybgimg');
    if (!empty($imageurl)) {
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl'); background-size: cover; background-position: center; background-attachment: fixed; background-repeat: no-repeat;";
        $content .= ' } }';
    }

    $loginbackgroundimageurl = $theme->setting_file_url('loginbg', 'loginbg');
    if ($loginlayout == 1 || $loginlayout == 4 || $loginlayout == 5) {
        if (!empty($loginbackgroundimageurl)) {
            $content .= 'body.path-login { ';
            $content .= "background-image: url('$loginbackgroundimageurl'); background-size: cover; background-attachment: fixed;";
            $content .= ' }';
        }
    }

    $forcefwvideo = theme_baz_get_setting('forcefwvideo');
    if ($forcefwvideo == 1) {
        $content .= '.mediaplugin.mediaplugin_videojs div[style*="max-width"] {margin-left:auto;margin-right:auto;
            width: 100%; max-width: 100% !important;}';
    }

    // Always return the background image with the scss when we have it.
    return !empty($theme->settings->scss) ? $theme->settings->scss . ' ' . $content : $content;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_baz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('baz');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        if ($filearea === 'hvp') {
            return theme_baz_serve_hvp_css($args[1], $theme);
        }
        if ($filearea === 'favicon') {
            return $theme->setting_file_serve('favicon', $args, $forcedownload, $options);
        } else if ($filearea === 'logo') {
            return $theme->setting_file_serve('logo', $args, $forcedownload, $options);
        } else if ($filearea === 'loginbg') {
            return $theme->setting_file_serve('loginbg', $args, $forcedownload, $options);
        } else if ($filearea === 'bodybgimg') {
            return $theme->setting_file_serve('bodybgimg', $args, $forcedownload, $options);
        } else if ($filearea === 'customloginlogo') {
            return $theme->setting_file_serve('customloginlogo', $args, $forcedownload, $options);
        } else if ($filearea === 'customlogindmlogo') {
            return $theme->setting_file_serve('customlogindmlogo', $args, $forcedownload, $options);
        } else if ($filearea === 'customlogo') {
            return $theme->setting_file_serve('customlogo', $args, $forcedownload, $options);
        } else if ($filearea === 'fontfiles') {
            return $theme->setting_file_serve('fontfiles', $args, $forcedownload, $options);
        } else if ($filearea === 'bazsettingsimgs') {
            return $theme->setting_file_serve('bazsettingsimgs', $args, $forcedownload, $options);
        } else if (preg_match("/^block1slideimg[1-9][0-9]?$/", $filearea) !== false) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if (preg_match("/^block5itemimg[1-9][0-9]?$/", $filearea) !== false) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        } else if ($filearea === 'block2videoposter') {
            return $theme->setting_file_serve('block2videoposter', $args, $forcedownload, $options);
        } else if ($filearea === 'block2img') {
            return $theme->setting_file_serve('block2img', $args, $forcedownload, $options);
        } else if ($filearea === 'block2videomp4') {
            return $theme->setting_file_serve('block2videomp4', $args, $forcedownload, $options);
        } else if ($filearea === 'block2videowebm') {
            return $theme->setting_file_serve('block2videowebm', $args, $forcedownload, $options);
        } else if ($filearea === 'footerbgimg') {
            return $theme->setting_file_serve('footerbgimg', $args, $forcedownload, $options);
        } else {
            send_file_not_found();
        }
    }
}

/**
 * Get the URL of files from theme settings.
 *
 * @param $setting
 * @param $filearea
 * @param $theme
 * @return array|false|string|string[]|null
 * @throws dml_exception
 */
function theme_baz_setting_file_url($setting, $filearea, $theme) {
    global $CFG;

    $component = 'theme_baz';
    $itemid = 0;
    $filepath = $theme->settings->$filearea;

    if (empty($filepath)) {
        return false;
    }
    $syscontext = context_system::instance();

    $url = moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php", "/$syscontext->id/$component/$filearea/$itemid" . $filepath);

    // Now this is tricky because the we can not hardcode http or https here, lets use the relative link.
    // Note: unfortunately moodle_url does not support //urls yet.

    $url = preg_replace('|^https?://|i', '//', $url->out(false));

    return $url;
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_baz_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/baz/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/baz/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_baz', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/baz/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get compiled css.
 *
 * @return string compiled css
 */
function theme_baz_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/baz/style/moodle.css');
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_baz_get_pre_scss($theme) {
    global $CFG;

    $scss = '';
    $configurable = [
        // Config key => [variableName, ...].
        'colorloginbgtext' => ['colorloginbgtext'],
        //Block 1
        'block1sliderwrapperbg' => ['block1slidewrapperbg'],
        // Customization
        'fontweightheadings' => ['headings-font-weight'],
        'fontbody' => ['font-family-base'],
        'fontweightregular' => ['font-weight-normal'],
        'fontweightmedium' => ['font-weight-medium'],
        'fontweightbold' => ['font-weight-bold'],
        //Text
        'colorbody' => ['body-color'],
        'colorbodysecondary' => ['body-color-secondary'],
        'colorbodylight' => ['body-color-light'],
        'colorlink' => ['link-color'],
        'colorlinkhover' => ['link-hover-color'],
        // Grays.
        'white' => ['white'],
        'black' => ['black'],
        'colorgray100' => ['gray-100'],
        'colorgray200' => ['gray-200'],
        'colorgray300' => ['gray-300'],
        'colorgray400' => ['gray-400'],
        'colorgray500' => ['gray-500'],
        'colorgray600' => ['gray-600'],
        'colorgray700' => ['gray-700'],
        'colorgray800' => ['gray-800'],
        'colorgray900' => ['gray-900'],
        // Primary.
        'colorprimary100' => ['primary-color-100'],
        'colorprimary200' => ['primary-color-200'],
        'colorprimary300' => ['primary-color-300'],
        'colorprimary400' => ['primary-color-400'],
        'colorprimary500' => ['primary-color-500'],
        'colorprimary600' => ['primary-color-600'],
        'colorprimary700' => ['primary-color-700'],
        'colorprimary800' => ['primary-color-800'],
        'colorprimary900' => ['primary-color-900'],
        //Secondary
        'colorsecondary100' => ['secondary-color-100'],
        'colorsecondary200' => ['secondary-color-200'],
        'colorsecondary300' => ['secondary-color-300'],
        'colorsecondary400' => ['secondary-color-400'],
        'colorsecondary500' => ['secondary-color-500'],
        'colorsecondary600' => ['secondary-color-600'],
        'colorsecondary700' => ['secondary-color-700'],
        'colorsecondary800' => ['secondary-color-800'],
        'colorsecondary900' => ['secondary-color-900'],
        //Others
        'colorbodybg' => ['body-bg'],
        'colorcontainerbg' => ['container-bg'],
        'colorborder' => ['border-color'],
        //Topbar
        'colortopbarbg' => ['topbar-bg'],
        'colortopbartext' => ['topbar-text'],
        'colortopbarbtn' => ['topbar-btn'],
        'colortopbarbtntext' => ['topbar-btn-text'],
        'colortopbarbtnhover' => ['topbar-btn-hover'],
        'colortopbarbtnhovertext' => ['topbar-btn-hover-text'],
        //'stickybreadcrumbs' => ['stickybreadcrumbs'],
        'wrappermdfw' => ['wrappermdfw']
    ];

    // Prepend variables first.
    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function ($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Build the guest access hint HTML code.
 *
 * @param int $courseid The course ID.
 * @return string.
 */
function theme_baz_get_course_guest_access_hint($courseid) {
    global $CFG;
    require_once($CFG->dirroot . '/enrol/self/lib.php');

    $html = '';
    $instances = enrol_get_instances($courseid, true);
    $plugins = enrol_get_plugins(true);
    foreach ($instances as $instance) {
        if (!isset($plugins[$instance->enrol])) {
            continue;
        }
        $plugin = $plugins[$instance->enrol];
        if ($plugin->show_enrolme_link($instance)) {
            $html = html_writer::tag('div', get_string(
                'showhintcourseguestaccesslink',
                'theme_baz',
                array('url' => $CFG->wwwroot . '/enrol/index.php?id=' . $courseid)
            ));
            break;
        }
    }

    return $html;
}


/**
 * Build the course page information banners HTML code.
 * This function evaluates and composes all information banners which may appear on a course page below the full header.
 *
 * @return string.
 */
function theme_baz_get_course_information_banners() {
    global $CFG, $COURSE, $PAGE, $USER, $OUTPUT;

    // Require user library.
    require_once($CFG->dirroot . '/user/lib.php');

    // Initialize HTML code.
    $html = '';

    // Check if user is admin, teacher or editing teacher.
    if (
        user_has_role_assignment($USER->id, '1') ||
        user_has_role_assignment($USER->id, '2') ||
        user_has_role_assignment($USER->id, '3') ||
        user_has_role_assignment($USER->id, '4')
    ) {
        $allowtoshowhint = true;
    } else {
        $allowtoshowhint = false;
    }

    // If the setting showhintcoursehidden is set and the visibility of the course is hidden and
    // a hint for the visibility will be shown.
    if (
        get_config('theme_baz', 'showhintcoursehidden') == 1
        && $allowtoshowhint == true
        && $PAGE->has_set_url()
        && $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
        && $COURSE->visible == false
    ) {

        // Prepare template context.
        $templatecontext = array('courseid' => $COURSE->id);

        // If the user has the capability to change the course settings, an additional link to the course settings is shown.
        if (has_capability('moodle/course:update', context_course::instance($COURSE->id))) {
            $templatecontext['showcoursesettingslink'] = true;
        } else {
            $templatecontext['showcoursesettingslink'] = false;
        }

        // Render template and add it to HTML code.
        $html .= $OUTPUT->render_from_template('theme_baz/course-hint-hidden', $templatecontext);
    }

    // If the setting showhintcourseguestaccess is set and the user is accessing the course with guest access,
    // a hint for users is shown.
    // We also check that the user did not switch the role. This is a special case for roles that can fully access the course
    // without being enrolled. A role switch would show the guest access hint additionally in that case and this is not
    // intended.
    if (
        get_config('theme_baz', 'showhintcourseguestaccess') == 1
        && is_guest(\context_course::instance($COURSE->id), $USER->id)
        && $PAGE->has_set_url()
        && $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
        && !is_role_switched($COURSE->id)
    ) {

        // Require self enrolment library.
        require_once($CFG->dirroot . '/enrol/self/lib.php');

        // Prepare template context.
        $templatecontext = array(
            'courseid' => $COURSE->id,
            'role' => role_get_name(get_guest_role())
        );

        // Search for an available self enrolment link in this course.
        $templatecontext['showselfenrollink'] = false;
        $instances = enrol_get_instances($COURSE->id, true);
        $plugins = enrol_get_plugins(true);
        foreach ($instances as $instance) {
            // If the enrolment plugin isn't enabled currently, skip it.
            if (!isset($plugins[$instance->enrol])) {
                continue;
            }

            // Remember the enrolment plugin.
            $plugin = $plugins[$instance->enrol];

            // If there is a self enrolment link.
            if ($plugin->show_enrolme_link($instance)) {
                $templatecontext['showselfenrollink'] = true;
                break;
            }
        }

        // Render template and add it to HTML code.
        $html .= $OUTPUT->render_from_template('theme_baz/course-hint-guestaccess', $templatecontext);
    }

    // If the setting showhintcourseselfenrol is set, a hint for users is shown that the course allows unrestricted self
    // enrolment. This hint is only shown if the course is visible, the self enrolment is visible and if the user has the
    // capability "theme/baz:viewhintcourseselfenrol".
    if (
        get_config('theme_baz', 'showhintcourseselfenrol') == 1
        && $allowtoshowhint == true
        && $PAGE->has_set_url()
        && $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
        && $COURSE->visible == true
    ) {

        // Get the active enrol instances for this course.
        $enrolinstances = enrol_get_instances($COURSE->id, true);

        // Prepare to remember when self enrolment is / will be possible.
        $selfenrolmentpossiblecurrently = false;
        $selfenrolmentpossiblefuture = false;
        foreach ($enrolinstances as $instance) {
            // Check if unrestricted self enrolment is possible currently or in the future.
            $now = (new \DateTime("now", \core_date::get_server_timezone_object()))->getTimestamp();
            if (
                $instance->enrol == 'self' && empty($instance->password) && $instance->customint6 == 1 &&
                (empty($instance->enrolenddate) || $instance->enrolenddate > $now)
            ) {

                // Build enrol instance object with all necessary information for rendering the note later.
                $instanceobject = new stdClass();

                // Remember instance name.
                if (empty($instance->name)) {
                    $instanceobject->name = get_string('pluginname', 'enrol_self') .
                        " (" . get_string('defaultcoursestudent', 'core') . ")";
                } else {
                    $instanceobject->name = $instance->name;
                }

                // Remember type of unrestrictedness.
                if (empty($instance->enrolenddate) && empty($instance->enrolstartdate)) {
                    $instanceobject->unrestrictedness = 'unlimited';
                    $selfenrolmentpossiblecurrently = true;
                } else if (
                    empty($instance->enrolstartdate) &&
                    !empty($instance->enrolenddate) && $instance->enrolenddate > $now
                ) {
                    $instanceobject->unrestrictedness = 'until';
                    $selfenrolmentpossiblecurrently = true;
                } else if (
                    empty($instance->enrolenddate) &&
                    !empty($instance->enrolstartdate) && $instance->enrolstartdate > $now
                ) {
                    $instanceobject->unrestrictedness = 'from';
                    $selfenrolmentpossiblefuture = true;
                } else if (
                    empty($instance->enrolenddate) &&
                    !empty($instance->enrolstartdate) && $instance->enrolstartdate <= $now
                ) {
                    $instanceobject->unrestrictedness = 'since';
                    $selfenrolmentpossiblecurrently = true;
                } else if (
                    !empty($instance->enrolstartdate) && $instance->enrolstartdate > $now &&
                    !empty($instance->enrolenddate) && $instance->enrolenddate > $now
                ) {
                    $instanceobject->unrestrictedness = 'fromuntil';
                    $selfenrolmentpossiblefuture = true;
                } else if (
                    !empty($instance->enrolstartdate) && $instance->enrolstartdate <= $now &&
                    !empty($instance->enrolenddate) && $instance->enrolenddate > $now
                ) {
                    $instanceobject->unrestrictedness = 'sinceuntil';
                    $selfenrolmentpossiblecurrently = true;
                } else {
                    // This should not happen, thus continue to next instance.
                    continue;
                }

                // Remember enrol start date.
                if (!empty($instance->enrolstartdate)) {
                    $instanceobject->startdate = $instance->enrolstartdate;
                } else {
                    $instanceobject->startdate = null;
                }

                // Remember enrol end date.
                if (!empty($instance->enrolenddate)) {
                    $instanceobject->enddate = $instance->enrolenddate;
                } else {
                    $instanceobject->enddate = null;
                }

                // Remember this instance.
                $selfenrolinstances[$instance->id] = $instanceobject;
            }
        }

        // If there is at least one unrestricted enrolment instance,
        // show the hint with information about each unrestricted active self enrolment in the course.
        if (
            !empty($selfenrolinstances) &&
            ($selfenrolmentpossiblecurrently == true || $selfenrolmentpossiblefuture == true)
        ) {

            // Prepare template context.
            $templatecontext = array();

            // Add the start of the hint t the template context
            // depending on the fact if enrolment is already possible currently or will be in the future.
            if ($selfenrolmentpossiblecurrently == true) {
                $templatecontext['selfenrolhintstart'] = get_string('showhintcourseselfenrolstartcurrently', 'theme_baz');
            } else if ($selfenrolmentpossiblefuture == true) {
                $templatecontext['selfenrolhintstart'] = get_string('showhintcourseselfenrolstartfuture', 'theme_baz');
            }

            // Iterate over all enrolment instances to output the details.
            foreach ($selfenrolinstances as $selfenrolinstanceid => $selfenrolinstanceobject) {
                // If the user has the capability to config self enrolments, enrich the instance name with the settings link.
                if (has_capability('enrol/self:config', \context_course::instance($COURSE->id))) {
                    $url = new moodle_url('/enrol/editinstance.php', array(
                        'courseid' => $COURSE->id,
                        'id' => $selfenrolinstanceid, 'type' => 'self'
                    ));
                    $selfenrolinstanceobject->name = html_writer::link($url, $selfenrolinstanceobject->name);
                }

                // Add the enrolment instance information to the template context depending on the instance configuration.
                if ($selfenrolinstanceobject->unrestrictedness == 'unlimited') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolunlimited',
                        'theme_baz',
                        array('name' => $selfenrolinstanceobject->name)
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'until') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenroluntil',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'until' => userdate($selfenrolinstanceobject->enddate)
                        )
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'from') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolfrom',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'from' => userdate($selfenrolinstanceobject->startdate)
                        )
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'since') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolsince',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'since' => userdate($selfenrolinstanceobject->startdate)
                        )
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'fromuntil') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolfromuntil',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'until' => userdate($selfenrolinstanceobject->enddate),
                            'from' => userdate($selfenrolinstanceobject->startdate)
                        )
                    );
                } else if ($selfenrolinstanceobject->unrestrictedness == 'sinceuntil') {
                    $templatecontext['selfenrolinstances'][] = get_string(
                        'showhintcourseselfenrolsinceuntil',
                        'theme_baz',
                        array(
                            'name' => $selfenrolinstanceobject->name,
                            'until' => userdate($selfenrolinstanceobject->enddate),
                            'since' => userdate($selfenrolinstanceobject->startdate)
                        )
                    );
                }
            }

            // If the user has the capability to config self enrolments, add the call for action to the template context.
            if (has_capability('enrol/self:config', \context_course::instance($COURSE->id))) {
                $templatecontext['calltoaction'] = true;
            } else {
                $templatecontext['calltoaction'] = false;
            }

            // Render template and add it to HTML code.
            $html .= $OUTPUT->render_from_template('theme_baz/course-hint-selfenrol', $templatecontext);
        }
    }

    // If the setting showswitchedroleincourse is set and the user has switched his role,
    // a hint for the role switch will be shown.
    if (
        get_config('theme_baz', 'showswitchedroleincourse') == 1
        && is_role_switched($COURSE->id)
    ) {

        // Get the role name switched to.
        $opts = \user_get_user_navigation_info($USER, $PAGE);
        $role = $opts->metadata['rolename'];

        // Get the URL to switch back (normal role).
        $url = new moodle_url(
            '/course/switchrole.php',
            array(
                'id' => $COURSE->id,
                'sesskey' => sesskey(),
                'switchrole' => 0,
                'returnurl' => $PAGE->url->out_as_local_url(false)
            )
        );

        // Prepare template context.
        $templatecontext = array(
            'role' => $role,
            'url' => $url->out()
        );

        // Render template and add it to HTML code.
        $html .= $OUTPUT->render_from_template('theme_baz/course-hint-switchedrole', $templatecontext);
    }

    // Return HTML code.
    return $html;
}

/**
 * Serves the H5P Custom CSS.
 *
 * @param string $filename The filename.
 * @param theme_config $theme The theme config object.
 *
 * @throws dml_exception
 */
function theme_baz_serve_hvp_css($filename, $theme) {
    global $CFG, $PAGE;

    require_once($CFG->dirroot . '/lib/configonlylib.php'); // For min_enable_zlib_compression.

    $PAGE->set_context(context_system::instance());
    $themename = $theme->name;

    $settings = new \theme_baz\util\theme_settings();
    $content = $settings->hvpcss;

    $md5content = md5($content);
    $md5stored = get_config('theme_baz', 'hvpccssmd5');
    if ((empty($md5stored)) || ($md5stored != $md5content)) {
        // Content changed, so the last modified time needs to change.
        set_config('hvpccssmd5', $md5content, $themename);
        $lastmodified = time();
        set_config('hvpccsslm', $lastmodified, $themename);
    } else {
        $lastmodified = get_config($themename, 'hvpccsslm');
        if (empty($lastmodified)) {
            $lastmodified = time();
        }
    }

    // Sixty days only - the revision may get incremented quite often.
    $lifetime = 60 * 60 * 24 * 60;

    header('HTTP/1.1 200 OK');

    header('Etag: "' . $md5content . '"');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $lifetime) . ' GMT');
    header('Pragma: ');
    header('Cache-Control: public, max-age=' . $lifetime);
    header('Accept-Ranges: none');
    header('Content-Type: text/css; charset=utf-8');
    if (!min_enable_zlib_compression()) {
        header('Content-Length: ' . strlen($content));
    }

    echo $content;

    die;
}


function theme_baz_get_theme_email_context() {
    global $CFG;

    $theme = theme_config::load('baz');

    require_once($CFG->dirroot . '/theme/baz/classes/theme_email.php');

    $theme_mail = new theme_email();
    $context = $theme_mail->get_context();

    return $context;

}

/**
 * Return the files from the additionalresources file area as templatecontext structure.
 * It was designed to compose the files for the settings-additionalresources-filelist.mustache template.
 * This function always loads the files from the filearea which is not really performant.
 * Thus, you have to take care where and how often you use it (or add some caching).
 *
 * @return array|null
 * @throws coding_exception
 * @throws dml_exception
 */
function theme_baz_get_additionalresources_templatecontext() {
    global $OUTPUT;

    // Static variable to remember the files for subsequent calls of this function.
    static $filesforcontext = null;

    if ($filesforcontext == null) {
        // Get the system context.
        $systemcontext = \context_system::instance();

        // Get filearea.
        $fs = get_file_storage();

        // Get all files from filearea.
        $files = $fs->get_area_files($systemcontext->id, 'theme_baz', 'additionalresources', false, 'itemid', false);

        // Iterate over the files and fill the templatecontext of the file list.
        $filesforcontext = [];
        foreach ($files as $af) {
            $urlpersistent = new moodle_url('/pluginfile.php/1/theme_baz/additionalresources/0/'.$af->get_filename());
            $urlrevisioned = new moodle_url('/pluginfile.php/1/theme_baz/additionalresources/'.theme_get_revision().
                    '/'.$af->get_filename());
            $filesforcontext[] = ['filename' => $af->get_filename(),
                                        'filetype' => $af->get_mimetype(),
                                        'filesize' => display_size($af->get_filesize()),
                                        'fileicon' => $OUTPUT->image_icon(file_file_icon($af), get_mimetype_description($af)),
                                        'fileurlpersistent' => $urlpersistent->out(),
                                        'fileurlrevisioned' => $urlrevisioned->out(), ];
        }
    }

    return $filesforcontext;
}

/**
 * Return the files from the customfonts file area as templatecontext structure.
 * It was designed to compose the files for the settings-customfonts-filelist.mustache template.
 * This function always loads the files from the filearea which is not really performant.
 * Thus, you have to take care where and how often you use it (or add some caching).
 *
 * @return array|null
 * @throws coding_exception
 * @throws dml_exception
 */
function theme_baz_get_customfonts_templatecontext() {
    global $OUTPUT;

    // Static variable to remember the files for subsequent calls of this function.
    static $filesforcontext = null;

    if ($filesforcontext == null) {
        // Get the system context.
        $systemcontext = \context_system::instance();

        // Get filearea.
        $fs = get_file_storage();

        // Get all files from filearea.
        $files = $fs->get_area_files($systemcontext->id, 'theme_baz', 'fontfiles', false, 'itemid', false);

        // Get the webfonts extensions list.
        $webfonts = theme_baz_get_webfonts_extensions();

        // Iterate over the files.
        $filesforcontext = [];
        foreach ($files as $af) {
            // Get the filename.
            $filename = $af->get_filename();

            // Check if the file is really a font file (as we can't really rely on the upload restriction in settings.php)
            // according to its file suffix (as the filetype might not have a known mimetype).
            // If it isn't a font file, skip it.
            $filenamesuffix = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array('.'.$filenamesuffix, $webfonts)) {
                continue;
            }

            // Otherwise, fill the templatecontext of the file list.
            $urlpersistent = new moodle_url('/pluginfile.php/1/theme_baz/fontfiles/0/'.$filename);
            $filesforcontext[] = ['filename' => $filename,
                    'fileurlpersistent' => $urlpersistent->out(), ];
        }
    }

    return $filesforcontext;
}

/**
 * Helper function which returns an array of accepted webfonts extensions (including the dots).
 *
 * @return array
 */
function theme_baz_get_webfonts_extensions() {
    return ['.eot', '.otf', '.svg', '.ttf', '.woff', '.woff2'];
}

/**
 * Helper function which makes sure that all webfont file types are registered in the system.
 * The webfont file types need to be registered in the system, otherwise the admin settings filepicker wouldn't allow restricting
 * the uploadable file types to webfonts only.
 *
 * Please note: If custom filetypes are defined in config.php, registering additional filetypes is not possible
 * due to a restriction in the set_custom_types() function in Moodle core. In this case, this function does not
 * register anything and will return false.
 *
 * @return boolean true if the filetypes were registered, false if not.
 * @throws coding_exception
 */
function theme_baz_register_webfonts_filetypes() {
    global $CFG;

    // If customfiletypes are set in config.php or PHP tests are running, we can't do anything.
    if (array_key_exists('customfiletypes', $CFG->config_php_settings) || PHPUNIT_TEST) {
        return false;
    }

    // Our array of webfont file types to register.
    // As we want to keep things simple, we do not set a particular icon for these file types.
    // Likewise, we do not set any type groups or use descriptions from the language pack.
    $webfonts = [
            'eot' => [
                    'extension' => 'eot',
                    'mimetype' => 'application/vnd.ms-fontobject',
                    'coreicon' => 'unknown',
            ],
            'otf' => [
                    'extension' => 'otf',
                    'mimetype' => 'font/otf',
                    'coreicon' => 'unknown',
            ],
            'svg' => [
                    'extension' => 'svg',
                    'mimetype' => 'image/svg+xml',
                    'coreicon' => 'unknown',
            ],
            'ttf' => [
                    'extension' => 'ttf',
                    'mimetype' => 'font/ttf',
                    'coreicon' => 'unknown',
            ],
            'woff' => [
                    'extension' => 'woff',
                    'mimetype' => 'font/woff',
                    'coreicon' => 'unknown',
            ],
            'woff2' => [
                    'extension' => 'woff2',
                    'mimetype' => 'font/woff2',
                    'coreicon' => 'unknown',
            ],
    ];

    // First, get the list of currently registered file types.
    $currenttypes = core_filetypes::get_types();

    // Iterate over the webfonts file types.
    foreach ($webfonts as $f) {
        // If the file type is already registered, skip it.
        if (array_key_exists($f['extension'], $currenttypes)) {
            continue;
        }

        // Otherwise, register the file type.
        core_filetypes::add_type($f['extension'], $f['mimetype'], $f['coreicon']);
    }

    return true;
}
