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
