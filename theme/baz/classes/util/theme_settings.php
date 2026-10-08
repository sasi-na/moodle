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
 * Mustache helper to load a theme configuration.
 *
 * @package    theme_baz
 * @copyright  Copyright © 2021 onwards, Marcin Czaja | RoseaThemes, rosea.io - Rosea Themes
 * @license    Commercial https://themeforest.net/licenses
 */

namespace theme_baz\util;

use theme_config;
use stdClass;

/**
 * Helper to load a theme configuration.
 *
 * @package    theme_baz
 * @copyright Copyright © 2018 onwards, Marcin Czaja | RoseaThemes, rosea.io - Rosea Themes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_settings {
    /**
     * @var \stdClass $theme The theme object.
     */
    protected $theme;
    /**
     * @var array $files Theme file settings.
     */
    protected $files = [
        'customlogo',
        'customdmlogo',
        'customsidebarlogo',
        'customsidebardmlogo',
        'seomanifestjson',
        'favicon16',
        'favicon32',
        'faviconsafaritab',
        'seoappletouchicon',
        'footerbgimg',
        'loginbg',
        'fontfiles'
    ];

    /**
     * Class constructor
     */
    public function __construct() {
        $this->theme = theme_config::load('baz');
    }

    /**
     * Magic method to get theme settings
     *
     * @param string $name
     *
     * @return false|string|null
     */
    public function __get(string $name) {
        if (in_array($name, $this->files)) {
            return $this->theme->setting_file_url($name, $name);
        }

        if (empty($this->theme->settings->$name)) {
            return false;
        }

        return $this->theme->settings->$name;
    }

    public function global_settings() {
        $templatecontext = [];

        $elements = [
            'googlefonturl',
            'customlogo',
            'customdmlogo',
            'customsidebarlogo',
            'customsidebardmlogo',
            'seomanifestjson',
            'seoappletouchicon',
            'seothemecolor',
            'favicon16',
            'favicon32',
            'faviconsafaritab',
            'faviconsafaritabcolor',
            'footerbgimg',
            'loginbg',
            'themeauthor',
            'displaycustomalert',
            'closecustomalert',
            'topbarlogoareaon',
            'topbarhamburgermenu',
            'logocontainer',
            'customlogoandname',
            'logoandname',
            'fontfiles',
            'customsignupoutside',
            'topbaradditionalbtn',
            'topbaradditionalbtnurl',
            'editbtntop',
            'istab2active',
            'darkmodefirst',
            'customalertid',
            'customalert',
            'darkmodetheme',
            'footercustomcss',
            'showfooterbuttons',
            'showbasicinfolist',
            'footerblock1hr',
            'footerblock2hr',
            'showsociallist',
            'facebook',
            'twitter',
            'linkedin',
            'youtube',
            'instagram',
            'mobile',
            'mail',
            'cwebsiteurl',
            'ipcourseimage',
            'stringshowhidden',
            'stringshowonlyinprogress'
        ];

        foreach ($elements as $setting) {
            $templatecontext[$setting] = $this->$setting;
        }
        
        $elementshtml = [
            'stringmycourses',
            'seometadesc',
            'customalerthtml',
            'customstcontent',
            'customsmcontent',
            'customsfcontent',
            'customlogotxt',
            'topbarcustomhtml',
            'customnavitems',
            'sdarkmode',
            'slightmode',
            'stopbaradditionalbtn',
            'dashboardblock1',
            'dashboardblock2',
            'mycoursesblock1',
            'mycoursesblock2',
            'footerblock1',
            'footerblock2',
            'footerblock3',
            'footercopy',
            'block5slidesperrow',
            'customalertcontent',
            'website',
            'customsocialicon',
            'customfloatingbtn'
        ];

        foreach ($elementshtml as $setting) {
            $templatecontext[$setting] = format_text(($this->$setting), FORMAT_HTML, array('noclean' => true));
        }

        // Bespoke sidebar "Interest Zone" nav item (theme custom-item 1), so every
        // bespoke student page's sidebar can render it consistently.
        if (function_exists('theme_baz_interestzone_ctx')) {
            $templatecontext += theme_baz_interestzone_ctx();
        }

        // Support contact for the sidebar footer.
        //
        // The email and phone were reachable only through the floating "Need
        // help?" panel, which ships hidden behind a button and is rendered by
        // the student shells alone -- so staff had no route to support anywhere
        // in the UI, and students had to know to look. Put here rather than in
        // each renderer because all fifteen shells merge this context, which is
        // what lets one partial sit in every sidebar.
        //
        // Empty for a site that has published neither, and the partial then
        // renders nothing at all.
        if (function_exists('theme_baz_support_contact')) {
            $templatecontext['sidebarsupport'] =
                theme_baz_support_contact(get_string('help_sidebar_lead', 'theme_baz'));
            $templatecontext['hassidebarsupport'] = $templatecontext['sidebarsupport'] !== '';
        }

        // Tenant-neutral branding.
        //
        // The theme is shared by every tenant, so nothing user-facing may name a
        // specific institution. Several strings used to hardcode "JAIN Online",
        // which meant a second tenant rendered the first tenant's brand -- nitk
        // showed "Copyright (c) 2025 JAIN Online" on its own front page.
        //
        // brandname comes from the site's own fullname, so each tenant renders
        // itself with no per-tenant configuration. brandyear is the current year,
        // replacing a hardcoded "2025" that was already stale.
        //
        // function_exists guard matches the interestzone call above: lib.php is
        // not guaranteed to be loaded in every context that constructs this class.
        if (function_exists('theme_baz_brand_ctx')) {
            $templatecontext += theme_baz_brand_ctx();
        }

        // Staff shells are inert unless a site opts in; students are unaffected either way.
        $audience = \theme_baz\local\shell::audience();
        $templatecontext['shellaudience'] = $audience;
        $templatecontext['isstaffshell'] = \theme_baz\local\shell::is_staff();
        $templatecontext['staffnav'] = $templatecontext['isstaffshell']
            ? \theme_baz\local\shell::nav($audience) : [];

        return $templatecontext;
    }

    public function course_settings() {
        $templatecontext = [];

        $elements = [
            'coursetablayout',
            'cccteacherslist'
        ];

        foreach ($elements as $setting) {
            $templatecontext[$setting] = $this->$setting;
        }

        $elementshtml = [
            'titlecoursetab1',
            'titlecoursetab2',
            'titlecoursetab3',
            'titlecoursetab4',
            'titlecoursetab5',
            'coursetab1content',
            'coursetab2content',
            'coursetab3content',
            'coursetab4content',
            'coursetab5content'
        ];

        foreach ($elementshtml as $setting) {
            $templatecontext[$setting] = format_text(($this->$setting), FORMAT_HTML, array('noclean' => true));
        }

        return $templatecontext;
    }

}
