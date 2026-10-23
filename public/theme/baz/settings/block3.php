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

$page = new admin_settingpage('theme_baz_block3', get_string('settingsblock3', 'theme_baz'));

$name = 'theme_baz/displayblock3';
$title = get_string('turnon', 'theme_baz');
$description = get_string('displayblock3_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/displayhrblock3';
$title = get_string('displayblockhr', 'theme_baz');
$description = get_string('displayblockhr_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block3class';
$title = get_string('additionalclass', 'theme_baz');
$description = get_string('additionalclass_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block3introtitle';
$title = get_string('blockintrotitle', 'theme_baz');
$description = get_string('blockintrotitle_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block3introcontent';
$title = get_string('blockintrocontent', 'theme_baz');
$description = get_string('blockintrocontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block3htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_baz');
$description = get_string('blockhtmlcontent_desc', 'theme_baz');
$default = '<div class="rui-card-team-grid wrapper-xxl"><!-- Start item -->
  <div class="rui-block-team-item"><img
      class="img-fluid atto_image_button_middle"
      src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/t-4.jpeg"
      alt="Team #1" width="520" height="770">
    <h4 class="rui-block-team-title">Christa McAuliffe</h4>
    <div class="rui-block-team-desc">CEO</div>
    <div class="rui-block-team-desc--sm">Since 2009</div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-team-item"><img
      class="img-fluid atto_image_button_text-bottom"
      src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/team-5.jpeg"
      alt="ku" width="520" height="770">
    <h4 class="rui-block-team-title">Helen Keller</h4>
    <div class="rui-block-team-desc">Senior Vice President Services</div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-team-item"><img
      class="img-fluid atto_image_button_text-bottom"
      src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/team-8.jpeg"
      alt="team #3" width="520" height="770">
    <h4 class="rui-block-team-title">Mark Twain</h4>
    <div class="rui-block-team-desc">Senior Vice President Machine Learning and
      AI Strategy</div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-team-item"><img
      class="img-fluid atto_image_button_text-bottom"
      src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/t-1.jpeg"
      alt="Team #4" width="520" height="770">
    <h4 class="rui-block-team-title">William Holmes McGuffey</h4>
    <div class="rui-block-team-desc">Sales &amp; Customer Success Enablement
      Manager</div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-team-item"><img
      class="img-fluid atto_image_button_text-bottom"
      src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/team-7.png"
      alt="Team #5" width="520" height="770">
    <h4 class="rui-block-team-title">Anton Makarenko</h4>
    <div class="rui-block-team-desc">Manager, Technical Recruiting<br>(Product
      &amp; Data)</div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-team-item"><img
      class="img-fluid atto_image_button_text-bottom"
      src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/team-9.jpeg"
      alt="Team #6
" width="520" height="770">
    <h4 class="rui-block-team-title">John Dewey</h4>
    <div class="rui-block-team-desc">Product Manager, Risk &amp; Payments</div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-team-item"><img
      class="img-fluid atto_image_button_text-bottom"
      src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/t-6.jpeg"
      alt="Team #7" width="520" height="770">
    <h4 class="rui-block-team-title">Eve Johnson</h4>
    <div class="rui-block-team-desc">Social Media Manager</div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-team-item"><img
      class="img-fluid atto_image_button_text-bottom"
      src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/team-6.jpeg"
      alt="Team #8" width="520" height="770">
    <h4 class="rui-block-team-title">Caroline van Diesel</h4>
    <div class="rui-block-team-desc">Advanced Technical Support Lead</div>
  </div>
  <!-- End item -->
</div>';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block3footercontent';
$title = get_string('blockfootercontent', 'theme_baz');
$description = get_string('blockfootercontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
