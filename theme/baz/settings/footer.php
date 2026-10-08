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


$page = new admin_settingpage('theme_baz_settingsfooter', get_string('settingsfooter', 'theme_baz'));

$name = 'theme_baz/showfooterbuttons';
$title = get_string('showfooterbuttons', 'theme_baz');
$description = get_string('showfooterbuttons_desc', 'theme_baz');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/footerblock1';
$title = get_string('footerblock1', 'theme_baz');
$description = get_string('footerblock1_desc', 'theme_baz');
$default = '<p><a href="#" onclick="window.open(\'/local/ticketing/create_ticket.php\', \'_blank\'); return false;"><i class="fa fa-ticket"></i> Click here to raise a ticket</a></p>';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/footerblock2';
$title = get_string('footerblock2', 'theme_baz');
$description = get_string('footerblock2_desc', 'theme_baz');
$default = '<div class="rui-footer-nav row text-sm-center text-md-left my-5">
        <div class="col-sm-12 col-md-6 col-lg my-sm-2 my-md-0">
            <h5 class="rui-footer-nav-title">Company</h5>
            <ul class="rui-footer-nav-items list-unstyled mb-0">
                <li> <a href="#">About Us</a> </li>
                <li> <a href="#">Blog</a><span class="badge-xs badge-success ml-2">New</span> </li>
                <li> <a href="#">FAQ</a> </li>
                <li> <a href="#">Contact</a> </li>
                <li> <a href="#">Help</a> </li>
            </ul>
        </div>
        <div class="col-sm-12 col-md-6 col-lg my-sm-2 my-md-0">
            <h5 class="rui-footer-nav-title">Products</h5>
            <ul class="rui-footer-nav-items list-unstyled mb-0">
                <li> <a href="#">Parents</a> </li>
                <li> <a href="#">Schools</a> </li>
                <li> <a href="#">Partners</a> </li>
            </ul>
        </div>
        <div class="col-sm-12 col-md-6 col-lg my-sm-2 my-md-0">
            <h5 class="rui-footer-nav-title">Legal</h5>
            <ul class="rui-footer-nav-items list-unstyled mb-0">
                <li> <a href="#">Privacy Policy</a> </li>
                <li> <a href="#">Terms of Service</a> </li>
            </ul>
        </div>
        <div class="col-sm-12 col-md-6 col-lg my-sm-2 my-md-0">
            <h5 class="rui-footer-nav-title">Office</h5>
            <ul class="rui-footer-nav-items list-unstyled mb-0">
                <li>
                    <p> Victoria Garden City, Lagos </p>
                </li>
                <li> 1234 Fruitvale Avenue, Oakland, Califonia, USA. </li>
            </ul>
        </div>
    </div>';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/footerblock3';
$title = get_string('footerblock3', 'theme_baz');
$description = get_string('footerblock3_desc', 'theme_baz');
$default = 'Get this theme exclusively on the ThemeForest: More premium moodle themes: <a href="https://rosea.io">rosea.io</a>';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_baz/footercopy';
$title = get_string('footercopy', 'theme_baz');
$description = get_string('footercopy_desc', 'theme_baz');
$default = '<p><strong>Copyright © 2020 baz Moodle Theme. All right reserved.</strong></p>BAZ premium moodle theme by RoseaThemes.';
$setting = new baz_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

// HR.
$name = 'theme_baz/hfootersocial';
$heading = get_string('hfootersocial', 'theme_baz');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hfootersocial_desc', 'theme_baz'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_baz/showsociallist';
$title = get_string('showsociallist', 'theme_baz');
$description = get_string('showsociallist_desc', 'theme_baz');
$default = '0';
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Website.
$name = 'theme_baz/website';
$title = get_string('website', 'theme_baz');
$description = get_string('website_desc', 'theme_baz');
$default = 'Moodle Themes';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Website.
$name = 'theme_baz/cwebsiteurl';
$title = get_string('cwebsiteurl', 'theme_baz');
$description = get_string('cwebsiteurl_desc', 'theme_baz');
$default = 'https://rosea.io';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Mobile.
$name = 'theme_baz/mobile';
$title = get_string('mobile', 'theme_baz');
$description = get_string('mobile_desc', 'theme_baz');
$default = 'Mobile : +12 (34) 00123-45678';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Mail.
$name = 'theme_baz/mail';
$title = get_string('mail', 'theme_baz');
$description = get_string('mail_desc', 'theme_baz');
$default = 'sample@mail.com';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Facebook url setting.
$name = 'theme_baz/facebook';
$title = get_string('facebook', 'theme_baz');
$description = get_string('facebook_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Twitter url setting.
$name = 'theme_baz/twitter';
$title = get_string('twitter', 'theme_baz');
$description = get_string('twitter_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Linkdin url setting.
$name = 'theme_baz/linkedin';
$title = get_string('linkedin', 'theme_baz');
$description = get_string('linkedin_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Youtube url setting.
$name = 'theme_baz/youtube';
$title = get_string('youtube', 'theme_baz');
$description = get_string('youtube_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Instagram url setting.
$name = 'theme_baz/instagram';
$title = get_string('instagram', 'theme_baz');
$description = get_string('instagram_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

// Cutsom icons setting.
$name = 'theme_baz/customsocialicon';
$title = get_string('customsocialicon', 'theme_baz');
$description = get_string('customsocialicon_desc', 'theme_baz');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);


// Must add the page after definiting all the settings!
$settings->add($page);
