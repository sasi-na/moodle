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

$page = new admin_settingpage('theme_baz_block5', get_string('settingsblock5', 'theme_baz'));

          $name = 'theme_baz/displayblock5';
          $title = get_string('turnon', 'theme_baz');
          $description = get_string('displayblock5_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/displayhrblock5';
          $title = get_string('displayblockhr', 'theme_baz');
          $description = get_string('displayblockhr_desc', 'theme_baz');
          $default = 1;
          $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block5class';
          $title = get_string('additionalclass', 'theme_baz');
          $description = get_string('additionalclass_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtext($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block5introtitle';
          $title = get_string('blockintrotitle', 'theme_baz');
          $description = get_string('blockintrotitle_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block5introcontent';
          $title = get_string('blockintrocontent', 'theme_baz');
          $description = get_string('blockintrocontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block5htmlcontent';
          $title = get_string('blockhtmlcontent', 'theme_baz');
          $description = get_string('blockhtmlcontent_desc', 'theme_baz');
          $default = '<div class="wrapper-md rui-block-desc rui-block-desc--lg">Custom block no.5 allows you to display multiple logotypes. It prepares them to thrive in today’s world — and to shape tomorrow’s. Apple is constantly creating resources to help educators do just that. Not only powerful products, but also tools, inspiration, and curricula to create magical learning experiences and make every moment of screen time worth it.</div>';
          $setting = new baz_setting_confightmleditor($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block5footercontent';
          $title = get_string('blockfootercontent', 'theme_baz');
          $description = get_string('blockfootercontent_desc', 'theme_baz');
          $default = '';
          $setting = new admin_setting_configtextarea($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block5slidesperrow';
          $title = get_string('block5slidesperrow', 'theme_baz');
          $description = get_string('block5slidesperrow_desc', 'theme_baz');
          $default = '5';
          $setting = new admin_setting_configtext($name, $title, $description, $default);
          $page->add($setting);

          $name = 'theme_baz/block5count';
          $title = get_string('block5count', 'theme_baz');
          $description = get_string('block5count_desc', 'theme_baz');
          $default = 5;
          $options = array();
          for ($i = 1; $i <= 30; $i++) {
                    $options[$i] = $i;
          }
          $setting = new admin_setting_configselect($name, $title, $description, $default, $options);
          $setting->set_updatedcallback('theme_reset_all_caches');
          $page->add($setting);

          // If we don't have an slide yet, default to the preset.
          $slidercount = get_config('theme_baz', 'block5count');

          if (!$slidercount) {
                    $slidercount = 1;
          }

          for ($sliderindex = 1; $sliderindex <= $slidercount; $sliderindex++) {
                    $name = 'theme_baz/hblock5item' . $sliderindex;
                    $heading = get_string('hblock5item', 'theme_baz');
                    $setting = new admin_setting_heading($name, '<span class="rui-admin-no">'. $sliderindex . '</span>'. $heading, format_text(get_string('hblock5item_desc', 'theme_baz'), FORMAT_MARKDOWN));
                    $page->add($setting);

                    $fileid = 'block5itemimg' . $sliderindex;
                    $name = 'theme_baz/block5itemimg' . $sliderindex;
                    $title = get_string('block5itemimg', 'theme_baz');
                    $description = get_string('block5itemimg_desc', 'theme_baz');
                    $opts = array('accepted_types' => array('.png', '.jpg', '.gif', '.webp', '.tiff', '.svg'), 'maxfiles' => 1);
                    $setting = new admin_setting_configstoredfile($name, '<span class="rui-admin-no">'. $sliderindex . '</span>'. $title, $description, $fileid, 0, $opts);
                    $setting->set_updatedcallback('theme_reset_all_caches');
                    $page->add($setting);

                    $name = 'theme_baz/block5itemtitle' . $sliderindex;
                    $title = get_string('block5itemtitle', 'theme_baz');
                    $description = get_string('block5itemtitle_desc', 'theme_baz');
                    $setting = new admin_setting_configtext($name, '<span class="rui-admin-no">'. $sliderindex . '</span>'. $title, $description, '', PARAM_TEXT);
                    $page->add($setting);

                    $name = 'theme_baz/block5itemurl' . $sliderindex;
                    $title = get_string('block5itemurl', 'theme_baz');
                    $description = get_string('block5itemurl_desc', 'theme_baz');
                    $default = '';
                    $setting = new admin_setting_configtext($name, '<span class="rui-admin-no">'. $sliderindex . '</span>'. $title, $description, $default);
                    $page->add($setting);
          }

$settings->add($page);