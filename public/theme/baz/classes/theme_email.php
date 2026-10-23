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
 *
 * @package   theme_baz
 * @copyright 2022 - 2024 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

class theme_email {

    public function get_context() {
        global $CFG;

        $context = [];
        $context['theme'] = [];
        $context['theme']['css'] = $this->get_css('baz');
        $context['theme']['logo'] = $this->get_logo('baz');
        $context['theme']['typography'] = $this->get_typography('baz');
        $context['theme']['footer'] = $this->get_footer('baz');

        return $context;
    }

    // CSS.
    private function get_css('baz') {
        global $CFG;

        $output = '';
        $output = file_get_contents($CFG->httpswwwroot . '/pluginfile.php/1/theme_baz/customstyles/email.css');

        return $output;
    }

    // Logo.
    private function get_logo('baz') {
        global $CFG;

        $output = [];

        $output['url'] = $this->get_logo_file('baz');
        $output['height'] = get_config('theme_baz', 'email_logo_height');
        $output['alt'] = get_config('theme_baz', 'email_logo_alt');
        $output['href'] = get_config('theme_baz', 'email_logo_href');
        $output['hashref'] = (!empty($output['href']) ? true : false);

        return $output;
    }

    private function get_logo_file('baz') {
        global $CFG;

        $output = '';

        if (!empty(get_config('theme_baz', 'email_logo_file'))) {
            $output = $this->http_or_https() . theme_config::load('baz')->setting_file_url('email_logo_file', 'email_logo_file');
        } else {
            $output = $this->http_or_https() . theme_config::load('baz')->setting_file_url('customlogo', 'customlogo');
        }

        return $output;
    }

    // Typography.
    private function get_typography('baz') {
        $output = [];

        if (get_config('theme_baz', 'email_typo_enable')) {
            $output['fontfamily'] = 'font-family:' . get_config('theme_baz', 'email_typo_fontfamily') . ';';

            $output['text']['color'] = 'color:' . get_config('theme_baz', 'email_typo_text_color') . ';';
            $output['text']['fontsize'] = 'font-size:' . get_config('theme_baz', 'email_typo_text_fontsize') . ';';

            $output['link']['color'] = 'color:' . get_config('theme_baz', 'email_typo_link_color') . ';';

            $output['footer']['color'] = 'color:' . get_config('theme_baz', 'email_typo_footer_color') . ';';
            $output['footer']['fontsize'] = 'font-size:' . get_config('theme_baz', 'email_typo_footer_fontsize') . ';';
        }

        return $output;
    }

    // Footer.
    private function get_footer('baz') {
        $output = [];

        $output['text'] = get_config('theme_baz', 'email_footer_text');
        $output['copyright'] = get_config('theme_baz', 'email_footer_copyright');
        $output['displaylogo'] = get_config('theme_baz', 'email_footer_displaylogo');

        return $output;
    }

    // Tools.
    private function http_or_https() {
        global $CFG;

        $output = 'http:';

        if (strpos($CFG->wwwroot, 'https:') !== false) {
            $output = 'https:';
        }

        return $output;
    }

}
