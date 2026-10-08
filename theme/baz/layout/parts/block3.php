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
$block3introtitle = format_text(theme_baz_get_setting('block3introtitle'),FORMAT_HTML, array('noclean' => true));
$block3introcontent = format_text(theme_baz_get_setting('block3introcontent'),FORMAT_HTML, array('noclean' => true));
$block3html = format_text(theme_baz_get_setting('block3htmlcontent'),FORMAT_HTML, array('noclean' => true));
$block3footer = format_text(theme_baz_get_setting('block3footercontent'),FORMAT_HTML, array('noclean' => true));
$block3class = theme_baz_get_setting('block3class');

echo '<!-- Start Block 3-->';
echo '<div class="rui-fp-block--3 '.$block3class.'">';
          if(!empty($block3introtitle) || !empty($block3introcontent)) {
          echo '<div class="wrapper-md">';
          }
          if(!empty($block3introtitle)) {
                    echo '<h3 class="rui-block-title">'.$block3introtitle.'</h3>';
          }
          if(!empty($block3introcontent)) {
                    echo '<div class="rui-block-desc">'.$block3introcontent.'</div>';
          }
          if(!empty($block3introtitle) || !empty($block3introcontent)) {
          echo '</div>';
          }
          echo $block3html;

          if(!empty($block3footer)) {
          echo '<div class="rui-block-footer wrapper-md">'.$block3footer.'</div>';
          }
echo '</div>';
if(theme_baz_get_setting("displayhrblock3") == '1') {
          echo '<hr class="rui-block-hr wrapper-md" />';
}
echo '<!-- End Block 3 -->';