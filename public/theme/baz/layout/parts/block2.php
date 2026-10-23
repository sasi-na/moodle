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
$block2introtitle = format_text(theme_baz_get_setting('block2introtitle'),FORMAT_HTML, array('noclean' => true));
$block2introcontent = format_text(theme_baz_get_setting('block2introcontent'),FORMAT_HTML, array('noclean' => true));
$block2html = format_text(theme_baz_get_setting('block2htmlcontent'),FORMAT_HTML, array('noclean' => true));
$block2footer = format_text(theme_baz_get_setting('block2footercontent'),FORMAT_HTML, array('noclean' => true));
$block2class = theme_baz_get_setting('block2class');

echo '<!-- Start Block 2 -->';
echo '<div class="rui-fp-block--2 '.$block2class.'">';

          if(!empty($block2introtitle) || !empty($block2introcontent)) {
          echo '<div class="wrapper-md">';
          }

          if(!empty($block2introtitle)) {
                    echo '<h3 class="rui-block-title">'.$block2introtitle.'</h3>';
          }
          if(!empty($block2introcontent)) {
                    echo '<div class="rui-block-desc">'.$block2introcontent.'</div>';
          }
          if(!empty($block2introtitle) || !empty($block2introcontent)) {
          echo '</div>';
          }

          echo $block2html;

          if(!empty($block2footer)) {
          echo '<div class="rui-block-footer wrapper-md">'.$block2footer.'</div>';
          }

echo '</div>';

if(theme_baz_get_setting("displayhrblock2") == '1') {
          echo '<hr class="rui-block-hr wrapper-md" />';
}

echo '<!-- End Block 2 -->';