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

$page = new admin_settingpage('theme_baz_block10', get_string('settingsblock10', 'theme_baz'));

$name = 'theme_baz/displayblock10';
$title = get_string('turnon', 'theme_baz');
$description = get_string('displayblock10_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/displayhrblock10';
$title = get_string('displayblockhr', 'theme_baz');
$description = get_string('displayblockhr_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block10class';
$title = get_string('additionalclass', 'theme_baz');
$description = get_string('additionalclass_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block10introtitle';
$title = get_string('blockintrotitle', 'theme_baz');
$description = get_string('blockintrotitle_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block10introcontent';
$title = get_string('blockintrocontent', 'theme_baz');
$description = get_string('blockintrocontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block10htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_baz');
$description = get_string('blockhtmlcontent_desc', 'theme_baz');
$default = '<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 mx-0"><!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card h-100"><a href="https://rosea.io">
        <div class="rui-img--rounded-fluid"><img
            src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/img-1.jpg"
            alt="img 1" width="400" height="225"></div>
      </a>
      <div class="rui-card-body">
        <h4 class="lead-4 mt-3">Get real-time insights on your performance.</h4>
        <p class="rui-card-text">Auto-generated reports: Get accurate insights
          on your performance with auto-generated reports that help create
          successful campaigns.</p>
        <ul class="rui-special-list rui-special-list--light">
          <li>Personal asset watchlists</li>
          <li>Curated market data feed</li>
          <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
        </ul>
        <a class="mt-2 btn btn-secondary w-100" href="https://rosea.io">Learn
          more about the Smart Keyboard</a>
      </div>
    </div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card h-100"><a href="https://rosea.io">
        <div class="rui-img--rounded-fluid"><img
            src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/img-2.jpg"
            alt="img 2" width="400" height="225"></div>
      </a>
      <div class="rui-card-body">
        <h4 class="lead-4 mt-3">Get real-time insights on your performance.</h4>
        <p class="rui-card-text">Auto-generated reports: Get accurate insights
          on your performance with auto-generated reports that help create
          successful campaigns.</p>
        <ul class="rui-special-list rui-special-list--light">
          <li>Personal asset watchlists</li>
          <li>Curated market data feed</li>
          <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
        </ul>
        <a class="mt-2 btn btn-secondary w-100" href="https://rosea.io">Learn
          more about the Smart Keyboard</a>
      </div>
    </div>
  </div>
  <!-- End item --> <!-- Start item -->
  <div class="rui-card-item col mb-4">
    <div class="rui-card h-100"><a href="https://rosea.io">
        <div class="rui-img--rounded-fluid"><img
            src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/img-4.jpg"
            alt="img 3" width="400" height="225"></div>
      </a>
      <div class="rui-card-body">
        <h4 class="lead-4 mt-3">Get real-time insights on your performance.</h4>
        <p class="rui-card-text">Auto-generated reports: Get accurate insights
          on your performance with auto-generated reports that help create
          successful campaigns.</p>
        <ul class="rui-special-list rui-special-list--light">
          <li>Personal asset watchlists</li>
          <li>Curated market data feed</li>
          <li class="list-icon-x">Sansheets plugin with pre-made templates</li>
        </ul>
        <a class="mt-2 btn btn-secondary w-100" href="https://rosea.io">Learn
          more about the Smart Keyboard</a>
      </div>
    </div>
  </div>
  <!-- End item -->
</div>';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);


$name = 'theme_baz/block10footercontent';
$title = get_string('blockfootercontent', 'theme_baz');
$description = get_string('blockfootercontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
