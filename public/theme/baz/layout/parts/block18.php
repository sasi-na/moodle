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
 * @copyright Copyright © 2021 onwards Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */

defined('MOODLE_INTERNAL') || die();

global $PAGE, $OUTPUT;

// Variables - Settings
$block18introtitle = format_text(theme_baz_get_setting('block18introtitle'),FORMAT_HTML, array('noclean' => true));
$block18introcontent = format_text(theme_baz_get_setting('block18introcontent'),FORMAT_HTML, array('noclean' => true));
$block18html = format_text(theme_baz_get_setting('block18htmlcontent'),FORMAT_HTML, array('noclean' => true));
$block18footer = format_text(theme_baz_get_setting('block18footercontent'),FORMAT_HTML, array('noclean' => true));
$block18class = theme_baz_get_setting('block18class');

echo '<!-- Start Block 18 -->';
echo '<div class="rui-fp-block--18 wrapper-md '.$block18class.'">';
          echo '<div class="display-3">';
                    echo $block18html;
          echo '</div>';
          if(!empty($block18footer)) {
          echo '<div class="rui-block-footer wrapper-md">'.$block18footer.'</div>';
          }
echo '</div>';
if(theme_baz_get_setting("displayhrblock18") == '1') {
          echo '<hr class="rui-block-hr wrapper-md" />';
}
echo '<!-- End Block 18 -->';