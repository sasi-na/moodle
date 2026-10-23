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

$page = new admin_settingpage('theme_baz_block8', get_string('settingsblock8', 'theme_baz'));

          $name = 'theme_baz/displayblock8';
          $title = get_string('turnon', 'theme_baz');
          $description = get_string('displayblock8_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/displayhrblock8';
          $title = get_string('displayblockhr', 'theme_baz');
          $description = get_string('displayblockhr_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block8class';
          $title = get_string('additionalclass', 'theme_baz');
          $description = get_string('additionalclass_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtext($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block8introtitle';
          $title = get_string('blockintrotitle', 'theme_baz');
          $description = get_string('blockintrotitle_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block8introcontent';
          $title = get_string('blockintrocontent', 'theme_baz');
          $description = get_string('blockintrocontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block8htmlcontent';
          $title = get_string('blockhtmlcontent', 'theme_baz');
          $description = get_string('blockhtmlcontent_desc', 'theme_baz');
          $default = '<div class="row row-cols-1 row-cols-lg-2 mx-0"><!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card d-block d-md-inline-flex align-items-center border-0"
      style="background: #eeead4;">
      <div class="rui-card-item-img rui-img--rounded-fluid mr-md-2"><img
          src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/download.jpg"
          alt="pic 1" width="400" height="400"></div>
      <div class="rui-card-body mt-3 mt-md-0 col">
        <h3 class="rui-card-title">Industry expert teachers</h3>
        <p class="rui-card-text">With supporting text below as a natural lead-in
          to additional content.</p>
        <a class="btn btn-sm btn-dark"
          href="https://roseathemes.com/baz/1/course/">Learn more about
          theme</a>
      </div>
    </div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card d-block d-md-inline-flex align-items-center border-0"
      style="background: #c1d3d3;">
      <div class="rui-card-item-img rui-img--rounded-fluid mr-md-2"><img
          src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/sq-2.jpg"
          alt="pic 2" width="400" height="400"></div>
      <div class="rui-card-body mt-3 mt-md-0 col">
        <h3 class="rui-card-title">Students community</h3>
        <p class="rui-card-text">With supporting text below as a natural lead-in
          to additional content.</p>
        <a class="btn btn-sm btn-dark"
          href="https://roseathemes.com/baz/1/course/">Documentation</a>
      </div>
    </div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card d-block d-md-inline-flex align-items-center border-0"
      style="background: #dcd5d9;">
      <div class="rui-card-item-img rui-img--rounded-fluid mr-md-2"><img
          src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/sq-3.jpg"
          alt="pic 3" width="400" height="400"></div>
      <div class="rui-card-body mt-3 mt-md-0 col">
        <h3 class="rui-card-title">Up-to-date course content</h3>
        <p class="rui-card-text">With supporting text below as a natural lead-in
          to additional content.</p>
        <a class="btn btn-sm btn-dark"
          href="https://roseathemes.com/baz/1/course/">All categories</a>
      </div>
    </div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card d-block d-md-inline-flex align-items-center border-0"
      style="background: #cdd6c1;">
      <div class="rui-card-item-img rui-img--rounded-fluid mr-md-2"><img
          src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/sq-5.jpg"
          alt="pic 4" width="400" height="400"></div>
      <div class="rui-card-body mt-3 mt-md-0 col">
        <h3 class="rui-card-title">Special title treatment</h3>
        <p class="rui-card-text">With supporting text below as a natural lead-in
          to additional content.</p>
        <a class="btn btn-sm btn-dark"
          href="https://roseathemes.com/baz/1/course/">Learn more about
          theme</a>
      </div>
    </div>
  </div>
  <!-- End item -->
</div>';
          $setting = new baz_setting_confightmleditor($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block8footercontent';
          $title = get_string('blockfootercontent', 'theme_baz');
          $description = get_string('blockfootercontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

$settings->add($page);