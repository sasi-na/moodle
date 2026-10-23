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

$page = new admin_settingpage('theme_baz_block6', get_string('settingsblock6', 'theme_baz'));

          $name = 'theme_baz/displayblock6';
          $title = get_string('turnon', 'theme_baz');
          $description = get_string('displayblock6_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/displayhrblock6';
          $title = get_string('displayblockhr', 'theme_baz');
          $description = get_string('displayblockhr_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block6class';
          $title = get_string('additionalclass', 'theme_baz');
          $description = get_string('additionalclass_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtext($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block6introtitle';
          $title = get_string('blockintrotitle', 'theme_baz');
          $description = get_string('blockintrotitle_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block6introcontent';
          $title = get_string('blockintrocontent', 'theme_baz');
          $description = get_string('blockintrocontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block6htmlcontent';
          $title = get_string('blockhtmlcontent', 'theme_baz');
          $description = get_string('blockhtmlcontent_desc', 'theme_baz');
          $default = '<div class="rui-card-testimonials-grid wrapper-xxl"><!-- Start item -->
  <div class="rui-block-testimonials-item">
    <div class="rui-block-testimonials-rating"> </div>
    <div class="rui-block-testimonials--quote">"Everything you need and nothing
      you do not"</div>
    <h4 class="rui-block-testimonials--author">Ryan Cook</h4>
    <span class="rui-block-testimonials--additional">CEO | SoSimple</span>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-testimonials-item">
    <div class="rui-block-testimonials-rating"> </div>
    <div class="rui-block-testimonials--quote">“I could not imagine using
      anything else!”</div>
    <h4 class="rui-block-testimonials--author">Tim Smith</h4>
    <span class="rui-block-testimonials--additional">UX Designer |
      Clean&amp;Simple</span>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-block-testimonials-item">
    <div class="rui-block-testimonials-rating"> </div>
    <div class="rui-block-testimonials--quote">"One of the best Moodle Theme
      I have ever used”</div>
    <h4 class="rui-block-testimonials--author">Anna van Diesel</h4>
    <span class="rui-block-testimonials--additional">Senior Product Designer |
      ABC Design</span>
  </div>
  <!-- End item -->
</div>';
          $setting = new baz_setting_confightmleditor($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block6footercontent';
          $title = get_string('blockfootercontent', 'theme_baz');
          $description = get_string('blockfootercontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

$settings->add($page);