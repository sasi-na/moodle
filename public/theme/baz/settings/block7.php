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

$page = new admin_settingpage('theme_baz_block7', get_string('settingsblock7', 'theme_baz'));

          $name = 'theme_baz/displayblock7';
          $title = get_string('turnon', 'theme_baz');
          $description = get_string('displayblock7_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/displayhrblock7';
          $title = get_string('displayblockhr', 'theme_baz');
          $description = get_string('displayblockhr_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block7class';
          $title = get_string('additionalclass', 'theme_baz');
          $description = get_string('additionalclass_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtext($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block7introtitle';
          $title = get_string('blockintrotitle', 'theme_baz');
          $description = get_string('blockintrotitle_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block7introcontent';
          $title = get_string('blockintrocontent', 'theme_baz');
          $description = get_string('blockintrocontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block7htmlcontent';
          $title = get_string('blockhtmlcontent', 'theme_baz');
          $description = get_string('blockhtmlcontent_desc', 'theme_baz');
          $default = '<div class="row row-cols-1 row-cols-lg-2 mx-0"><!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card rui-card--with-img">
      <div class="rui-card-body ml-md-4">
        <h3 class="rui-card-title">Packed with features</h3>
        <p class="rui-card-text">With supporting text below as a natural lead-in
          to additional content.</p>
        <a class="btn btn-sm btn-secondary" href="https://rosea.io">Learn more
          about theme</a>
      </div>
      <div class="ml-md-4 rui-img--rounded-fluid"><img
          src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/img-3d-1-1.png"
          alt="img #1" width="300" height="300"></div>
    </div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card rui-card--with-img">
      <div class="rui-card-body ml-md-4">
        <h3 class="rui-card-title">Everything you need for your University
          website</h3>
        <p class="rui-card-text">With supporting text below as a natural lead-in
          to additional content.</p>
        <a class="btn btn-sm btn-secondary" href="https://rosea.io">Learn more
          about Moodle</a>
      </div>
      <div class="ml-md-4 rui-img--rounded-fluid"><img
          src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/img-3d-3-1.png"
          alt="img #1" width="300" height="300"></div>
    </div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card rui-card--with-img">
      <div class="rui-card-body ml-md-4">
        <h3 class="rui-card-title">Get started. It’s easy</h3>
        <p class="rui-card-text">With supporting text below as a natural lead-in
          to additional content.</p>
        <a class="btn btn-sm btn-secondary" href="https://rosea.io">Video
          tutorials</a>
      </div>
      <div class="ml-md-4 rui-img--rounded-fluid"><img
          src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/img-3d-4-1.png"
          alt="img #1" width="300" height="300"></div>
    </div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card rui-card--with-img">
      <div class="rui-card-body ml-md-4">
        <h3 class="rui-card-title">Start with components</h3>
        <p class="rui-card-text">With supporting text below as a natural lead-in
          to additional content.</p>
        <a class="btn btn-sm btn-secondary" href="https://rosea.io">Check out
          code snippets</a>
      </div>
      <div class="ml-md-4 rui-img--rounded-fluid"><img
          src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/img-3d-2-1.png"
          alt="img #1" width="300" height="300"></div>
    </div>
  </div>
  <!-- End item -->
</div>';
          $setting = new baz_setting_confightmleditor($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block7footercontent';
          $title = get_string('blockfootercontent', 'theme_baz');
          $description = get_string('blockfootercontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

$settings->add($page);