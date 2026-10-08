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

$page = new admin_settingpage('theme_baz_block15', get_string('settingsblock15', 'theme_baz'));

$name = 'theme_baz/displayblock15';
$title = get_string('turnon', 'theme_baz');
$description = get_string('displayblock15_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/displayhrblock15';
$title = get_string('displayblockhr', 'theme_baz');
$description = get_string('displayblockhr_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block15class';
$title = get_string('additionalclass', 'theme_baz');
$description = get_string('additionalclass_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block15introtitle';
$title = get_string('blockintrotitle', 'theme_baz');
$description = get_string('blockintrotitle_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block15introcontent';
$title = get_string('blockintrocontent', 'theme_baz');
$description = get_string('blockintrocontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block15htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_baz');
$description = get_string('blockhtmlcontent_desc', 'theme_baz');
$default = '<div class="wrapper-xxl"><!-- Start item -->
  <div class="row my-5">
    <div class="col-xs-12 col-md-6 rui-img--rounded-fluid"><img
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/simg-1.jpeg"
        alt="pic #1" width="600" height="472"></div>
    <div
      class="col-xs-12 col-md-6 d-flex flex-wrap align-content-center px-lg-6">
      <div>
        <h4 class="lead-2 my-3">BAZ makes it easy to create your e-learning
          website based on Moodle 3.11 and later.</h4>
        <p>Lorem ipsum was purposefully designed to have no meaning, but appear
          like real text, making it the perfect placeholder.</p>
        <a class="btn btn-sm btn-secondary mt-3"
          href="https://roseathemes.com/baz/1/course/">Explore theme UI </a>
      </div>
    </div>
  </div>
  <!-- End item -->
  <hr class="hr-small"><!-- Start item -->
  <div class="row my-5">
    <div
      class="col-xs-12 col-md-6 d-flex flex-wrap align-content-center px-lg-6">
      <div>
        <h4 class="lead-2 my-3">Take full control of theme with the full access
          to all theme files and dozens of settings.</h4>
        <p>According to McClintock, a 15th century typesetter likely scrambled
          part of Ciceros De Finibus in order to provide placeholder text...
        </p>
        <a class="btn btn-sm btn-secondary my-3"
          href="https://roseathemes.com/baz/1/course/">Learn more about
          theme</a>
      </div>
    </div>
    <div class="col-xs-12 col-md-6 rui-img--rounded-fluid"><img
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/simg-2.png"
        alt="pic #2" width="600" height="472"></div>
  </div>
  <!-- End item -->
  <hr class="hr-small"><!-- Start item -->
  <div class="row my-5">
    <div class="col-xs-12 col-md-6 rui-img--rounded-fluid"><img
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/simg-3.jpeg"
        alt="pic #4" width="600" height="472"></div>
    <div
      class="col-xs-12 col-md-6 d-flex flex-wrap align-content-center px-lg-6">
      <div>
        <h4 class="lead-2 my-3">Lifetime updates!</h4>
        <p>And anyways, as Cecil Adams reasoned, “[Do you really] think graphic
          arts supply houses were hiring classics scholars in the 1960s?”
          Perhaps. But it seems reasonable to imagine that there was a version
          in use far before the age of Letraset.</p>
        <a class="btn btn-sm btn-secondary my-3"
          href="https://roseathemes.com/baz/1/course/">Available courses</a>
      </div>
    </div>
  </div>
  <!-- End item -->
</div>';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);


$name = 'theme_baz/block15footercontent';
$title = get_string('blockfootercontent', 'theme_baz');
$description = get_string('blockfootercontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
