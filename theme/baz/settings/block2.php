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

$page = new admin_settingpage('theme_baz_block2', get_string('settingsblock2', 'theme_baz'));

$name = 'theme_baz/displayblock2';
$title = get_string('turnon', 'theme_baz');
$description = get_string('displayblock2_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/displayhrblock2';
$title = get_string('displayblockhr', 'theme_baz');
$description = get_string('displayblockhr_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);


$name = 'theme_baz/block2class';
$title = get_string('additionalclass', 'theme_baz');
$description = get_string('additionalclass_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block2introtitle';
$title = get_string('blockintrotitle', 'theme_baz');
$description = get_string('blockintrotitle_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block2introcontent';
$title = get_string('blockintrocontent', 'theme_baz');
$description = get_string('blockintrocontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block2htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_baz');
$description = get_string('blockhtmlcontent_desc', 'theme_baz');
$default = '<div class="rui-card-cat-grid mx-0"><!-- Start item --><a
    class="rui-block-category-item"
    href="https://roseathemes.com/baz/1/course/">
    <div class="badge-wrapper w-100"><span
        class="badge-sm badge-soon">-50%</span></div>
    <div class="d-block"><img class="img-fluid atto_image_button_text-bottom"
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/icon1.png"
        alt="Icon #1" width="80" height="80"></div>
    <h3 class="rui-block-category-item-title">Art History</h3>
    <span class="rui-block-category-item-subtitle">3 courses</span>
  </a><!-- End item --> <!-- Start item --><a class="rui-block-category-item"
    href="https://roseathemes.com/baz/1/course/">
    <div class="badge-wrapper w-100"><span
        class="badge-sm badge-soon">Soon</span></div>
    <div class="d-block"><img class="img-fluid atto_image_button_text-bottom"
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/icon2.png"
        alt="Icon #2
" width="80" height="80"></div>
    <h3 class="rui-block-category-item-title">UI &amp; UX</h3>
    <span class="rui-block-category-item-subtitle">16 courses</span>
  </a><!-- End item --> <!-- Start item --><a class="rui-block-category-item"
    href="https://roseathemes.com/baz/1/course/">
    <div class="d-block"><img class="img-fluid atto_image_button_text-bottom"
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/icon3.png"
        alt="Icon #3" width="80" height="80"></div>
    <h3 class="rui-block-category-item-title">3D Design</h3>
    <span class="rui-block-category-item-subtitle">4 courses</span>
  </a><!-- End item --> <!-- Start item --><a class="rui-block-category-item"
    href="https://roseathemes.com/baz/1/course/">
    <div class="d-block"><img class="img-fluid atto_image_button_text-bottom"
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/icon4.png"
        alt="Icon #4" width="80" height="80"></div>
    <h3 class="rui-block-category-item-title">Media &amp; PR</h3>
    <span class="rui-block-category-item-subtitle">9 courses</span>
  </a><!-- End item --> <!-- Start item --><a class="rui-block-category-item"
    href="https://roseathemes.com/baz/1/course/">
    <div class="badge-wrapper w-100"><span class="badge-sm badge-new">New</span>
    </div>
    <div class="d-block"><img class="img-fluid atto_image_button_text-bottom"
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/icon5.png"
        alt="Icon #5" width="80" height="80"></div>
    <h3 class="rui-block-category-item-title">Programming</h3>
    <span class="rui-block-category-item-subtitle">2 courses</span>
  </a><!-- End item --> <!-- Start item --><a class="rui-block-category-item"
    href="https://roseathemes.com/baz/1/course/">
    <div class="d-block"><img class="img-fluid atto_image_button_text-bottom"
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/icon6.png"
        alt="Icon #6" width="80" height="80"></div>
    <h3 class="rui-block-category-item-title">Music</h3>
    <span class="rui-block-category-item-subtitle">6 courses</span>
  </a><!-- End item --> <!-- Start item --><a class="rui-block-category-item"
    href="https://roseathemes.com/baz/1/course/">
    <div class="d-block"><img class="img-fluid atto_image_button_text-bottom"
        src="https://roseathemes.com/baz/1/pluginfile.php/1/theme_baz/bazsettingsimgs/0/icon8.png"
        alt="Icon #7" width="80" height="80"></div>
    <h3 class="rui-block-category-item-title">Language courses</h3>
    <span class="rui-block-category-item-subtitle">3 courses</span>
  </a><!-- End item --></div>';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/block2footercontent';
$title = get_string('blockfootercontent', 'theme_baz');
$description = get_string('blockfootercontent_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
