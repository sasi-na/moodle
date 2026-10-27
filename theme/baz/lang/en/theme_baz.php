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
 * Language file.
 *
 * @package   theme_baz
 * @copyright Copyright © 2021 onwards Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 */

defined('MOODLE_INTERNAL') || die();
$siteurl = $CFG->wwwroot;

//$string['backgroundimage'] = 'Background image';
//$string['backgroundimage_desc'] = 'The image to display as a background of the site. The background image you upload here will override the background image in your theme preset files.';
$string['bootswatch'] = 'Bootswatch';
$string['bootswatch_desc'] = 'A bootswatch is a set of Bootstrap variables and css to style Bootstrap';
$string['choosereadme'] = '<div class="text-center"><img src="' . $siteurl . '/theme/baz/doc/baz-icon.svg" class="img-fluid rounded my-3" width="80" height="80" alt="BAZ Moodle Theme" /><h2>BAZ</h2>
<p class="lead-5">Premium Moodle Theme designed by RoseaThemes.</p>
<p class="lead-2 my-5">I want to make beautiful things<br />even if nobody cares.</p>
<p>Without a shadow of a doubt, BAZ has something for everyone and looks amazing!</p>
<hr /><h4>Support</h4><p>Need help with theme customization?<br />or you want to report a bug?</p><a href="https://rosea.gitbook.io/baztheme" target="_blank" class="btn btn-success m-1">Documentation</a><a href="https://roseathemes.ticksy.com" target="_blank" class="btn btn-info m-1">Dedicated ticket system</a></div><hr class="hr-small" /><p class="dimmed small text-center">For my wife Karolina and children, Bruno, Antoni and Zosia.<br /><br />Made in Poland</p>';
$string['currentinparentheses'] = '(current)';
$string['configtitle'] = 'BAZ';
$string['generalsettings'] = 'General settings';
$string['wrappermdfw'] = 'Remove Narrow Container';
$string['wrappermdfw_desc'] = 'Remove narrow wrapper inside the content.';
$string['nobootswatch'] = 'None';
$string['pluginname'] = 'BAZ (2.0.14)';
$string['privacy:metadata'] = 'The baz theme does not store any personal data about any user.';
$string['rawscss'] = 'Raw SCSS';
$string['rawscss_desc'] = 'Use this field to provide SCSS or CSS code which will be injected at the end of the style sheet.';
$string['rawscsspre'] = 'Raw initial SCSS';
$string['rawscsspre_desc'] = 'In this field you can provide initialising SCSS code, it will be injected before everything else. Most of the time you will use this setting to define variables.';
$string['privacy:metadata:preference:sidebaropen'] = 'The user\'s preference for hiding or showing the drawer menu navigation.';
$string['privacy:metadata:preference:darkmodeon'] = 'The user\'s preference for dark mode';
$string['privacy:rightdrawerclosed'] = 'The current preference for the navigation drawer is closed.';
$string['privacy:rightdraweropen'] = 'The current preference for the navigation drawer is open.';
$string['privacy:darkmodeoff'] = 'The current preference for the dark mode is off.';
$string['privacy:darkmodeon'] = 'The current preference for the dark mode is on.';
$string['totop'] = 'Go to top';

$string['region-side-pre'] = 'Hidden Sidebar';
$string['region-dtopblocks'] = 'Dashboard Top Blocks';
$string['region-dbottomblocks'] = 'Dashboard Bottom Blocks';
$string['region-dleftblocks'] = 'Dashboard Left Blocks';
$string['region-dmiddleblocks'] = 'Dashboard Middle Blocks';
$string['region-drightblocks'] = 'Dashboard Right Blocks';
$string['region-sidecourseblocks'] = 'Course - Sidebar Blocks';
$string['region-ctopbl'] = 'Course - Top Blocks';
$string['region-cbottombl'] = 'Course - Bottom Blocks';
$string['region-cstopbl'] = 'Course Sections - Top Blocks';
$string['region-csbottombl'] = 'Course Sections - Bottom Blocks';

$string['darkmodetheme'] = 'Dark Mode Switcher';
$string['darkmodetheme_desc'] = '';
$string['darkmodefirst'] = 'Only Dark Mode UI';
$string['darkmodefirst_desc'] = 'Force Dark Interface Only. When enabled the Dark Mode Switcher will not be displayed.';
$string['sdarkmode'] = 'Dark Mode (Button Title/Tooltip)';
$string['sdarkmode_desc'] = '<span class="badge-sq badge-info mb-2">FAQ</span> <ul><li><span>Displaying text in multiple languages </span><a class="underline--anim" href="https://docs.moodle.org/400/en/Multi-language_content_filter" target="_blank">Learn more</a></li></ul>';
$string['slightmode'] = 'Light Mode (Button Title/Tooltip)';
$string['slightmode_desc'] = '<span class="badge-sq badge-info mb-2">FAQ</span> <ul><li><span>Displaying text in multiple languages </span><a class="underline--anim" href="https://docs.moodle.org/400/en/Multi-language_content_filter" target="_blank">Learn more</a></li></ul>';

$string['themeauthor'] = 'Theme Author';
$string['themeauthor_desc'] = 'Show information about the author of the theme. - in the source code.';

$string['fontawesome'] = 'FontAwesome (v6.4)';
$string['fontawesome_desc'] = 'Self-hosted - Web Fonts.<br /><small>If you want to use an older version of the script just add the CDN code to <strong>Site administration - Appearance - Additional HTML - Within HEAD</strong>.</small>';

$string['thiscourse'] = 'Course Sections';
$string['nothiscourse'] = 'We cannot identify any course sections or topics';

$string['showhintcoursehiddensetting'] = 'Show hint in hidden courses';
$string['showhintcoursehiddensetting_desc'] = 'With this setting a hint will appear in the course header as long as the visibility of the course is hidden. This helps to identify the visibility state of a course at a glance without the need for looking at the course settings.';
$string['showhintcoursehiddensettingslink'] = 'You can change the visibility in the <a href="{$a->url}">course settings</a>.';
$string['showhintcoursehiddengeneral'] = 'This course is currently <strong>hidden</strong>. Only enrolled teachers can access this course when hidden.';


//Edit Button Text
$string['editon'] = 'Turn Edit On';
$string['editoff'] = 'Turn Edit Off';
$string['left'] = 'Left';
$string['center'] = 'Center';
$string['right'] = 'Right';


// Custom Alert
$string['alertsettings'] = 'Custom Alert';
$string['displaycustomalert'] = 'Display Custom Alert';
$string['displaycustomalert_desc'] = '';
$string['closecustomalert'] = 'Close Custom Alert Permanently';
$string['closecustomalert_desc'] = '';
$string['customalerthtml'] = 'Custom Alert Content';
$string['customalerthtml_desc'] = '';
$string['customalertid'] = 'Custom Alert ID';
$string['customalertid_desc'] = 'Change the ID to show the alert again.';

// SEO
$string['seosettings'] = 'SEO';
$string['seometadesc'] = 'Meta Description';
$string['seometadesc_desc'] = '';
$string['seoappletouchicon'] = 'Apple Touch Icon';
$string['seoappletouchicon_desc'] = 'Apple Touch Icon';
$string['seothemecolor'] = 'SEO Theme Color';
$string['seothemecolor_desc'] = 'The theme-color value for the name attribute of the <meta> element indicates a suggested color that user agents should use to customize the display of the page or of the surrounding user interface. <a href="https://developer.mozilla.org/en-US/docs/Web/HTML/Element/meta/name/theme-color" target="_blank">Learn more about theme-color tag</a>';
$string['seomanifestjson'] = 'Manifest JSON';
$string['seomanifestjson_desc'] = '';

//Course Card
$string['cccteachers'] = 'Display teachers section';
$string['cccteachers_desc'] = 'Display teachers section on the main course page and inside course card.<br /><div class="mt-3 w-100 text-left"><img src="' . $siteurl . '/theme/baz/doc/teachers.png" class="img-fluid rounded" style="max-width: 400px;" alt="BAZ Moodle Theme" /></div>';

$string['cccfooter'] = 'Display "Get access" buttons';
$string['cccfooter_desc'] = '';

$string['cccdteachers'] = 'Show Course Card Teachers Section';
$string['cccdteachers_desc'] = '';

$string['cccdfooter'] = 'Show Course Card Footer <small>(Get access)</small>';
$string['cccdfooter_desc'] = '';

$string['coursecarddesclimit'] = 'Description - Text Limit (Course Card)';
$string['coursecarddesclimit_desc'] = '<span class="badge-sm badge-secondary"><strong class="mr-1">Example (text length - letters):</strong> 120</span>';

// Main Course Page
$string['ipcoursesummary'] = 'Display course summary (Main Course Page)';
$string['ipcoursesummary_desc'] = '';
$string['ipcourseimage'] = 'Display course image (Main Course Page)';
$string['ipcourseimage_desc'] = '';

// Headings
$string['hlogin'] = 'Login Page';
$string['hlogin_desc'] = 'Login page customization. You can select three layouts, add background and more.';
$string['hsignup'] = 'Sign up Page';
$string['hsignup_desc'] = 'Customization of the sign up page.';
$string['hcoursecard'] = 'Course Card';
$string['hcoursecard_desc'] = '';
$string['loginidprovtop'] = 'Display list of identity providers before the login form';
$string['loginidprovtop_desc'] = '';

// Settings -- Advanced
$string['advancedsettings'] = 'Advanced settings';
$string['googleanalytics'] = 'Google Analytics V4 Code';
$string['googleanalyticsdesc'] = 'Please enter your Google Analytics V4 code to enable analytics on your website. The code format should be like [G-XXXXXXXXXX].<br />To meet all GPDR rules, the code will be visible only for logged-in users. Don\'t forget to add a cookie popup with information about cookie collecting.';
$string['favicon'] = 'Custom favicon';
$string['favicon_desc'] = 'Upload your own favicon. It should be an <strong>.ico</strong> file. <a href="https://www.favicon-generator.org/" target="_blank">Favicon generator #1</a>';
$string['favicon16'] = 'Custom favicon (16x16)';
$string['favicon32'] = 'Custom favicon (32x32)';
$string['favicon32'] = 'Custom favicon (32x32)';
$string['faviconsafaritab'] = 'Safari Pinned Tab (svg)';
$string['faviconsafaritabcolor'] = 'Safari Pinned Tab (color)';
$string['fontfilessetting'] = 'Font files';
$string['fontfilessetting_desc'] = 'With this dialogue you can upload own font files. The uplaod is resricted to the font files of type .eot, .woff, .woff2, .ttf and .svg. <br/>
<br /><h5>Step by step: <a href="https://rosea.gitbook.io/space-moodle-theme/theme-settings/google-custom-fonts-and-gdpr" target="_blank" class="badge badge-xs badge-info">Documentation <svg class="ml-2" width="12" height="12" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.75 6.75L19.25 12L13.75 17.25"></path><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H4.75"></path></svg></a></h5>
<ol>
<li>Convert Google Font Files to: *.eot, *.woff, .*woff2, *.svg. <br /><a href="https://cloudconvert.com/ttf-to-woff" target="_blank">Online Font Converter</a></li>
<li>To be able to use the uploaded fonts within this theme, you have to add related code to your "Raw SCSS" area in the tab "Advanced Tab".</li>
<li>HTML code to add (Advanced - Raw SCSS) <a href="https://rosea.gitbook.io/space-moodle-theme/theme-settings/google-custom-fonts-and-gdpr" class="badge badge-xs badge-dark" target="_blank">Learn more - Code snippet to add <svg class="ml-2" width="12" height="12" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.75 6.75L19.25 12L13.75 17.25"></path><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H4.75"></path></svg></a></li>
</ol>';
// Settings - Login page.
$string['settingslogin'] = 'Login Page';
$string['setloginlayout'] = 'Login Page Image Position';
$string['setloginlayout_desc'] = '';
$string['loginlayout1'] = '#1 - Box - Background image (middle)';
$string['loginlayout2'] = '#2 - Box - Image on the right (middle)';
$string['loginlayout3'] = '#3 - Box - Image on the left (middle)';
$string['loginlayout4'] = '#4 - Full height (left)';
$string['loginlayout5'] = '#5 - Full height (right)';
$string['loginhtmlcontent1'] = 'HTML Content #1';
$string['loginhtmlcontent1_desc'] = 'Custom HTML content<br />
<ul>
<li>for layout #1: before the logo</li>
<li>for layout #2, #3: before the login box</li>
</ul>';
$string['loginhtmlcontent2'] = 'HTML Content #2';
$string['loginhtmlcontent2_desc'] = 'Custom HTML content<br />
<ul>
<li>for layout #1: after HTML content #2</li>
<li>for layout #2, #3: next to the login form</li>
</ul>';
$string['loginhtmlcontent3'] = 'HTML Content #3';
$string['loginhtmlcontent3_desc'] = 'Custom HTML content<br />
<ul>
<li>for layout #1: after the login form</li>
<li>for layout #2, #3: under the login box</li>
</ul>';
$string['logincustomfooterhtml'] = 'Custom Footer HTML or JS';
$string['logincustomfooterhtml_desc'] = '';
$string['loginhtmlblockbottom'] = 'Custom HTML Block (under the login form)';
$string['loginhtmlblockbottom_desc'] = '';
$string['loginfootercontent'] = 'Footer Content';
$string['loginfootercontent_desc'] = 'Custom HTML content<br />
<ul>
<li>for layout #1: after the login form and HTML Content #2</li>
<li>for layout #2, #3: under the login box</li>
</ul>';
$string['loginbg'] = 'Login Page Background';
$string['loginbg_desc'] = '';
$string['loginbgcolor'] = 'Login Background Color (Image Container)';
$string['loginbgcolor_desc'] = '';
$string['customloginlogo'] = 'Custom Logo on the Login Page';
$string['customloginlogo_desc'] = '<strong>Recommendation:</strong> SVG files or png files with transparent background.';
$string['customlogindmlogo'] = 'Custom Logo on the Login Page (Dark Mode)';
$string['customlogindmlogo_desc'] = '<strong>Recommendation:</strong> SVG files or png files with transparent background.';
$string['colorloginbgtext'] = 'Text Color';
$string['colorloginbgtext_desc'] = 'Color of the text like "Create an account"';
$string['loginintrotext'] = 'Log in - Introduction';
$string['loginintrotext_desc'] = '';
$string['stringca'] = 'Label next to "Create an account" button.';
$string['stringca_desc'] = 'Label displays on the Sign in page';
$string['stringbacktologin'] = 'Label next to "Log in"';
$string['stringbacktologin_desc'] = 'Label displays on the Sign up page';
$string['signuptext'] = 'Sign up Content';
$string['signuptext_desc'] = '';
$string['signupintrotext'] = 'Sign up - Introduction';
$string['signupintrotext_desc'] = '';
$string['loginlogooutside'] = 'Logo outside the container';
$string['loginlogooutside_desc'] = '';
$string['customsignupoutside'] = 'Sign up link outside the container';
$string['customsignupoutside_desc'] = 'Display the sign-up link on the right top corner. If disabled, the sign-up button will be displayed under the log-in button.';
$string['hideforgotpassword'] = 'Hide remember password link';
$string['hideforgotpassword_desc'] = '';
$string['logininfobox'] = 'Additional content under the password input field.';
$string['logininfobox_desc'] = 'Code snippet:<pre class="rui-pre"><code>&#x3C;div class=&#x22;mt-3&#x22;&#x3E;&#x3C;p class=&#x22;small&#x22;&#x3E;Sample text&#x3C;/p&#x3E;&#x3C;/div&#x3E;</code></pre>
<a href="https://rosea.gitbook.io/baz-moodle-theme/theme-settings/login-page#available-content-fields-areas" class="btn btn-sm btn-success" target="_blank">Documentation - Login Page Customization</a>';


//Repeatable
$string['none'] = 'None';
$string['haccordionend'] = '';
$string['haccordionend_desc'] = '';
$string['blockintrotitle'] = 'Intro Title';
$string['blockintrotitle_desc'] = '';
$string['blockintrocontent'] = 'Intro Content';
$string['blockintrocontent_desc'] = '';
$string['blockhtmlcontent'] = 'HTML Content';
$string['blockhtmlcontent_desc'] = '';
$string['blockfootercontent'] = 'Footer Content';
$string['blockfootercontent_desc'] = '';
$string['turnon'] = 'Turn on';
$string['turnon_desc'] = '';

// Settings -- Course page
$string['settingscourses'] = 'Course Page';
$string['courseimagefullwidth'] = 'Full width';
$string['courseimagecontent'] = 'Inside the content';
$string['courseprogressbar'] = 'Display Course Progress Bar';
$string['courseprogressbar_desc'] = '<div class="w-100 text-left"><img src="' . $siteurl . '/theme/baz/doc/course-progress.png" class="img-fluid rounded w-100" alt="BAZ Moodle Theme" style="max-width: 400px;" /></div>';



// Settings -- Top Bar
$string['settingstopbar'] = 'Top Bar';
$string['topbarhamburgermenu'] = 'Hamburger Menu';
$string['topbarhamburgermenu_desc'] = 'Display hamburger menu instead horizontal navigation. <br /><div class="w-100 text-left"><img src="' . $siteurl . '/theme/baz/doc/hamburger-menu.jpg" class="img-fluid rounded my-3 w-100" alt="BAZ Moodle Theme" /></div>';
$string['hmycoursesbtn'] = 'My Courses Button';
$string['hmycoursesbtn_desc'] = '';
$string['turnoffdashboardlink'] = 'Turn off Dashboard Link (User Menu)';
$string['turnoffdashboardlink_desc'] = '<p>You can turn off Dashboard Link inside the User menu.</p><img src="{$a->siteurl}/theme/baz/doc/user-menu-dashboard.png" class="mt-2 rounded" style="max-height: 300px; width: auto; max-width: 100%;" alt="Dashboard Link" />';
// Depreciated in moodle 4.0
// $string['stringmycourses'] = 'My Courses';
// $string['stringmycourses_desc'] = '';
$string['stringallcourses'] = 'List of all available courses';
$string['stringallcourses_desc'] = 'Leave this field empty if you want to hide this button.';
$string['stringnocourses'] = 'You are not enrolled in any courses.';
$string['stringnocourses_desc'] = '';
$string['stringallcourses'] = 'List of all available courses';
$string['stringallcourses_desc'] = 'Leave this field empty if you want to hide this button.
<br />This button depends on user permissions - moodle/category:viewcourselist.
<br />From \'Site administration / Users / Permissions / Define roles\'
<a href="https://docs.moodle.org/400/en/Course_list_viewer_role" target="_blank">Learn more about the course list viewer role</a>
<ul><li><span>Displaying text in multiple languages </span><a class="underline--anim" href="https://docs.moodle.org/400/en/Multi-language_content_filter" target="_blank">Learn more</a></li></ul>';
$string['stickybreadcrumbs'] = 'Sticky Breadcrumbs';
$string['stickybreadcrumbs_desc'] = '';
$string['stringdetails'] = 'Details';
$string['stringdetails_desc'] = '<p>Link to My Courses Page.</p><div class="mt-3 mb-2">Sample code snippet</div><pre class="rui-pre">&lt;span&gt;My courses overview&lt;/span&gt;&lt;svg width="18" height="18" fill="none" viewBox="0 0 24 24"&gt;
&lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.75 6.75L19.25 12L13.75 17.25"&gt;&lt;/path&gt;
&lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 12H4.75"&gt;&lt;/path&gt;
&lt;/svg&gt;</pre><ul><li><span>Displaying text in multiple languages </span><a class="underline--anim" href="https://docs.moodle.org/400/en/Multi-language_content_filter" target="_blank">Learn more</a></li></ul>';
$string['stringallcourses'] = 'List of all available courses';
$string['stringallcourses_desc'] = 'Leave this field empty if you want to hide this button.
<br />This button depends on user permissions - moodle/category:viewcourselist.
<br />From \'Site administration / Users / Permissions / Define roles\'
<a href="https://docs.moodle.org/400/en/Course_list_viewer_role" target="_blank">Learn more about the course list viewer role</a>
<ul><li><span>Displaying text in multiple languages </span><a class="underline--anim" href="https://docs.moodle.org/400/en/Multi-language_content_filter" target="_blank">Learn more</a></li></ul>';

$string['htopbarcolors'] = 'Color Customization';
$string['htopbarcolors_desc'] = '';
$string['colortopbarbg'] = 'Topbar Background';
$string['colortopbartext'] = 'Text';
$string['colortopbarbtn'] = 'Button Background';
$string['colortopbarbtntext'] = 'Button Text';
$string['colortopbarbtnhover'] = 'Button Background (Hover)';
$string['colortopbarbtnhovertext'] = 'Button Text (Hover)';


// Settings -- Dashboard
$string['settingsdashboard'] = 'Dashboard';
$string['setdashboardlayout'] = 'Dashboard Page Layout';
$string['setdashboardlayout_desc'] = '<img src="' . $siteurl . '/theme/baz/doc/dashboard-layout.jpg" class="img-fluid rounded my-3 w-100" alt="BAZ Moodle Theme" />';
$string['dashboardlayout1'] = 'Layout #1';
$string['dashboardlayout2'] = 'Layout #2';
$string['dashboardlayout3'] = 'Layout #3';
$string['customdcolsize'] = 'Custom Dashboard Column Width (Sidebar Left/Right)';
$string['customdcolsize_desc'] = 'For Layout #2, #3<br />col-lg (50%), col-lg-3 (33%), col-lg-4 (25%)';

// Settings -- Footer
$string['settingsfooter'] = 'Footer';
$string['footerblock1'] = 'Footer Block #1';
$string['footerblock1_desc'] = '<span class="badge-sq badge-warning">More snippets will be added to the next theme release.</span>';
$string['footerblock2'] = 'Footer Block #2';
$string['footerblock2_desc'] = '<span class="badge-sq badge-warning">More snippets will be added to the next theme release.</span>';
$string['footerblock3'] = 'Footer Block #3';
$string['footerblock3_desc'] = '<span class="badge-sq badge-warning">More snippets will be added to the next theme release.</span>';
$string['footercopy'] = 'Footer Copy';
$string['footercopy_desc'] = '';
$string['showfooterbuttons'] = 'Display Footer Buttons';
$string['showfooterbuttons_desc'] = 'Default Moodle buttons on the footer like: Moodle Doc, Data retention summary, etc.';

// Content Builder
$string['scbsettings'] = 'Blocks Order';
$string['block1'] = 'Block #1 <span class="mt-1 small d-block text-light w-100">Hero Slider #1</span>';
$string['block1_desc'] = '';
$string['block2'] = 'Block #2 <span class="mt-1 small d-block text-light w-100">HTML Block #1</span>';
$string['block2_desc'] = '';
$string['block3'] = 'Block #3 <span class="mt-1 small d-block text-light w-100">HTML Block #2</span>';
$string['block3_desc'] = '';
$string['block4'] = 'Block #4 <span class="mt-1 small d-block text-light w-100">FAQ</span>';
$string['block4_desc'] = '';
$string['block5'] = 'Block #5 <span class="mt-1 small d-block text-light w-100">Logotypes</span>';
$string['block5_desc'] = '';
$string['block6'] = 'Block #6 <span class="mt-1 small d-block text-light w-100">HTML Block #3</span>';
$string['block6_desc'] = '';
$string['block7'] = 'Block #7 <span class="mt-1 small d-block text-light w-100">HTML Block #4</span>';
$string['block7_desc'] = '';
$string['block8'] = 'Block #8 <span class="mt-1 small d-block text-light w-100">HTML Block #5</span>';
$string['block8_desc'] = '';
$string['block9'] = 'Block #9 <span class="mt-1 small d-block text-light w-100">HTML Block #6</span>';
$string['block9_desc'] = '';
$string['block10'] = 'Block #10 <span class="mt-1 small d-block text-light w-100">HTML Block #7</span>';
$string['block10_desc'] = '';
$string['block11'] = 'Block #11 <span class="mt-1 small d-block text-light w-100">HTML Block #8</span>';
$string['block11_desc'] = '';
$string['block12'] = 'Block #12 <span class="mt-1 small d-block text-light w-100">HTML Block #9</span>';
$string['block12_desc'] = '';
$string['block13'] = 'Block #13 <span class="mt-1 small d-block text-light w-100">CTA #1</span>';
$string['block13_desc'] = '';
$string['block14'] = 'Block #14 <span class="mt-1 small d-block text-light w-100">HTML Block #10</span>';
$string['block14_desc'] = '';
$string['block15'] = 'Block #15 <span class="mt-1 small d-block text-light w-100">HTML Block #11</span>';
$string['block15_desc'] = '';
$string['block16'] = 'Block #16 <span class="mt-1 small d-block text-light w-100">HTML Block #12</span>';
$string['block16_desc'] = '';
$string['block17'] = 'Block #17 <span class="mt-1 small d-block text-light w-100">HTML Block #13</span>';
$string['block17_desc'] = '';
$string['block18'] = 'Block #18 <span class="mt-1 small d-block text-light w-100">HTML Block #14</span>';
$string['block18_desc'] = '';
$string['block19'] = 'Block #19 <span class="mt-1 small d-block text-light w-100">CTA #2</span>';
$string['block19_desc'] = '';
$string['block20'] = 'Block #20';
$string['block20_desc'] = '';
$string['block21'] = 'Block #21';
$string['block21_desc'] = '';

$string['displayblockhr'] = 'Show Block Separator (hr)';
$string['displayblockhr_desc'] = '';

// Block 1
$string['settingsblock1'] = 'Block #1';;

$string['displayblock1_desc'] = '<small>Script: <a href="https://swiperjs.com/" target="_blank">Swiper</a>. MIT Licensed, v7.0.8 released on October 4, 2021</small>';

$string['hblock1slide'] = 'Slide';
$string['hblock1slide_desc'] = '';
$string['block1count'] = 'Slider count';
$string['block1count_desc'] = '';
$string['block1slideimg'] = 'Slide Image';
$string['block1slideimg_desc'] = '';
$string['block1slidetitle'] = 'Slide Heading';
$string['block1slidetitle_desc'] = '';
$string['block1slidecaption'] = 'Slide Caption';
$string['block1slidecaption_desc'] = '<br />If you want to display or hide some elements for non-logged in users use dedicated class names:<br /><ul><li>For non-logged in users: <strong>hidefornotloggedin</strong></li><li>For logged in users: <strong>hideforloggedin</strong></li></ul>';
//$string['block1slidebtns'] = 'Slide Buttons';
//$string['block1slidebtns_desc'] = '<br />If you want to display or hide some elements for non-logged in users use dedicated class names:<br /><ul><li>For non-logged in users: <strong>hidefornotloggedin</strong></li><li>For logged in users: <strong>hideforloggedin</strong></li></ul>';
$string['block1slidecss'] = 'Slide Custom CSS';
$string['block1slidecss_desc'] = '<a href="https://css-tricks.com/almanac/properties/b/background/" target="_blank">Learn more about CSS background properties</a>';
$string['showblock1sliderwrapper'] = 'Show Colorized Content Wrapper';
$string['showblock1sliderwrapper_desc'] = '';
$string['block1sliderwrapperbg'] = 'Content Wrapper Color';
$string['block1sliderwrapperbg_desc'] = '';
$string['block1wrapperalign'] = 'Content Wrapper Alignment';
$string['block1wrapperalign_desc'] = '';

// Block 2
$string['settingsblock2'] = 'Block #2';
$string['displayblock2_desc'] = '';

// Block 3
$string['settingsblock3'] = 'Block #3';
$string['displayblock3_desc'] = '';

// Block 4
$string['settingsblock4'] = 'Block #4';
$string['displayblock4_desc'] = '';
$string['hblock4'] = 'Custom HTML';
$string['hblock4_desc'] = 'You can use HTML to display accordion items or just add it using simple form below.';
$string['hblock4_2'] = 'FAQ Items';
$string['hblock4_2_desc'] = 'Add FAQ items manually.';
$string['block4count'] = 'Number of items';
$string['block4count_desc'] = 'Number of items';
$string['block4answer'] = 'Answer';
$string['block4answer_desc'] = '';
$string['block4question'] = 'Question';
$string['block4question_desc'] = '';

// Block 5
$string['hblock5item'] = 'Item';
$string['hblock5item_desc'] = '';
$string['settingsblock5'] = 'Block #5';
$string['displayblock5_desc'] = '';
$string['block5slidesperrow'] = 'Sldies per row';
$string['block5slidesperrow_desc'] = '';
$string['block5count'] = 'Number of items';
$string['block5count_desc'] = '';
$string['block5itemimg'] = 'Image';
$string['block5itemimg_desc'] = '';
$string['block5itemtitle'] = 'Title (Alt)';
$string['block5itemtitle_desc'] = '';
$string['block5itemurl'] = 'URL with (https:// or http://)';
$string['block5itemurl_desc'] = '';

// Block 6
$string['settingsblock6'] = 'Block #6';
$string['displayblock6_desc'] = '';

// Block 7
$string['settingsblock7'] = 'Block #7';
$string['displayblock7_desc'] = '';

// Block 8
$string['settingsblock8'] = 'Block #8';
$string['displayblock8_desc'] = '';

// Block 9
$string['settingsblock9'] = 'Block #9';
$string['displayblock9_desc'] = '';

// Block 10
$string['settingsblock10'] = 'Block #10';
$string['displayblock10_desc'] = '';

// Block 11
$string['settingsblock11'] = 'Block #11';
$string['displayblock11_desc'] = '';

// Block 12
$string['settingsblock12'] = 'Block #12';
$string['displayblock12_desc'] = '';

// Block 13
$string['settingsblock13'] = 'Block #13';
$string['displayblock13_desc'] = '';
$string['block13bg'] = 'Background Image';
$string['block13bg_desc'] = '';
$string['block13bgcolor'] = 'Background Color';
//TODO: dodac lepszy opis
$string['block13bgcolor_desc'] = 'You can use custom CSS for solid background or gradient.';
$string['block13customcss'] = 'Custom CSS (to set up backgroud properties)';
$string['block13customcss_desc'] = 'Tutorial: https://css-tricks.com/almanac/properties/b/background/';


// Block 14
$string['settingsblock14'] = 'Block #14';
$string['displayblock14_desc'] = '';
$string['block14bg'] = 'Background Image';
$string['block14bg_desc'] = '';
$string['block14customcss'] = 'Custom CSS (to set up backgroud properties)';
$string['block14customcss_desc'] = 'Tutorial: https://css-tricks.com/almanac/properties/b/background/';
$string['block14textalign'] = 'Text alignment';
$string['block14textalign_desc'] = '';

// Block 15
$string['settingsblock15'] = 'Block #15';
$string['displayblock15_desc'] = '';

// Block 16
$string['settingsblock16'] = 'Block #16';
$string['displayblock16_desc'] = '';

// Block 17
$string['settingsblock17'] = 'Block #17';
$string['displayblock17_desc'] = '';

// Block 18
$string['settingsblock18'] = 'Block #18';
$string['displayblock18_desc'] = '';

// Block 19
$string['settingsblock19'] = 'Block #19';
$string['displayblock19_desc'] = '';
$string['block19bg'] = 'Background Image';
$string['block19bg_desc'] = '';
$string['block19customcss'] = 'Custom CSS (to set up background properties)';
$string['block19customcss_desc'] = 'Tutorial: https://css-tricks.com/almanac/properties/b/background/';
$string['block19textalign'] = 'Text alignment';
$string['block19textalign_desc'] = '';

// // Block 20
// $string['settingsblock20'] = 'Block #20';
// $string['displayblock20_desc'] = '';

// // Block 21
// $string['settingsblock21'] = 'Block #21';
// $string['displayblock21_desc'] = '';

// Block 0 - Main Moodle Content.
$string['settingsblock0'] = 'Block #0';
$string['displayblock0_desc'] = '';
$string['block0'] = 'Block #0 <span class="mt-1 small d-block text-light w-100">Main Moodle Content</span><span class="badge-xs badge-light mt-2">Go to the settings - <a href="' . $siteurl . '/admin/settings.php?section=frontpagesettings" target="_blank">Edit</a></span>';
$string['block0_desc'] = '';

// Customization
$string['settingscustomization'] = 'Customization';
$string['settingscolorcustomization'] = 'Colors';
$string['settingsfontcustomization'] = 'Fonts';
$string['hfontsettings'] = 'Fonts Settings';
$string['hfontsettings_desc'] = 'Customize Font Properties';
$string['hgooglefont'] = 'Google Font URL';
$string['hgooglefont_desc'] = 'Google Fonts is a library of 1,284 free licensed font families. <a href="https://fonts.google.com" targe="_blank">Google Fonts Library</a>.';
$string['googlefonturl'] = 'Google Font URL';
$string['googlefonturl_desc'] = "";
$string['fontbody'] = 'Font Name (Body)';
$string['fontbody_desc'] = "";
$string['fontheadings'] = 'Font Name (Headings)';
$string['fontheadings_desc'] = "";
$string['fontweightregular'] = 'Font weight: Regular';
$string['fontweightregular_desc'] = 'e.g 400';
$string['fontweightmedium'] = 'Font weight: Medium';
$string['fontweightmedium_desc'] = 'e.g 500, 600';
$string['fontweightbold'] = 'Font weight: Bold';
$string['fontweightbold_desc'] = 'e.g 700, 800, 900';
$string['fontweightheadings'] = 'Font weight (Headings)';
$string['fontweightheadings_desc'] = 'e.g 700, 800, 900';

$string['hcustomfont'] = 'Custom Font';
$string['hcustomfont_desc'] = '';

$string['hgeneral'] = 'General Colors';
$string['hgeneral_desc'] = '';
$string['colorbodybg'] = 'Body Background Color';
$string['colorcontainerbg'] = 'Container Color';
$string['colorborder'] = 'Border Color (Global)';


$string['customlogo'] = 'Logo (Top Bar)';
$string['customlogo_desc'] = '';
$string['logocontainer'] = 'Logo - Solid Background Container';
$string['logocontainer_desc'] = '<img src="' . $siteurl . '/theme/baz/doc/logo-solid-bg.jpg" class="img-fluid rounded my-3" alt="BAZ Moodle Theme" />';

$string['hcolorstxt'] = 'Text Colors';
$string['hcolorstxt_desc'] = '';
$string['colorheadings'] = 'Headings';
$string['colorbody'] = 'Text Color';
$string['colorbodysecondary'] = 'Text Color (Secondary)';
$string['colorbodylight'] = 'Text Color (Light)';
$string['colorlink'] = 'Link Color';
$string['colorlinkhover'] = 'Link Color (Hover)';

$string['hcolorsgrays'] = 'Grays <span class="badge badge-sm badge-light mx-2">Not recommended to change</span>';
$string['hcolorsgrays_desc'] = '<p>I used gray shades compatible with WCAG Principles.</p>
<p>Change only when you really need to.</p><br /><div class="d-inline-flex flex-wrap">
<div class="sqcolor bg--desc bg-gray-100 m-1"></div><div class="sqcolor bg--desc bg-gray-200 m-1"></div>
<div class="sqcolor bg--desc bg-gray-300 m-1"></div><div class="sqcolor bg--desc bg-gray-400 m-1"></div>
<div class="sqcolor bg--desc bg-gray-500 m-1"></div><div class="sqcolor bg--desc bg-gray-600 m-1"></div>
<div class="sqcolor bg--desc bg-gray-700 m-1"></div><div class="sqcolor bg--desc bg-gray-800 m-1"></div>
<div class="sqcolor bg--desc bg-gray-900 m-1"></div></div>';
$string['colorgray100'] = 'Gray 100';
$string['colorgray200'] = 'Gray 200';
$string['colorgray300'] = 'Gray 300<br /><small class="mx-2">e.g main borders color</small>';
$string['colorgray400'] = 'Gray 400';
$string['colorgray500'] = 'Gray 500';
$string['colorgray600'] = 'Gray 600';
$string['colorgray700'] = 'Gray 700';
$string['colorgray800'] = 'Gray 800';
$string['colorgray900'] = 'Gray 900';
$string['colorwhite'] = 'White';
$string['colorblack'] = 'Black';
$string['colorgray_desc'] = '';

$string['hcolorsprimary'] = 'Primary colors';
$string['hcolorsprimary_desc'] = 'Primary buttons, top bar, all important/primary UI elements.
<br /><div class="d-inline-flex flex-wrap"><div class="sqcolor bg--desc bg-primary-100 m-1"></div>
<div class="sqcolor bg--desc bg-primary-200 m-1"></div><div class="sqcolor bg--desc bg-primary-300 m-1"></div>
<div class="sqcolor bg--desc bg-primary-400 m-1"></div><div class="sqcolor bg--desc bg-primary-500 m-1"></div>
<div class="sqcolor bg--desc bg-primary-600 m-1"></div><div class="sqcolor bg--desc bg-primary-700 m-1"></div>
<div class="sqcolor bg--desc bg-primary-800 m-1"></div><div class="sqcolor bg--desc bg-primary-900 m-1"></div>
</div>';
$string['colorprimary100'] = 'Primary 100';
$string['colorprimary200'] = 'Primary 200';
$string['colorprimary300'] = 'Primary 300';
$string['colorprimary400'] = 'Primary 400';
$string['colorprimary500'] = 'Primary 500';
$string['colorprimary600'] = 'Primary 600 - Main Theme Color';
$string['colorprimary700'] = 'Primary 700';
$string['colorprimary800'] = 'Primary 800';
$string['colorprimary900'] = 'Primary 900';
$string['colorprimary_desc'] = '<span class="badge badge-sq badge-warning">To generate automatically the colour palette just set up the "Main Theme Color - 600"</span>
<p class="mt-2"><small>If you want to change any color from the palette just add custom HEX color value to the field.</small></p>';
$string['color_desc'] = '';

$string['hcolorssecondary'] = 'Secondary Colors';
$string['hcolorssecondary_desc'] = 'Lighter color palette for UI interface like some backgrounds contains secondary buttons etc.
<br /><div class="d-inline-flex flex-wrap"><div class="sqcolor bg--desc bg-secondary-100 m-1"></div>
<div class="sqcolor bg--desc bg-secondary-200 m-1"></div><div class="sqcolor bg--desc bg-secondary-300 m-1"></div>
<div class="sqcolor bg--desc bg-secondary-400 m-1"></div><div class="sqcolor bg--desc bg-secondary-500 m-1"></div>
<div class="sqcolor bg--desc bg-secondary-600 m-1"></div><div class="sqcolor bg--desc bg-secondary-700 m-1"></div>
<div class="sqcolor bg--desc bg-secondary-800 m-1"></div><div class="sqcolor bg--desc bg-secondary-900 m-1"></div>
</div>';
$string['colorsecondary100'] = 'Secondary 100';
$string['colorsecondary200'] = 'Secondary 200';
$string['colorsecondary300'] = 'Secondary 300';
$string['colorsecondary400'] = 'Secondary 400';
$string['colorsecondary500'] = 'Secondary 500';
$string['colorsecondary600'] = 'Secondary 600';
$string['colorsecondary700'] = 'Secondary 700';
$string['colorsecondary800'] = 'Secondary 800';
$string['colorsecondary900'] = 'Secondary 900';

$string['colordesc_desc'] = '';


$string['additionalclass'] = 'Additional Class Name';
$string['additionalclass_desc'] = '<strong class="badge badge-warning mr-2">Only for developers.</strong><span>You can add multiple class names e.g class1 class2 class3 </span>';

$string['hintro'] = '<img src="' . $siteurl . '/theme/baz/doc/baz-icon.svg" class="img-fluid rounded my-3" width="80" height="80" alt="BAZ Moodle Theme" /><div>BAZ for Moodle 4.5 LTS</div>';
$string['hintro_desc'] = '
<div class="mt-1 small">by <a href="https://rosea.io">RoseaThemes</a></div><hr class="mt-3" />
<div class="mt-3"><span class="badge badge-primary"><a href="https://rosea.gitbook.io/baztheme/changelog" target="_blank">Version: 2.0.14</a></span></div>
<div class="mt-4"><h4 class="lead-4 mb-2">Need help with theme customization?<br />Or you want to report a bug?</h4>Just let me know. Open <a href="https://roseathemes.ticksy.com" target="_blank">a ticket</a> or contact me via support form on the ThemeForest item page.</div>
<a href="https://rosea.gitbook.io/baztheme" target="_blank" class="btn btn-sm btn-dark mt-3">Online documentation</a>
';

// Credits: BoostCampus.
// Setting: Show hint for switched role setting.
$string['showswitchedroleincoursesetting'] = 'Show hint for switched role';
$string['showswitchedroleincoursesetting_desc'] = 'With this setting a hint will appear in the course header if the user has switched the role in the course. By default, this information is only displayed right near the user\'s avatar in the user menu. By enabling this option, you can show this information - together with a link to switch back - within the course page as well.';
$string['switchedroleto'] = 'You are viewing this course currently with the role: <strong>{$a->role}</strong>';
// Setting: Show hint for hidden course.
$string['showhintcoursehiddensetting'] = 'Show hint in hidden courses';
$string['showhintcoursehiddensetting_desc'] = 'With this setting a hint will appear in the course header as long as the visibility of the course is hidden. This helps to identify the visibility state of a course at a glance without the need for looking at the course settings.';
$string['showhintcoursehiddengeneral'] = 'This course is currently <strong>hidden</strong>. Only enrolled teachers can access this course when hidden.';
$string['showhintcoursehiddensettingslink'] = 'You can change the visibility in the <a href="{$a->url}">course settings</a>.';
// Setting: Show hint for guest access.
$string['showhintcoursguestaccesssetting'] = 'Show hint for guest access';
$string['showhintcourseguestaccesssetting_desc'] = 'With this setting a hint will appear in the course header when a user is accessing it with the guest access feature. If the course provides an active self enrolment, a link to that page is also presented to the user.';
$string['showhintcourseguestaccessgeneral'] = 'You are currently viewing this course as <strong>{$a->role}</strong>.';
$string['showhintcourseguestaccesslink'] = 'To have full access to the course, you can <a href="{$a->url}">self enrol into this course</a>.';
// Setting: Show hint for unrestricted self enrolment.
$string['showhintcourseselfenrolsetting'] = 'Show hint for self enrolment without enrolment key';
$string['showhintcourseselfenrolsetting_desc'] = 'With this setting a hint will appear in the course header if the course is visible and an enrolment without enrolment key is currently possible.';
$string['showhintcourseselfenrolstartcurrently'] = 'This course is currently visible to everyone and <strong>self enrolment without an enrolment key</strong> is possible.';
$string['showhintcourseselfenrolstartfuture'] = 'This course is currently visible to everyone and <strong>self enrolment without an enrolment key</strong> is planned to become possible.';
$string['showhintcourseselfenrolunlimited'] = 'The <strong>{$a->name}</strong> enrolment instance allows unrestricted self enrolment indefinitely.';
$string['showhintcourseselfenroluntil'] = 'The <strong>{$a->name}</strong> enrolment instance allows unrestricted self enrolment until {$a->until}.';
$string['showhintcourseselfenrolfrom'] = 'The <strong>{$a->name}</strong> enrolment instance allows unrestricted self enrolment from {$a->from} on.';
$string['showhintcourseselfenrolsince'] = 'The <strong>{$a->name}</strong> enrolment instance allows unrestricted self enrolment currently.';
$string['showhintcourseselfenrolfromuntil'] = 'The <strong>{$a->name}</strong> enrolment instance allows unrestricted self enrolment from {$a->from} until {$a->until}.';
$string['showhintcourseselfenrolsinceuntil'] = 'The <strong>{$a->name}</strong> enrolment instance allows unrestricted self enrolment until {$a->until}.';
$string['showhintcourseselfenrolinstancecallforaction'] = 'If you don\'t want any Moodle user to have access to this course freely, please restrict the self enrolment settings.';

//Moodle 4.0
$string['showfooter'] = 'Show footer';
$string['privacy:metadata:preference:draweropenblock'] = 'The user\'s preference for hiding or showing the drawer with blocks.';
$string['privacy:metadata:preference:draweropenindex'] = 'The user\'s preference for hiding or showing the drawer with course index.';
$string['privacy:metadata:preference:draweropennav'] = 'The user\'s preference for hiding or showing the drawer menu navigation.';
$string['privacy:drawerindexclosed'] = 'The current preference for the index drawer is closed.';
$string['privacy:drawerindexopen'] = 'The current preference for the index drawer is open.';
$string['privacy:drawerblockclosed'] = 'The current preference for the block drawer is closed.';
$string['privacy:drawerblockopen'] = 'The current preference for the block drawer is open.';
$string['privacy:drawernavclosed'] = 'The current preference for the navigation drawer is closed.';
$string['privacy:drawernavopen'] = 'The current preference for the navigation drawer is open.';
$string['unaddableblocks'] = 'Unneeded blocks';
$string['unaddableblocks_desc'] = 'The blocks specified are not needed when using this theme and will not be listed in the \'Add a block\' menu.';

$string['settingssidebar-nav'] = 'Main Navigation';

$string['empty_desc'] = '';
$string['iconcustomitem_desc'] = 'SVG Inline <br><strong>Sample SVG Icon</strong>
<pre class="rui-pre"><code>&lt;svg width="20" height="20" fill="none"
viewBox="0 0 24 24"&gt; &lt;path stroke="currentColor" stroke-linecap="round"
stroke-linejoin="round" stroke-width="1.5" d="M4.75 6.75C4.75 5.64543 5.64543
4.75 6.75 4.75H17.25C18.3546 4.75 19.25 5.64543 19.25 6.75V17.25C19.25 18.3546
18.3546 19.25 17.25 19.25H6.75C5.64543 19.25 4.75 18.3546 4.75
17.25V6.75Z"&gt;&lt;/path&gt; &lt;path stroke="currentColor"
stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
d="M9.75 8.75V19"&gt;&lt;/path&gt; &lt;path stroke="currentColor"
stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
d="M5 8.25H19"&gt;&lt;/path&gt; &lt;/svg&gt;</code></pre>
<br>More icons you can find here:
<ul>
<li><a class="underline--anim" href="https://fontawesome.com/icons" target="_blank">FontAwesome</a></li>
<li><a class="underline--anim" href="https://iconic.app/" target="_blank">iconic</a></li>
<li><a class="underline--anim" href="https://css.gg/" target="_blank">css.gg</a></li>
<li><a class="underline--anim" href="https://coolicons.cool/" target="_blank">coolicons</a></li>
</ul>';
$string['urlcustomitem_desc'] = 'External links with https:// or http://.<br />Interial links e.g /mod/forum/view.php?id=207';

$string['customnavitems'] = 'Custom Navigation Items';
$string['customnavitems_desc'] = 'You can add additional navigation item or custom HTML content. Down below you can find a sample code snippet of the navigation item.
<pre class="rui-pre">&lt;li class="rui-sidebar-nav-item" id="yui_3_17_2_1_1659561578352_26557"&gt;&lt;a href="#" id="itemContentBank" class="rui-sidebar-nav-item-link "&gt;&lt;span class="rui-sidebar-nav-icon"&gt;&lt;svg width="20" height="20" fill="none" viewBox="0 0 24 24"&gt;&lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.25 17.25V9.75C19.25 8.64543 18.3546 7.75 17.25 7.75H4.75V17.25C4.75 18.3546 5.64543 19.25 6.75 19.25H17.25C18.3546 19.25 19.25 18.3546 19.25 17.25Z"&gt;&lt;/path&gt;&lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 7.5L12.5685 5.7923C12.2181 5.14977 11.5446 4.75 10.8127 4.75H6.75C5.64543 4.75 4.75 5.64543 4.75 6.75V11"&gt;&lt;/path&gt;&lt;/svg&gt;&lt;/span&gt;&lt;span class="rui-sidebar-nav-text"&gt;Content bank&lt;/span&gt;&lt;/a&gt;&lt;/li&gt;</pre>
SVG icons:
<ul>
<li><a href="https://iconic.app/" target="_blank">iconic</a></li>
<li><a href="https://fontawesome.com/icons" target="_blank">FontAwesome</a></li>
<li><a href="https://css.gg/" target="_blank">css.gg</a></li>
<li><a href="https://coolicons.cool/" target="_blank">coolicons</a></li>
</ul>';

$string['hmainnav'] = 'Main Navigation Items';
$string['hmainnav_desc'] = 'Customization of the Main Navigation Items';
$string['isitemonsitehome'] = 'Site home';
$string['isitemonsitehome_desc'] = '';
$string['isitemondashboard'] = 'Dashboard';
$string['isitemondashboard_desc'] = '';
$string['isitemoncalendar'] = 'Calendar';
$string['isitemoncalendar_desc'] = '';
$string['isitemonprivatefiles'] = 'Private files';
$string['isitemonprivatefiles_desc'] = '';
$string['isitemoncontentbank'] = 'Content bank';
$string['isitemoncontentbank_desc'] = '';
$string['isitemonmycourses'] = 'My courses';
$string['isitemonmycourses_desc'] = '';

$string['possitehome'] = 'Site home (Position)';
$string['posdashboard'] = 'Dashboard (Position)';
$string['posprivatefiles'] = 'Private Files (Position)';
$string['poscalendar'] = 'Calendar (Position)';
$string['poscontentbank'] = 'Content Bank (Position)';
$string['posmycourses'] = 'My Courses (Position)';

$string['iscustomitem1on'] = 'Custom Item #1';
$string['labelcustomitem1'] = 'Custom Item #1 (Label)';
$string['poscustomitem1'] = 'Custom Item #1 (Position)';
$string['iconcustomitem1'] = 'Custom Item #1 (Icon)';
$string['urlcustomitem1'] = 'Custom Item #1 (URL)';

$string['iscustomitem2on'] = 'Custom Item #2';
$string['labelcustomitem2'] = 'Custom Item #2 (Label)';
$string['poscustomitem2'] = 'Custom Item #2 (Position)';
$string['iconcustomitem2'] = 'Custom Item #2 (Icon)';
$string['urlcustomitem2'] = 'Custom Item #2 (URL)';

$string['iscustomitem3on'] = 'Custom Item #3';
$string['labelcustomitem3'] = 'Custom Item #3 (Label)';
$string['poscustomitem3'] = 'Custom Item #3 (Position)';
$string['iconcustomitem3'] = 'Custom Item #3 (Icon)';
$string['urlcustomitem3'] = 'Custom Item #3 (URL)';

$string['iscustomitem4on'] = 'Custom Item #4';
$string['labelcustomitem4'] = 'Custom Item #4 (Label)';
$string['poscustomitem4'] = 'Custom Item #4 (Position)';
$string['iconcustomitem4'] = 'Custom Item #4 (Icon)';
$string['urlcustomitem4'] = 'Custom Item #4 (URL)';

$string['iscustomitem5on'] = 'Custom Item #5';
$string['labelcustomitem5'] = 'Custom Item #5 (Label)';
$string['poscustomitem5'] = 'Custom Item #5 (Position)';
$string['iconcustomitem5'] = 'Custom Item #5 (Icon)';
$string['urlcustomitem5'] = 'Custom Item #5 (URL)';

$string['navposition_desc'] = '';;

// BAZ 2.0.14.
$string['hvpcss'] = 'H5P CSS';
$string['hvpcss_desc'] = 'Custom CSS code to be applied to H5P activities';

// Sidebar.
$string['settingssidebar-nav'] = 'Main Navigation';
$string['settingssidebar'] = 'Sidebar';
$string['customsidebarlogo'] = 'Sidebar Logo';
$string['customsidebarlogo_desc'] = '';
$string['customsidebardmlogo'] = 'Sidebar Logo (Dark mode)';
$string['customsidebardmlogo_desc'] = 'Additional logo for dark mode e.g white version';
$string['customstcontent'] = 'Custom Text (Top)';
$string['customstcontent_desc'] = 'Custom content between logo and main navigation.';
$string['customsmcontent'] = 'Custom Text (Middle)';
$string['customsmcontent_desc'] = 'Custom content between main navigation and "My Courses"';
$string['customnavitems'] = 'Custom Navigation Items';
$string['customnavitems_desc'] = 'You can add additional navigation item or custom HTML content. Down below you can find a sample code snippet of the navigation item.
<pre class="rui-pre">&lt;li class="rui-sidebar-nav-item" id="yui_3_17_2_1_1659561578352_26557"&gt;&lt;a href="#" id="itemContentBank" class="rui-sidebar-nav-item-link "&gt;&lt;span class="rui-sidebar-nav-icon"&gt;&lt;svg width="20" height="20" fill="none" viewBox="0 0 24 24"&gt;&lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.25 17.25V9.75C19.25 8.64543 18.3546 7.75 17.25 7.75H4.75V17.25C4.75 18.3546 5.64543 19.25 6.75 19.25H17.25C18.3546 19.25 19.25 18.3546 19.25 17.25Z"&gt;&lt;/path&gt;&lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 7.5L12.5685 5.7923C12.2181 5.14977 11.5446 4.75 10.8127 4.75H6.75C5.64543 4.75 4.75 5.64543 4.75 6.75V11"&gt;&lt;/path&gt;&lt;/svg&gt;&lt;/span&gt;&lt;span class="rui-sidebar-nav-text"&gt;Content bank&lt;/span&gt;&lt;/a&gt;&lt;/li&gt;</pre>
SVG icons:
<ul>
<li><a href="https://iconic.app/" target="_blank">iconic</a></li>
<li><a href="https://fontawesome.com/icons" target="_blank">FontAwesome</a></li>
<li><a href="https://css.gg/" target="_blank">css.gg</a></li>
<li><a href="https://coolicons.cool/" target="_blank">coolicons</a></li>
</ul>';
$string['customsfcontent'] = 'Custom Text (Bottom)';
$string['customsfcontent_desc'] = 'Custom content under "My Courses" section. <br /><div class="mt-3 mb-2">Sample code snippet <span class="badge badge-sq badge-warning">remember to switch Atto editor to HTML view</span></div><pre class="rui-pre">&#x3C;hr class=&#x22;hr-small&#x22;&#x3E;
&#x3C;div class=&#x22;ml-2&#x22;&#x3E;
    &#x3C;span class=&#x22;d-inline-flex mb-2&#x22;&#x3E;
        &#x3C;span class=&#x22;mr-2 ml-1&#x22;&#x3E;&#x3C;svg width=&#x22;28&#x22; height=&#x22;28&#x22; fill=&#x22;none&#x22; viewBox=&#x22;0 0 24 24&#x22;&#x3E;
                &#x3C;path stroke=&#x22;currentColor&#x22; stroke-linecap=&#x22;round&#x22; stroke-linejoin=&#x22;round&#x22; stroke-width=&#x22;1.5&#x22; d=&#x22;M6.75 6.75C6.75 5.64543 7.64543 4.75 8.75 4.75H15.25C16.3546 4.75 17.25 5.64543 17.25 6.75V19.25L12 14.75L6.75 19.25V6.75Z&#x22;&#x3E;&#x3C;/path&#x3E;
            &#x3C;/svg&#x3E;&#x3C;/span&#x3E;
        &#x3C;p class=&#x22;rui-block-text rui-block-text--2&#x22;&#x3E;Learn more about the theme&#x3C;br&#x3E;&#x3C;a href=&#x22;#&#x22; target=&#x22;_blank&#x22;&#x3E;Documentation page&#x3C;/a&#x3E;.
        &#x3C;/p&#x3E;
    &#x3C;/span&#x3E;
    &#x3C;div class=&#x22;ml-5&#x22;&#x3E;
        &#x3C;p class=&#x22;rui-block-text--light rui-block-text--3&#x22;&#x3E;Space (2.3.8) is fully compatible with moodle 4.0 and later. Theme is not compatible with Space 1.x.&#x3C;/p&#x3E;&#x3C;a href=&#x22;#&#x22; class=&#x22;mt-2 rui-block-text--3&#x22; target=&#x22;_blank&#x22;&#x3E;Get this theme for just $99&#x3C;/a&#x3E;
    &#x3C;/div&#x3E;
&#x3C;/div&#x3E;</pre>';
$string['turnoffsidebarfp'] = 'Turn off the sidebar (Front Page)';
$string['turnoffsidebarfp_desc'] = '';
$string['turnoffsidebardashboard'] = 'Turn off the sidebar (Dashboard)';
$string['turnoffsidebardashboard_desc'] = '';
$string['turnoffsidebarcourse'] = 'Turn off the sidebar (Course Page)';
$string['turnoffsidebarcourse_desc'] = '';
$string['turnoffsidebarincourse'] = 'Turn off the sidebar (In-course Page)';
$string['turnoffsidebarincourse_desc'] = '';
$string['turnoffsidebarreport'] = 'Turn off the sidebar (Report Page)';
$string['turnoffsidebarreport_desc'] = '';
$string['turnoffsidebarstandard'] = 'Turn off the sidebar (Standard Page)';
$string['turnoffsidebarstandard_desc'] = 'e.g Calendar, Private Files';
$string['turnoffsidebaradmin'] = 'Turn off the sidebar (Admin Page)';
$string['turnoffsidebaradmin_desc'] = '';
$string['hturnoffsidebar'] = 'Turn off the sidebar';
$string['hturnoffsidebar_desc'] = 'You can turn off the sidebar on specific pages.';
$string['hsidebarcolors'] = 'Sidebar Color Customization';
$string['hsidebarcolors_desc'] = '';
$string['colordrawerbg'] = 'Sidebar Background';
$string['colordrawertext'] = 'Sidebar Text';
$string['colordrawernavcontainer'] = 'Sidebar Navigation Box';
$string['colordrawernavbtntext'] = 'Sidebar Button Text';
$string['colordrawernavbtnicon'] = 'Sidebar Button Icon';
$string['colordrawernavbtniconh'] = 'Sidebar Button Icon (Hover)';
$string['colordrawernavbtntexth'] = 'Sidebar Button Text (Hover)';
$string['colordrawernavbtnbgh'] = 'Sidebar Button Background (Hover/Active)';
$string['colordrawernavbtntextlight'] = 'Sidebar Button Text (Light)';
$string['colordrawerscrollbar'] = 'Sidebar Scrollbar Color';
$string['colordrawerlink'] = 'Sidebar Link Color';
$string['colordrawerlinkh'] = 'Sidebar Link Color (Hover)';

$string['hfootersocial'] = 'Footer Social Icons';
$string['hfootersocial_desc'] = '';
$string['showsociallist'] = 'Display footer social list';
$string['showsociallist_desc'] = 'Check this field to show up social icons list.';
$string['website'] = 'Website Title';
$string['website_desc'] = 'Main company Website Title';
$string['cwebsiteurl'] = 'Website URL';
$string['cwebsiteurl_desc'] = 'Main company Website';
$string['mobile'] = 'Mobile';
$string['mobile_desc'] = 'Enter Mobile Number';
$string['mail'] = 'E-Mail';
$string['mail_desc'] = 'Enter E-Mail ID';
$string['facebook'] = 'Facebook URL';
$string['facebook_desc'] = 'Enter the URL of your Facebook. (i.e http://www.facebook.com/moodlehq)';
$string['customsocialicon'] = 'More icons';
$string['customsocialicon_desc'] = '<br>SVG icons:
<ul>
<li><a href="https://fontawesome.com/icons" target="_blank">FontAwesome</a></li>
<li><a href="https://iconic.app/" target="_blank">iconic</a></li>
<li><a href="https://css.gg/" target="_blank">css.gg</a></li>
<li><a href="https://coolicons.cool/" target="_blank">coolicons</a></li>
</ul>';
$string['twitter'] = 'Twitter URL';
$string['twitter_desc'] = 'Enter the URL of your twitter. (i.e http://www.twitter.com/moodlehq)';
$string['linkedin'] = 'Linkedin URL';
$string['linkedin_desc'] = 'Enter the URL of your LinkedIn. (i.e http://www.linkedin.com/moodlehq)';
$string['youtube'] = 'Youtube URL';
$string['youtube_desc'] = 'Enter the URL of your Youtube. (i.e https://www.youtube.com/user/moodlehq)';
$string['instagram'] = 'Instagram URL';
$string['instagram_desc'] = 'Enter the URL of your Instagram. (i.e https://www.instagram.com/moodlehq)';

$string['hidecourseindexnav'] = 'Hide Course Index Navigation';
$string['hidecourseindexnav_desc'] = 'Hide or show the Course Index drawer navigation.';

// Additional Top bar Button.
$string['htopbaradditionalbtn'] = 'Additional Button';
$string['htopbaradditionalbtn_desc'] = 'The button will be displayed next to the "Log in" button, only for non-logged-in users.';
$string['topbaradditionalbtn'] = 'Additional Button *';
$string['topbaradditionalbtn_desc'] = '<img src="{$a->siteurl}/theme/baz/doc/topbar-additionalbtn.png" class="mt-2 rounded" style="max-height: 100px; width: auto; max-width: 100%;" alt="Top Bar - Additional button" />
<br /><h5 class="h5 mb-2">How to revert the buttons?</h5>
<p>Add this custom CSS code to: Advanced - Raw SCSS</p>
<pre class="rui-pre mt-2">.notloggedin .rui-icon-menu { flex-direction: row-reverse; }</pre>';
$string['stopbaradditionalbtn'] = 'Button Label';
$string['stopbaradditionalbtn_desc'] = '<span class="badge-sq badge-info mb-2">FAQ</span> <ul><li><span>Displaying text in multiple languages </span><a class="underline--anim" href="https://docs.moodle.org/400/en/Multi-language_content_filter" target="_blank">Learn more</a></li></ul>';
$string['topbaradditionalbtnurl'] = 'Button URL';
$string['topbaradditionalbtnurl_desc'] = 'Button URL';
$string['topbaradditionalbtnhtml'] = 'Custom HTML';
$string['topbaradditionalbtnhtml_desc'] = 'You can use also custom HTML code';

$string['customfloatingbtn'] = 'Custom Floating Button';
$string['customfloatingbtn_desc'] = '<pre class="rui-pre">&lt;a href="#" data-toggle="tooltip" title="Donate" data-placement="left"&gt;
&lt;svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"&gt;
  &lt;circle cx="12" cy="12" r="7.25" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"&gt;&lt;/circle&gt;
  &lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.25 8.75H11.375C10.4775 8.75 9.75 9.47754 9.75 10.375V10.375C9.75 11.2725 10.4775 12 11.375 12H12.625C13.5225 12 14.25 12.7275 14.25 13.625V13.625C14.25 14.5225 13.5225 15.25 12.625 15.25H9.75"&gt;&lt;/path&gt;
  &lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 7.75V8.25"&gt;&lt;/path&gt;
  &lt;path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15.75V16.25"&gt;&lt;/path&gt;
&lt;/svg&gt;
&lt;/a&gt;</pre>';

$string['bodybgimg'] = 'Background image';
$string['bodybgimg_desc'] = 'The image to display as a background of the site. The background image you upload here will override the background image in your theme preset files.';

$string['editbtntop'] = 'Edit mode button on the top bar';
$string['editbtntop_desc'] = '<img src="{$a->siteurl}/theme/baz/doc/topbar-editmode.png" class="mt-2 rounded" style="max-height: 100px; width: auto; max-width: 100%;" alt="Edit mode" />';

$string['backtotopbutton'] = 'Go to top';
$string['backtotop'] = 'Back to top button';
$string['backtotop_desc'] = '';

$string['forcefwvideo'] = 'Force full-width video';
$string['forcefwvideo_desc'] = 'Turn off this option to revert to the default Moodle video size settings.
<br /><img src="{$a->siteurl}/theme/baz/doc/video-size.jpg" class="img-fluid rounded my-3" style="max-height: 200px; width: auto; max-width: 100%;" alt="Space Moodle Theme" />';

$string['settingsdashboard'] = 'Dashboard';
$string['setdashboardlayout'] = 'Dashboard Page Layout';
$string['dashboardblock1'] = 'Dashboard HTML Block #1 (On the top)';
$string['dashboardblock1_desc'] = 'To add a file or a background please use the "Additional resources field" (Advanced tab)';
$string['dashboardblock2'] = 'Dashboard HTML Block #2 (On the bottom)';
$string['dashboardblock2_desc'] = 'To add a file or a background please use the "Additional resources field" (Advanced tab)';

// Dashboard banner carousel.
$string['hdashboardbanner'] = 'Dashboard Banner';
$string['hdashboardbanner_desc'] = 'A rotating banner carousel shown at the top of the Dashboard. Configure the number of slides, the transition effect and rotation speed, then upload an image (and optionally a link) for each slide.';
$string['displaydashboardbanner'] = 'Show dashboard banner';
$string['displaydashboardbanner_desc'] = 'Enable to display the banner carousel on the Dashboard.';
$string['dashboardbannercount'] = 'Number of banner slides';
$string['dashboardbannercount_desc'] = 'How many slides to display (minimum 1, maximum 5). Save to reveal the per-slide fields.';
$string['dashboardbannereffect'] = 'Transition effect';
$string['dashboardbannereffect_desc'] = 'The animation used when moving between slides.';
$string['dashboardbannerdelay'] = 'Rotation delay (milliseconds)';
$string['dashboardbannerdelay_desc'] = 'Time each slide is shown before advancing, in milliseconds (e.g. 5000 = 5 seconds).';
$string['hdashboardbannerslide'] = 'Banner slide';
$string['hdashboardbannerslide_desc'] = '';
$string['dashboardbannerimg'] = 'Banner image';
$string['dashboardbannerimg_desc'] = 'The image shown for this slide. Required - slides without an image are skipped.';
$string['dashboardbannerlink'] = 'Banner link (optional)';
$string['dashboardbannerlink_desc'] = 'Optional URL the slide links to. Leave empty for a non-clickable image. Opens in a new tab.';
$string['dashboardbanneralt'] = 'Image alt text (optional)';
$string['dashboardbanneralt_desc'] = 'Alternative text for the image, used by screen readers.';
$string['dashboardbannerorder'] = 'Slide order';
$string['dashboardbannerorder_desc'] = 'Drag the slide cards into the order they should appear on the dashboard (first card shows first), then save. Reordering here does not move the uploaded images between slots below.';
$string['dashboardbannerslidelabel'] = 'Slide {$a}';
$string['dashboardbannermove'] = 'Move slide {$a}';
$string['dashboardbannernoimg'] = 'No image';
$string['dashboardbannermode'] = 'Show to';
$string['dashboardbannermode_desc'] = 'Which students see this slide, based on their Cohort/Batch. Choose "Only selected batches" or "All batches except" to target the batches picked below.';
$string['dashboardbannermode_all'] = 'All students';
$string['dashboardbannermode_only'] = 'Only selected batches';
$string['dashboardbannermode_except'] = 'All batches except selected';
$string['dashboardbannercohorts'] = 'Batches';
$string['dashboardbannercohorts_desc'] = 'The Cohort/Batch values this slide targets or excludes (per the "Show to" setting above). Ignored when "Show to" is "All students". Hold Ctrl/Cmd to select more than one.';

$string['settingsmycourses'] = 'My Courses';
$string['mycoursesblock1'] = 'My Courses HTML Block #1 (On the top)';
$string['mycoursesblock1_desc'] = 'To add a file or a background please use the "Additional resources field" (Advanced tab)';
$string['mycoursesblock2'] = 'My Courses HTML Block #2 (On the bottom)';
$string['mycoursesblock2_desc'] = 'To add a file or a background please use the "Additional resources field" (Advanced tab)';

// My Courses page header and cards. "In progress" / "Completed" tile labels
// reuse block_myoverview and core completion strings for free translations.
$string['welcomeback'] = 'Welcome back, {$a}';
$string['mycoursesheadersub'] = 'Pick up where you left off and keep making progress.';
$string['totalcourses'] = 'This Semester';
$string['continuelearning'] = 'Continue learning';
$string['nocoursesheading'] = 'No courses yet';
$string['nocoursesbody'] = 'Once you are enrolled in a course it will appear here, ready for you to start learning.';

$string['settingsfiles'] = 'Files';
$string['additionalresheading'] = 'Additional Files Area';
$string['additionalresheading_desc'] = '<p class="lead-4">With this setting you can upload additional resources to the theme. The advantage of uploading files to this file area is that those files can be delivered without a check if the user is logged in. This is also why you should only add files that are uncritical and everyone should be allowed to access and don\'t need be protected with a valid login. As soon as you have uploaded at least one file to this filearea and have stored the settings, a list will appear underneath which will give you the URL which you can use to reference a particular file.</p>';


// Course Page - Secondary Navigation.
$string['settingscoursesnav'] = 'Course Page - Secondary Navigation';
$string['secnavgroupitem'] = 'Include Groups Item to the Secondary Navigation';
$string['secnavgroupitem_desc'] = 'For users who have capabilities such as moodle/course:managegroups. <br /><img src="{$a->siteurl}/theme/baz/doc/secondary-nav-course-tmpl.png" width="w-100 rounded" style="max-height:150px;" />';

$string['secnavitems'] = 'Turn on additional menu items';
$string['secnavitems_desc'] = 'Select and save to turn on additional options for customizing secondary navigation.';

$string['hsecnavitem'] = 'Item';
$string['hsecnavitem_desc'] = '';

$string['secnavitemscount'] = 'Number of custom items';
$string['secnavitemscount_desc'] = '';

$string['secnavcustomnavlabel'] = 'Label';
$string['secnavcustomnavlabel_desc'] = '';

$string['secnavcustomnavurl'] = 'URL';
$string['secnavcustomnavurl_desc'] = 'To add course ID to the URL, just use {{courseID}}. eg. https://localhost/group/index.php?id={{courseID}}';

$string['secnavuserrole1'] = 'Select user role';
$string['secnavuserrole1_desc'] = 'Add restrictions depends on user roles.';
$string['secnavuserrole'] = 'Select user role';
$string['secnavuserrole_desc'] = 'Add restrictions depends on user roles.';
$string['secnavuserroles'] = 'Select user role';
$string['secnavuserroles_desc'] = 'Add restrictions depends on user roles.';

$string['stringshowhidden'] = 'Show hidden courses';
$string['stringshowhidden_desc'] = 'For users who get permission to see hidden courses.<ul class="mt-2"><li><span>Displaying text in multiple languages </span><a class="underline--anim" href="https://docs.moodle.org/400/en/Multi-language_content_filter" target="_blank">Learn more</a></li></ul>';
$string['stringshowonlyinprogress'] = 'Only courses in progress';
$string['stringshowonlyinprogress_desc'] = '<ul><li><span>Displaying text in multiple languages </span><a class="underline--anim" href="https://docs.moodle.org/400/en/Multi-language_content_filter" target="_blank">Learn more</a></li></ul>';

$string['displayfilterhiddenchbx'] = 'Display Hidden Courses Toggle';
$string['displayfilterhiddenchbx_desc'] = '';

$string['courselangbadge'] = 'Course Language Badge (Course Card)';
$string['courselangbadge_desc'] = '<p>If you have made the course language, you can display a badge for it. (Course - Settings - Appearance - Force Language).</p><img src="'.$siteurl.'/theme/baz/doc/course-lang-badge.png" class="img-fluid mt-2 rounded" width="100%" alt="Course Simple List" />';

$string['cccsummary'] = 'Display Course Summary (Course Card)';
$string['cccsummary_desc'] = '';

$string['showccoursedate'] = 'Display Course Start/End Date (Course Card)';
$string['showccoursedate_desc'] = '';

$string['ipcoursedetails'] = 'Display Course Details (Main Course Page)';
$string['ipcoursedetails_desc'] = 'Custom Course Fields.';
$string['ipcoursedates'] = 'Display Course Start/End Date (Course Index Page)';
$string['ipcoursedates_desc'] = 'Course start date, Modification date and Course end date';

$string['showcustomfields'] = 'Show Custom Fields (Course Card)';
$string['showcustomfields_desc'] = '';

// Enrollment Page
$string['henrollment'] = '';
$string['henrollment_desc'] = '<p class="lead-3 mb-4">Enrollment Page</p>
<p class="lead-4">Custom Fields (visible only on the enrollment page)</p>
<p class="my-3"><code>Site administration -> Courses -> Default settings -> Course custom fields</code></p>
<ol>
<li><strong>Add a Text Area field</strong></li>
<li><strong>Name:</strong> "Custom description on enrollment page" or any other</li>
<li><strong>Shortname:</strong> enrolldesc ((It\'s really important not to change this name)</li></ol>';

$string['settingsenrollmentpage'] = 'Enrollment Page';
$string['ecoursesummary'] = 'Display course summary (Enrollment Page)';
$string['ecoursesummary_desc'] = '';
$string['ecoursedetails'] = 'Display Course Details (Enrollment Page)';
$string['ecoursedetails_desc'] = 'Course start date, Modification date and Course end date';
$string['ecoursedates'] = 'Display Course Start/End Date (Enrollment Page)';
$string['ecoursedates_desc'] = 'Course start date, Modification date and Course end date';

// E-mail Template Branding.
$string['email_tab_title'] = 'Email';
$string['email_general_heading'] = 'General';

$string['customemailtmpl'] = 'Email Custom Template';
$string['customemailtmpl_desc'] = '';

// Logo.
$string['email_logo_heading'] = 'Logo';
$string['email_logo_file'] = 'Upload logo for email template.';
$string['email_logo_file_desc'] = 'SVG logo is not allowed.';
$string['email_header_file'] = 'Email header banner';
$string['email_header_file_desc'] = 'Banner across the top of every email. Leave empty to use the shipped banner, served from this site\'s own address.';
$string['email_footer_file1'] = 'Email footer banner';
$string['email_footer_file1_desc'] = 'First footer image, linked to this site.';
$string['email_footer_file2'] = 'Email footer banner (second)';
$string['email_footer_file2_desc'] = 'Second footer image, shown beside the first.';
$string['email_logo_height'] = 'Height of logo';
$string['email_logo_alt'] = 'Logo alt text';
$string['email_logo_href'] = 'Logo link';
$string['email_preview'] = '';
$string['email_preview_desc'] = '<p class="lead-3 mb-4">Custom E-Mail Template</p><strong>eMail Test Plugin</strong> - <a href="https://moodle.org/plugins/local_mailtest" target="_blank">Download</a>
<br />Moodle eMailTest is a powerful tool that enables administrators to test Moodle\'s email system with ease.
<br /><br /><strong>Once you have added any options, please make sure to save your changes and clear the cache.</strong><br />(Go to: Dashboard → Site administration → Development → Purge all cache)';

// Footer.
$string['email_footer_heading'] = 'Footer';

$string['email_footer_text'] = 'Footer text';
$string['email_footer_copyright'] = 'Copyright';
$string['email_footer_displaylogo'] = 'Display logo';

// Typography.
$string['email_typo_heading'] = 'Typography';

$string['email_typo_heading'] = 'Typography';
$string['email_typo_fontfamily'] = 'Font-family';

$string['email_typo_text_fontsize'] = 'Body font size';
$string['email_typo_text_color'] = 'Body font color';

$string['email_typo_link_color'] = 'Link font color';

$string['email_typo_footer_fontsize'] = 'Footer font size';
$string['email_typo_footer_color'] = 'Footer font color';

$string['customemailsettings'] = 'Custom E-mail';

$string['rolemanager'] = 'Manager';
$string['rolecoursecreator'] = 'Course Creator';
$string['roleeditingteacher'] = 'Teacher (editing)';
$string['rolenoneditingteacher'] = 'Teacher (non-editing)';
$string['roleguest'] = 'Guest';
$string['rolestudent'] = 'Student';
$string['roleauthenticateduser'] = 'Authenticated user';

// Settings: Resources tab.
// Credits: Boost_Union
$string['resourcestab'] = 'Resources';
$string['resourcescachecontrolnote'] = 'Please note that the files are shipped to the browser with the \'Cache-Control\' header set which tells the browser to cache the file. If you are sure that you won\'t change the file in the near future, you can use the persistent URL to link to the file. However, if you plan to modify a file but keep the same filename every now and then, you should rather use the revisioned URL and re-link the file where you have used it everytime you update the file to avoid that the browsers will show cached outdated versions of the file.';
// Section: Additional resources.
$string['additionalresourcesheading'] = 'Additional resources';
// Setting: Additional resources.
$string['additionalresourcessetting'] = 'Additional resources';
$string['additionalresourcessetting_desc'] = '';
// Information: Additional resources list.
$string['additionalresourceslistsetting'] = '';
$string['additionalresourceslistsetting_desc'] = '<h4 class="lead-4">Additional resources list</h4>This is the list of files which you have uploaded to the additional resources filearea.
The given URLs can be used to link to these files from within your custom CSS, from the footnote or wherever you need to use uploaded files but can\'t upload files in place.';
$string['additionalresourcesfileurlpersistent'] = 'URL (persistent)';
$string['additionalresourcesfileurlrevisioned'] = 'URL (revisioned)';
// Section: Custom fonts.
$string['customfontsheading'] = 'Custom fonts';
// Setting: Custom fonts.
$string['customfontssetting'] = 'Custom fonts';
$string['customfontssetting_desc'] = 'With this setting you can upload custom fonts to the theme. The advantage of uploading fonts to this file area is that those fonts can be delivered without a check if the user is logged in and can be used as locally installed fonts everywhere on the site. As soon as you have uploaded at least one font to this filearea and have stored the settings, a list will appear underneath which will give you CSS code snippets which you can use as a boilerplate to reference particular fonts in your custom SCSS.';
// Information: Custom fonts list.
$string['customfontslistsetting'] = 'Custom fonts list';
$string['customfontslistsetting_desc'] = 'This is the list of fonts which you have uploaded to the custom fonts filearea. The given CSS snippets can be used to add these fonts to your custom SCSS. Please note that you will have to take care of the font format value as well as the font-family, font-style and font-weight CSS properties yourself for now as Boost Union is not able yet to parse the font files.';
$string['customfontsfileurlpersistent'] = 'URL (persistent)';
$string['customfontsfileurlrevisioned'] = 'URL (revisioned)';

// The email strings below are built from live theme settings, which means DB
// reads and a theme_config::load() at lang-file INCLUDE time.
//
// core\plugin_manager loads every plugin's lang strings during a FRESH INSTALL,
// before the config table exists, so doing that unguarded aborts
// admin/cli/install_database.php outright with "Table config does not exist" --
// the entire install killed from inside a lang file, with a stack trace that
// points at plugin_manager rather than here.
//
// Found standing up learningshala on 2026-09-16. Nothing had hit it before: nitk
// was installed before this block existed, and every other tenant was adopted
// with a populated database.
if (during_initial_install()) {
    // No settings to read yet, and no mail is sent during an install. Emit inert
    // values so nothing can render a raw [[tmpl_*]] placeholder.
    $string['tmple_email_header_img'] = '';
    $string['tmple_email_footer_img1'] = '';
    $string['tmple_email_footer_img2'] = '';
    $string['tmpl_footer_copyright'] = '';
    $string['tmpl_footer_text'] = '';
    $string['tmple_typography_link_color'] = '';
    $string['tmple_typography_link_color_h'] = '';
    $string['tmple_typography_footer_fontsize'] = '';
    $string['tmple_typography_footer_color'] = '';
    $string['tmple_typography_text_fontsize'] = '';
    $string['tmple_typography_fontfamily'] = '';
    $string['tmple_typography_text_color'] = '';
    $string['tmpl_logo_img'] = '';
    $string['email_footer_displaylogo_ch'] = '';
} else {
global $CFG, $PAGE, $OUTPUT;
$logo_img = '';
$theme = theme_config::load('baz');


    if(!empty(get_config('theme_baz', 'email_logo_file'))) {
        $logo_img = 'https:' . $PAGE->theme->setting_file_url('email_logo_file', 'email_logo_file');
    } else {
        $logo_img = 'https:' . $PAGE->theme->setting_file_url('customlogo', 'customlogo');
    }
    
    // Email header/footer artwork.
    //
    // A site gets ITS OWN uploaded artwork, or nothing at all. There is
    // deliberately no fallback: this used to fall back to art shipped in the
    // theme, and that art is one institution's, so every tenant that had
    // uploaded none sent mail carrying somebody else's banner from its own
    // domain. A missing upload should look missing, not look like Jain.
    //
    // Returns a complete <img> tag, or an empty string. It has to be the whole
    // tag rather than a url because the template cannot conditionally omit an
    // element around a {{#str}} -- given a bare url, an unset setting would
    // render <img src=""> and most clients draw that as a broken image.
    $emailart = function(string $setting) {
        global $CFG, $PAGE;
        if (empty(get_config('theme_baz', $setting))) {
            return '';
        }
        // setting_file_url() is protocol-relative (//host/path). A browser
        // supplies the scheme; an email client has none, so the image silently
        // fails to load. Prepend it, as the email logo above already does.
        $url = (string) $PAGE->theme->setting_file_url($setting, $setting);
        if (strpos($url, '//') === 0) {
            $url = (stripos($CFG->wwwroot, 'https:') === 0 ? 'https:' : 'http:') . $url;
        }
        return '<img title="" alt="" data-imagetype="External" src="' . s($url)
             . '" style="max-width:100%; height:auto; border:0;">';
    };
    $string['tmple_email_header_img'] = $emailart('email_header_file');
    $string['tmple_email_footer_img1'] = $emailart('email_footer_file1');
    $string['tmple_email_footer_img2'] = $emailart('email_footer_file2');

    $footer_text = get_config('theme_baz', 'email_footer_text');
    $footer_copyright = get_config('theme_baz', 'email_footer_copyright');
    $email_logo_alt = get_config('theme_baz', 'email_logo_alt');
    $email_logo_height = get_config('theme_baz', 'email_logo_height');
    $email_logo_href = get_config('theme_baz', 'email_logo_href');
    $email_footer_displaylogo = get_config('theme_baz', 'email_footer_displaylogo');
    $email_typo_link_color = get_config('theme_baz', 'email_typo_link_color');
    $email_typo_footer_fontsize = get_config('theme_baz', 'email_typo_footer_fontsize');
    $email_typo_footer_color = get_config('theme_baz', 'email_typo_footer_color');
    $email_typo_text_fontsize = get_config('theme_baz', 'email_typo_text_fontsize');
    $email_typo_fontfamily = get_config('theme_baz', 'email_typo_fontfamily');
    $email_typo_text_color = get_config('theme_baz', 'email_typo_text_color');
    
    $string['tmpl_footer_copyright'] = $footer_copyright;
    $string['tmpl_footer_text'] = $footer_text;
    $string['tmple_typography_link_color'] = 'color:' . $email_typo_link_color. ';';
    $string['tmple_typography_link_color_h'] = $email_typo_link_color;
    $string['tmple_typography_footer_fontsize'] = 'font-size:' . $email_typo_footer_fontsize. ';';
    $string['tmple_typography_footer_color'] = 'color:' . $email_typo_footer_color. ';';
    $string['tmple_typography_text_fontsize'] = 'font-size:' . $email_typo_text_fontsize. ';';
    $string['tmple_typography_fontfamily'] = 'font-family:' . $email_typo_fontfamily. ';';
    $string['tmple_typography_text_color'] = 'color:' . $email_typo_text_color . ';';
    
    if(!empty($email_logo_href)) {
        $string['tmpl_logo_img'] = '<a href="' . $email_logo_href . '"><img src="'. $logo_img . '" height="' . $email_logo_height . '" alt="' . $email_logo_alt . '" title="' . $email_logo_alt . '" style="border:none;" /></a>';
    } else {
        $string['tmpl_logo_img'] = '<img src="'. $logo_img . '" height="' . $email_logo_height . '" alt="' . $email_logo_alt . '" title="' . $email_logo_alt . '" style="border:none;" />';
    }
    
    if($email_footer_displaylogo == 1) {
        if(!empty($email_logo_href)) {
            $string['email_footer_displaylogo_ch'] = '<a href="' . $email_logo_href . '"><img src="'. $logo_img . '" height="' . $email_logo_height . '" alt="' . $email_logo_alt . '" title="' . $email_logo_alt . '" style="border:none;" /></a>';
        } else {
            $string['email_footer_displaylogo_ch'] = '<img src="'. $logo_img . '" height="' . $email_logo_height . '" alt="' . $email_logo_alt . '" title="' . $email_logo_alt . '" style="border:none;" />';
        }
    } else {
        $string['email_footer_displaylogo_ch'] = '';
    }
}

// ---------------------------------------------------------------------------
// Student dashboard (bespoke, student-only view).
// ---------------------------------------------------------------------------
$string['enable_student_dashboard'] = 'Enable student dashboard';
$string['enable_student_dashboard_desc'] = 'Show the redesigned dashboard to student-only users on /my/. Teachers, managers, admins and verifiers always see the standard dashboard.';

// New student experience — master switch + per-view toggles.
$string['hstudentexperience'] = 'New student experience';
$string['hstudentexperience_desc'] = 'The bespoke, redesigned views shown to student-only users. The master switch turns the whole experience on or off; the per-view toggles let you disable an individual page without affecting the rest. Teachers, managers, admins and verifiers always see the standard pages. If the master switch is left untouched, the previous "Enable student dashboard" behaviour is preserved.';
$string['enable_student_experience'] = 'Enable new student experience (master)';
$string['enable_student_experience_desc'] = 'Master switch for all bespoke student views. When off, every student sees the standard Moodle pages.';
$string['student_view_dashboard'] = 'Dashboard';
$string['student_view_dashboard_desc'] = 'Show the redesigned dashboard (/my/). Only applies when the master switch is on.';
$string['student_view_courses'] = 'My courses';
$string['student_view_courses_desc'] = 'Show the redesigned My courses page (/my/courses.php). Only applies when the master switch is on.';
$string['student_view_calendar'] = 'Calendar';
$string['student_view_calendar_desc'] = 'Show the redesigned Calendar page (/calendar/view.php). Only applies when the master switch is on.';
$string['student_view_grades'] = 'Grades';
$string['student_view_grades_desc'] = 'Show the redesigned Grades overview page (/grade/report/overview/index.php). Only applies when the master switch is on.';
$string['student_view_coursepage'] = 'Course page';
$string['student_view_coursepage_desc'] = 'Show the redesigned course view page (/course/view.php) for student-only users. Teachers, editing users and other roles always see the standard course page. Only applies when the master switch is on.';

// Bespoke course-view page.
$string['cp_searchph'] = 'Search this course…';
$string['cp_complete'] = 'complete';
$string['cp_resume'] = 'Resume';
$string['cp_sections'] = '{$a} sections';
$string['cp_contents'] = 'Contents';
$string['cp_collapseall'] = 'Collapse all';
$string['cp_expandall'] = 'Expand all';
$string['cp_upnext'] = 'Up next';
$string['cp_yourprogress'] = 'Your progress';
$string['cp_mentor'] = 'Course mentor';
$string['cp_bookslot'] = 'Book a mentoring slot';
$string['cp_message'] = 'Message mentor';
$string['cp_keydates'] = 'Key dates';
$string['cp_announcements'] = 'Announcements';
$string['cp_empty'] = 'There is no content in this course yet.';
$string['cp_tab_course'] = 'Course';
$string['cp_tab_participants'] = 'Participants';
$string['cp_tab_grades'] = 'Grades';
$string['cp_tab_competencies'] = 'Competencies';
$string['cp_tab_badges'] = 'Badges';
$string['cp_unread'] = '{$a} new';
$string['cp_markdone'] = 'Mark as done';
$string['cp_markundone'] = 'Done — click to undo';
$string['cp_completed'] = 'Completed';
$string['cp_nottodo'] = 'Not yet completed';
$string['cp_auto_done'] = 'Completed automatically';
$string['cp_auto_pending'] = 'Tracked automatically — completes as you progress';
$string['cp_open'] = 'Open';
$string['cp_review'] = 'Review';
$string['cp_attempt'] = 'Attempt';
$string['cp_submit'] = 'Submit';
$string['cp_view'] = 'View';
$string['cp_join'] = 'Join';
$string['cp_book'] = 'Book';
$string['cp_watch'] = 'Watch';
$string['cp_rewatch'] = 'Rewatch';
$string['cp_locked'] = 'Locked';
$string['cp_liveclass'] = 'Live class';

// Bespoke per-course grade report.
$string['student_view_gradereport'] = 'Course grade report';
$string['student_view_gradereport_desc'] = 'Show the redesigned per-course grade report (/grade/report/user/index.php) for student-only users. Teachers, editing users and other roles always see the standard report. Only applies when the master switch is on.';
$string['gr_eyebrow'] = 'Your grades';
$string['gr_coursetotal'] = 'Course total';
$string['gr_gradedn'] = '{$a} graded';
$string['gr_pendingn'] = '{$a} pending';
$string['gr_graded'] = 'Graded';
$string['gr_pending'] = 'Pending';
$string['gr_assessments'] = 'Assessments';
$string['gr_subtotal'] = '{$a} total';
$string['gr_manualitem'] = 'Manual';
$string['gr_item'] = 'Item';
$string['gr_empty'] = 'There are no graded items in this course yet.';
$string['gr_col_assessment'] = 'Assessment';
$string['gr_col_type'] = 'Type';
$string['gr_col_weight'] = 'Weight';
$string['gr_col_grade'] = 'Grade';
$string['gr_col_range'] = 'Range';
$string['gr_col_performance'] = 'Performance';
$string['gr_col_status'] = 'Status';
$string['gr_actions'] = 'Actions';
$string['gr_analysis'] = 'Grade analysis';
$string['gr_openactivity'] = 'Open activity';
$string['gr_feedback'] = 'Feedback';

// Bespoke Grades overview (row design).
$string['gv_eyebrow'] = 'Your grades';
$string['gv_title'] = 'Grade overview';
$string['gv_sub'] = 'Your standing across every course you\'re enrolled in. Scores are running totals and update as each assessment is marked.';
$string['gv_graded'] = 'Graded so far';
$string['gv_inprogress'] = 'In progress';
$string['gv_highest'] = 'Highest score';
$string['gv_notgradedyet'] = 'Not graded yet';
$string['gv_gradedtag'] = 'Graded';
$string['gv_pendingtag'] = 'Pending';
$string['gv_other'] = 'Other enrolments';
$string['gv_notgraded'] = 'not graded';
$string['gv_empty'] = 'You have no graded courses yet.';
$string['gv_overviewreport'] = 'Overview report';
$string['gv_userreport'] = 'User report';

// Bespoke Student e-ID Card portal.
$string['student_view_idcard'] = 'Student e-ID Card';
$string['student_view_idcard_desc'] = 'Show the redesigned Student e-ID Card portal (/local/studentid/index.php) for eligible student-only users. Only applies when the master switch is on.';
$string['sid_eyebrow'] = 'Identity';
$string['sid_sub'] = 'Your official {$a} digital identity. Download it as a PDF or keep your details up to date.';
$string['sid_ready'] = 'Card ready';
$string['sid_identity'] = 'Student Identity';
$string['sid_usn'] = 'USN';
$string['sid_batch'] = 'Batch';
$string['sid_cardno'] = 'Card no.';
$string['sid_carddetails'] = 'Card details';
$string['sid_issued'] = 'Issued on';
$string['sid_program'] = 'Programme';
$string['sid_appstatus'] = 'Application status';
$string['sid_download'] = 'Download card (PDF)';
$string['sid_update'] = 'Update my details';
$string['sid_downloads'] = 'Downloads';
$string['sid_timesdl'] = 'Times downloaded';
$string['sid_sinceissue'] = 'since issue';
$string['sid_lastdl'] = 'Last downloaded';
$string['sid_history'] = 'Request history';
$string['sid_request_label'] = 'e-ID Card request';
$string['sid_submitted_on'] = 'Submitted {$a}';
$string['sid_created_on'] = 'Created {$a}';
$string['sid_request'] = 'Request your e-ID card';
$string['sid_none_title'] = 'You don\'t have an e-ID card yet';
$string['sid_none_sub'] = 'Apply for your official {$a} student identity card. It takes a few minutes and a short review.';
$string['sid_draft_title'] = 'Finish your application';
$string['sid_draft_sub'] = 'Your request is saved as a draft. Complete the required details and documents, then submit it for review.';
$string['sid_continue'] = 'Continue application';
$string['sid_submit'] = 'Submit for review';
$string['sid_review_title'] = 'Your application is being reviewed';
$string['sid_review_sub'] = 'Our team is verifying your details. You\'ll be notified here as soon as your e-ID card is ready.';
$string['sid_reapply_title'] = 'Request a new e-ID card';
$string['sid_reapply_sub'] = 'You can submit a fresh request with the corrected details below.';
$string['sid_rejected_title'] = 'Your last request needs changes';
$string['sif_newrequest'] = 'New request';
$string['sif_eyebrow'] = 'Identity · Application';
$string['sif_title'] = 'Request your e-ID card';
$string['sif_sub'] = 'Confirm your details, add your location and upload the required documents. We\'ll review your request and issue your card.';
$string['sif_back'] = 'Back to card';

// Bespoke wrapped pages (My Files, Profile).
$string['student_view_files'] = 'My Files';
$string['student_view_files_desc'] = 'Show /user/files.php inside the bespoke shell for student-only users (the file manager itself is unchanged). Only applies when the master switch is on.';
$string['student_view_profile'] = 'Profile';
$string['student_view_profile_desc'] = 'Show /user/profile.php inside the bespoke shell for student-only users (profile content is unchanged). Only applies when the master switch is on.';
$string['student_view_messages'] = 'Messages';
$string['student_view_messages_desc'] = 'Show /message/index.php inside the bespoke shell for student-only users (the messaging app itself is unchanged). Only applies when the master switch is on.';
$string['student_view_notifications'] = 'Notifications';
$string['student_view_notifications_desc'] = 'Show the all-notifications page inside the bespoke shell for student-only users (notification content is unchanged). Only applies when the master switch is on.';
$string['student_view_enrol'] = 'Course enrolment page';
$string['student_view_enrol_desc'] = 'Show the self-enrolment page (/enrol/index.php) inside the bespoke shell for student-only users, restyled as a course-enrolment card. The core enrol form (Enrol me + any enrolment key) is unchanged. Only applies when the master switch is on.';
$string['sw_enrol_title'] = 'Enrol in this course';
$string['sw_enrol_eyebrow'] = 'Course enrolment';
$string['sw_enroldesc_heading'] = 'About this course';
$string['show_endsem_exam'] = 'End-semester exam button';
$string['show_endsem_exam_desc'] = 'Show the highlighted gold "Start End-Sem Exam" button in the student dashboard welcome section. It links to the Examstack SSO handshake and is only ever shown to learners in the batches ticked below. Turn this off between exam cycles.';
$string['endsem_exam_batches'] = 'Exam batches';
$string['endsem_exam_batches_desc'] = 'The batches whose learners see the End-Sem Exam button, by their Cohort/Batch profile field. The list is every batch value currently in use on the site, with its active-learner count — tick the batches sitting exams this cycle and untick them afterwards. Spelling variants of one batch (for example "July, 2025" and "July 2025") share a single checkbox. This has no effect unless the button is switched on above; with nothing ticked, nobody sees the button.';
$string['endsem_exam_batch_choice'] = '{$a->batch} — {$a->count} learners';
$string['endsem_exam_batches_nofield'] = 'This site has no "Cohort/Batch" (cohort_batch) user profile field, so there are no exam batches to choose from.';
$string['student_view_activity'] = 'Activity pages';
$string['student_view_activity_desc'] = 'Apply the bespoke navy/gold restyle to activity pages (quiz, assignment, forum, lesson and all other modules) for student-only users. The activity content, navigation and JS are preserved — full-screen/external players (SCORM, VPL, LTI, Zoom, MS Teams, IMS) are left untouched. Only applies when the master switch is on.';
$string['student_view_badges'] = 'Badge pages';
$string['student_view_badges_desc'] = 'Show the redesigned badge pages (My Badges, single badge, and available course/site badges) as navy/gold cards for student-only users. All badge actions, downloads and routing are preserved. Teachers and other roles always see the standard badge pages. Only applies when the master switch is on.';
$string['student_view_blog'] = 'Blog pages';
$string['student_view_blog_desc'] = 'Show the redesigned blog pages (entry list and single entry) as journal cards for student-only users. All blog actions (add, edit, delete, comments) and routing are preserved. Teachers and other roles always see the standard blog. Only applies when the master switch is on.';
$string['student_view_eventreg'] = 'Event registration form';
$string['student_view_eventreg_desc'] = 'Show the event-registration form and open-forms list (local_eventreg) inside the redesigned student shell — sidebar/topbar and card + gold-button form format — for student-only users. The form fields, validation and one-per-user submission are unchanged. Teachers and other roles always see the standard page. Only applies when the master switch is on.';
$string['student_view_userprofile'] = 'Other users\' profiles';
$string['student_view_userprofile_desc'] = 'Show the redesigned profile page (a profile hero + detail cards) when a student-only user views another person\'s profile (classmate or teacher), reached from forum posts and messaging. Privacy and which details are visible are unchanged. Teachers and other roles always see the standard profile. Only applies when the master switch is on.';
$string['sw_blog_title'] = 'Blog';
$string['sw_blog_eyebrow'] = 'Journal';
// Generic wrap: pages that carry their own heading, so the title is taken from
// the page and these are only the fallback and the label above it.
$string['sw_generic_title'] = 'Notice';
$string['sw_generic_eyebrow'] = 'Your learning';
$string['sw_eventreg_title'] = 'Event registration';
$string['sw_eventreg_eyebrow'] = 'Register';
$string['sw_helpdesk_title'] = 'Support';
$string['sw_helpdesk_eyebrow'] = 'Help';
$string['sw_staff_eyebrow'] = 'Support desk';
$string['sw_badges_title'] = 'My Badges';
$string['sw_badges_eyebrow'] = 'Achievements';
$string['sw_badges_earned'] = 'earned';
$string['sw_badge_public'] = 'Public';
$string['sw_badge_private'] = 'Private';
$string['sw_files_title'] = 'My Files';
$string['sw_files_eyebrow'] = 'Your space';
$string['sw_profile_title'] = 'My Profile';
$string['sw_profile_eyebrow'] = 'Account';
// Label for the "Full name" row we add above Email in the profile User details card.
// Core's get_string('fullname') is deprecated and 'fullnameuser' reads "User full name".
$string['profile_fullname'] = 'Full Name';
$string['profile_emailaddress'] = 'Email Address';
$string['sw_messages_title'] = 'Messages';
$string['sw_messages_eyebrow'] = 'Inbox';
$string['sw_notifications_title'] = 'Notifications';
$string['sw_notifications_eyebrow'] = 'Activity';
$string['sw_prefs_title'] = 'Preferences';
$string['sw_prefs_eyebrow'] = 'Account settings';
$string['sw_pref_account'] = 'Account';
$string['sw_pref_learning'] = 'Learning preferences';
$string['sw_pref_messaging'] = 'Messaging & privacy';
$string['sw_pref_blogs'] = 'Blogs';
$string['sw_pref_more'] = 'More';
$string['sw_editprofile_title'] = 'Edit profile';
$string['sw_editprofile_eyebrow'] = 'Account';
$string['sw_password_title'] = 'Change password';
$string['sw_password_eyebrow'] = 'Account · Security';
// Change-password security card (theme_baz/changepassword).
$string['cp_hero_title'] = 'Update your password';
$string['cp_hero_sub'] = 'You will stay signed in on this device.';
$string['cp_req_len'] = 'At least {$a} characters';
$string['cp_req_digit'] = 'At least {$a} digit(s)';
$string['cp_req_lower'] = 'At least {$a} lowercase letter(s)';
$string['cp_req_upper'] = 'At least {$a} uppercase letter(s)';
$string['cp_req_special'] = 'At least {$a} special character (* - # …)';
$string['cp_strength'] = 'Password strength';
$string['cp_match_ok'] = 'Passwords match';
$string['cp_match_no'] = 'Passwords don\'t match yet';
$string['cp_show'] = 'Show password';
$string['cp_hide'] = 'Hide password';
$string['cp_weak'] = 'Weak';
$string['cp_fair'] = 'Fair';
$string['cp_good'] = 'Good';
$string['cp_strong'] = 'Strong';
$string['sw_seeallmsg'] = 'See all messages';
$string['student_view_account'] = 'Account pages';
$string['student_view_account_desc'] = 'Show the bespoke navy/gold shell around the Preferences, Edit profile and Change password pages for student-only users. Requires the master student-experience setting to be on.';

// Bespoke pre-auth (login + forgot-password).
$string['bespoke_login'] = 'Bespoke login & forgot-password design';
$string['bespoke_login_desc'] = 'Render the login and forgot-password pages in the bespoke split-screen navy/gold design (uses the existing login background image, username/password only). Applies to everyone. Falls back to the standard login below on any error.';
$string['bespoke_frontpage'] = 'Bespoke front page (logged out)';
$string['bespoke_frontpage_desc'] = 'For not-logged-in / guest visitors, show a branded gateway on the site front page — the "Learn Beyond Borders" hero on the login-navy background with a single "Log in to learn" button — instead of the standard front page. Logged-in users are unaffected. Falls back to the standard front page on any error.';
$string['frontbanner'] = 'Front page hero image';
$string['frontbanner_desc'] = 'Artwork for the bespoke front page hero. Leave empty to use the image shipped with the theme. That shipped image has wording baked into it, so a site with its own branding should upload its own here rather than inherit another site\'s copy. Recommended size 1220x700; the bottom-left corner is left clear for the log-in button.';
$string['brandnamefull'] = 'Institution name (full)';
$string['brandnamefull_desc'] = 'The institution\'s full legal name, used in the copyright line. Leave empty to use the site full name.';
$string['brandnameshort'] = 'Institution name (short)';
$string['brandnameshort_desc'] = 'Short form used in running text, for example "NITK · Secure sign-in". Leave empty to use the site full name.';
$string['brandnavy'] = 'Brand colour: primary';
$string['brandnavy_desc'] = 'The dark primary colour, used for the sidebar, hero panels and sign-in. Leave empty to keep the shipped palette. Set this together with the accent: the whole palette of gradients and tints is derived from the two, so setting only one has no effect.';
$string['brandaccent'] = 'Brand colour: accent';
$string['brandaccent_desc'] = 'The single accent colour, used for primary buttons, active navigation and highlights. It is a fill colour rather than a text colour, so pick something that carries dark text on top of it. Leave empty to keep the shipped palette.';
$string['help_lead'] = 'Tell us what is wrong and the support team will come back to you.';
$string['help_raise'] = 'Raise a ticket';
$string['help_track'] = 'Track your tickets';
$string['student_view_helpdesk'] = 'Student view: support pages';
$string['student_view_helpdesk_desc'] = 'Show the support ticket pages in the redesigned shell. Only applies where the helpdesk plugin is enabled.';
$string['staff_shell'] = 'Staff shell';
$string['staff_shell_desc'] = 'Render the redesigned interface for staff as well as students. Off by default: staff keep the standard layout until this is switched on. Site administrators always keep the standard administrative interface.';
$string['staff_shell_roles'] = 'Staff shell role mapping';
$string['staff_shell_roles_desc'] = 'Optional. One <code>roleshortname=audience</code> per line, where audience is pm, ta or faculty. Only needed for roles whose archetype does not describe them; manager, editingteacher and teacher are mapped automatically.';
$string['shell_group_programme'] = 'Programme';
$string['shell_group_teaching'] = 'Teaching';
$string['shell_group_support'] = 'Support';
$string['shell_nav_queue'] = 'Support queue';
$string['shell_nav_forwarded'] = 'Forwarded to me';
$string['shell_nav_learners'] = 'Learners';
$string['shell_nav_courses'] = 'Courses';
$string['shell_nav_mycourses'] = 'My courses';
$string['shell_nav_overview'] = 'Overview';
$string['shell_nav_grading'] = 'Grading';
$string['login_layout'] = 'Sign-in page layout';
$string['login_layout_desc'] = 'How the sign-in page is arranged. The split screen shows the artwork beside the form; the centred card places one card over a full-bleed photograph, using the sign-in artwork as the background rather than a framed image.';
$string['login_layout_split'] = 'Split screen (artwork beside the form)';
$string['login_layout_centered'] = 'Centred card over a full-bleed photograph';
$string['loginlogo'] = 'Sign-in card logo';
$string['loginlogo_desc'] = 'Logo shown on the sign-in card. The card uses the primary brand colour, so this usually needs a light or reversed version of the mark. Leave empty to fall back to the site logo, then to the site name as text. Only used by the centred layout.';
$string['lpc_signin'] = 'Sign in';
$string['lpc_showpassword'] = 'Show password';
$string['lpc_hidepassword'] = 'Hide password';
$string['lpc_forgotpassword'] = 'Forgot password?';
$string['fl_loginbtn'] = 'Log in to learn';
$string['fl_banneralt'] = 'Learn Beyond Borders — your journey to excellence begins here';
$string['fl_copyright'] = '© {$a->year} {$a->name}. All rights reserved.';
$string['lp_login_head'] = 'Log in to continue';
$string['lp_forgot_head'] = 'Reset your password';
$string['lp_foot'] = '{$a} · Secure sign-in';
$string['lp_blocked_badge'] = 'Access on hold';
$string['lp_blocked_head'] = 'You can\'t sign in right now';
$string['lp_blocked_back'] = 'Back to login';

// Bespoke "My courses" page.
$string['sc_title'] = 'My courses';
$string['sc_subtitle'] = 'Pick up where you left off and keep making progress.';
$string['sc_kpitotal'] = 'Enrolled';
$string['sc_kpisemester'] = 'This semester';
$string['sc_kpiinprogress'] = 'In progress';
$string['sc_kpicompleted'] = 'Completed';
$string['sc_continuelearning'] = 'Continue learning';
$string['sc_continue'] = 'Continue';
$string['sc_start'] = 'Start';
$string['sc_review'] = 'Review';
$string['sc_completed'] = 'Completed';
$string['sc_inprogress'] = 'In progress';
$string['sc_notstarted'] = 'Not started';
$string['sc_instructor'] = 'Instructor';
$string['sc_searchph'] = 'Search my courses…';
$string['sc_filter_all'] = 'All';
$string['sc_filter_inprogress'] = 'In progress';
$string['sc_filter_future'] = 'Future';
$string['sc_filter_past'] = 'Past';
$string['sc_filter_starred'] = 'Starred';
$string['sc_filter_removed'] = 'Removed';
$string['sc_groupby'] = 'Group';
$string['sc_group_semester'] = 'Semester';
$string['sc_sort'] = 'Sort';
$string['sc_sort_name'] = 'Course name';
$string['sc_sort_lastaccessed'] = 'Last accessed';
$string['sc_view_card'] = 'Card view';
$string['sc_view_list'] = 'List view';
$string['sc_view_summary'] = 'Summary view';
$string['sc_courses_count'] = '{$a} courses';
$string['sc_avg'] = 'avg {$a}%';
$string['sc_activities'] = '{$a} activities';
$string['sc_notopened'] = 'Not opened yet';
$string['sc_opened_ago'] = 'Opened {$a} ago';
$string['sc_opened_recent'] = 'Opened just now';
$string['sc_star'] = 'Star this course';
$string['sc_unstar'] = 'Unstar this course';
$string['sc_actions'] = 'Course actions';
$string['sc_movetop'] = 'Move to top';
$string['sc_moveup'] = 'Move up';
$string['sc_remove'] = 'Remove from view';
$string['sc_restore'] = 'Restore to view';
$string['sc_showall'] = 'Show all';
$string['sc_empty'] = 'No courses match this filter.';

// Bespoke Calendar page.
$string['cal_title'] = 'Calendar';
$string['cal_today'] = 'Today';
$string['cal_newevent'] = 'New event';
$string['cal_month'] = 'Month';
$string['cal_week'] = 'Week';
$string['cal_day'] = 'Day';
$string['cal_upcoming'] = 'Upcoming';
$string['cal_allcourses'] = 'All courses';
$string['cal_overdue'] = 'Overdue';
$string['cal_thisweek'] = 'This week';
$string['cal_nextweek'] = 'Next week';
$string['cal_later'] = 'Later';
$string['cal_join'] = 'Join';
$string['cal_open'] = 'Open';
$string['cal_view'] = 'View';
$string['cal_more'] = '+{$a} more';
$string['cal_empty'] = 'Nothing here.';
$string['cal_t_deadline'] = 'Deadlines';
$string['cal_t_course'] = 'Course';
$string['cal_t_live'] = 'Live class';
$string['cal_t_user'] = 'User';
$string['cal_t_site'] = 'Site';
$string['cal_help'] = 'Need help?';
$string['cal_tomorrow'] = 'Tomorrow';

// Bespoke Grades page.
$string['sg_title'] = 'Grades';
$string['sg_sub'] = "Track how you're doing across every course.";
$string['sg_avgpct'] = 'Avg %';
$string['sg_graded'] = 'Graded';
$string['sg_pending'] = 'Pending';
$string['sg_all'] = 'All';
$string['sg_gradedn'] = '{$a} graded';
$string['sg_avg'] = 'avg {$a}%';
$string['sg_nograded'] = 'Not yet graded';
$string['sg_breakdown'] = 'View breakdown';
$string['sg_coursetotal'] = 'Course total';
$string['sg_notgraded'] = 'Not graded yet';
$string['sg_instructor'] = 'Instructor';
$string['sg_empty'] = 'No grades to show yet.';

$string['sd_nav_dashboard'] = 'Dashboard';
$string['sd_nav_courses'] = 'My Courses';
$string['sd_nav_calendar'] = 'Calendar';
$string['sd_nav_grades'] = 'Grades';
$string['sd_nav_resources'] = 'Resources';
$string['sd_nav_files'] = 'Private Files';
$string['sd_nav_support'] = 'Support';
$string['sd_help'] = 'Need help?';
$string['sd_help_sub'] = 'Support & escalation';

$string['sd_askjo'] = 'Ask JO';
$string['sd_toggletheme'] = 'Toggle light / dark theme';
$string['sd_backtocourse'] = 'Back to course';
$string['sd_modules'] = 'Course modules';
$string['sd_instructions'] = 'Instructions';
$string['sd_morning'] = 'Good morning';
$string['sd_afternoon'] = 'Good afternoon';
$string['sd_evening'] = 'Good evening';
// Teacher dashboard.
$string['staff_view_dashboard'] = 'Teacher dashboard';
$string['staff_view_dashboard_desc'] = 'Show the bespoke teacher dashboard on /my/ for faculty and teaching-assistant staff audiences (requires the staff shell above to be on). Everything on it links into the existing grader / course / report screens — no workflow changes. Switch off to keep the classic dashboard for staff.';
$string['td_teacher'] = 'Teacher';
$string['td_nav_grading'] = 'Grading';
$string['td_nav_reports'] = 'Reports';
$string['td_nav_qbank'] = 'Question bank';
$string['td_searchph'] = 'Search your courses…';
$string['td_herosub'] = 'You have <b>{$a->grading}</b> submissions to grade and <b>{$a->classes}</b> live classes today across your <b>{$a->courses}</b> courses.';
$string['td_gradenow'] = 'Grade now';
$string['td_message'] = 'Message students';
$string['td_thisweek'] = 'This week';
$string['td_activelearners'] = 'Active learners';
$string['td_submissions'] = 'Submissions received';
$string['td_kpicourses'] = 'Courses teaching';
$string['td_kpistudents'] = 'Students enrolled';
$string['td_kpigrading'] = 'Awaiting grading';
$string['td_kpicompletion'] = 'Avg. completion';
$string['td_needsattention'] = 'Needs your attention';
$string['td_tograde'] = '{$a} to grade';
$string['td_nocourses'] = 'You are not teaching any courses yet. Courses you are assigned to will appear here.';
$string['td_nogradingpending'] = 'Nothing waiting to be graded — you\'re all caught up.';
$string['td_livetoday'] = 'Today\'s live classes';
$string['td_host'] = 'Host';
$string['td_nolivetoday'] = 'No live classes scheduled today.';
$string['td_yourcourses'] = 'Your courses';
$string['td_students'] = 'students';
$string['td_tocheck'] = 'to grade';
$string['td_pctcomplete'] = '{$a}% avg completion';
$string['td_grade'] = 'Grade';
$string['td_content'] = 'Content';
$string['td_people'] = 'People';
// Admin dashboard.
$string['admin_view_dashboard'] = 'Admin dashboard';
$string['admin_view_dashboard_desc'] = 'Show the bespoke admin dashboard on /my/ for site administrators (site overview, learner engagement, system health, quick actions, recent config changes). Replaces the classic dashboard for admins; every admin tool stays reachable via its links. Off by default.';
$string['ad_admin'] = 'Site Admin';
$string['ad_overview'] = 'System overview';
$string['ad_hero_ok'] = 'Good {$a} — everything looks healthy';
$string['ad_hero_warn'] = 'Good {$a->word} — {$a->count} item(s) need attention';
$string['ad_adduser'] = 'Add user';
$string['ad_createcourse'] = 'Create course';
$string['ad_kpi_users'] = 'Total users';
$string['ad_kpi_activetoday'] = 'Active today';
$string['ad_kpi_courses'] = 'Courses';
$string['ad_kpi_enrolments'] = 'Enrolments';
$string['ad_kpi_blocked'] = 'Blocked accounts';
$string['ad_engagement'] = 'Learner engagement';
$string['ad_activetoday'] = 'Active today';
$string['ad_wau'] = 'Weekly active';
$string['ad_mau'] = 'Monthly active';
$string['ad_stickiness'] = 'Stickiness · DAU/MAU (yesterday)';
$string['ad_dau_caption'] = 'Daily sign-ins · last 30 days';
$string['ad_health'] = 'System health';
$string['ad_health_ok'] = 'All systems OK';
$string['ad_health_attention'] = 'Needs attention';
$string['ad_h_cron'] = 'Cron';
$string['ad_h_failingtasks'] = 'Failing tasks';
$string['ad_h_none'] = 'None';
$string['ad_h_queue'] = 'Task queue';
$string['ad_h_queueempty'] = 'Empty';
$string['ad_h_queued'] = '{$a} queued';
$string['ad_h_nfailing'] = '{$a} failing';
$string['ad_h_oldestdays'] = 'oldest {$a}d';
$string['ad_h_failedlogins'] = 'Failed sign-ins (yesterday)';
$string['ad_h_plugins'] = 'Plugin updates';
$string['ad_manageusers'] = 'Manage users';
$string['ad_manageusers_s'] = 'Browse, bulk-upload, roles';
$string['ad_managecourses'] = 'Courses & categories';
$string['ad_managecourses_s'] = 'Create, restore, organise';
$string['ad_reports'] = 'Reports & logs';
$string['ad_reports_s'] = 'Live logs, config changes';
$string['ad_security'] = 'Security & policies';
$string['ad_security_s'] = 'Auth, access, data requests';
$string['ad_settings'] = 'Site administration';
$string['sd_herosub'] = 'You have <b>{$a->deadlines}</b> deadlines this week and <b>{$a->classes}</b> live classes coming up. Here\'s where to pick up.';
$string['sd_upnext'] = 'Up next';
$string['sd_exam_tag'] = 'End Semester Examination';
$string['sd_exam_title'] = 'Your end-semester exam is ready';
$string['sd_exam_sub'] = 'Launch the secure exam platform to begin. Keep your ID handy.';
$string['sd_exam_cta'] = 'Start Now';
$string['sd_exam_start'] = 'Start End Semester Exam';
$string['sd_exam_tagshort'] = 'EXAM';
$string['sd_remaining'] = 'remaining';
$string['sd_attempt'] = 'Open';
$string['sd_resume'] = 'Resume last activity';
$string['sd_joinclass'] = 'Join next class';

$string['sd_semprogress'] = 'Semester progress';
$string['sd_semcurrent'] = 'Semester {$a} · current';
$string['sd_semshort'] = 'Semester {$a}';
$string['sd_avgcomplete'] = 'avg. complete';
$string['sd_completed'] = 'Completed';
$string['sd_inprogress'] = 'In progress';
$string['sd_notstarted'] = 'Not started';

$string['sd_kpicourses'] = 'Active courses';
$string['sd_kpiprogress'] = 'Avg. progress';
$string['sd_kpideadlines'] = 'Deadlines this week';
$string['sd_kpiliveclasses'] = 'Live classes this week';

$string['sd_jumpback'] = 'Jump back in';
$string['sd_mycourses'] = 'Active courses';
$string['sd_allcourses'] = 'All {$a} courses →';

$string['sd_banner_alt'] = 'Announcement';
$string['sd_menu'] = 'Menu';
$string['sd_thisweek'] = 'This week';
$string['sd_activity'] = 'Activity';
$string['sd_focusnext'] = 'Focus next';
$string['sd_focusgo'] = 'Start';

$string['sd_deadlines'] = 'Deadlines · Next 7 days';
$string['sd_liveclasses'] = 'Live classes · This week';
$string['sd_open'] = 'Open';
$string['sd_join'] = 'Join';
$string['sd_act_quiz'] = 'Take quiz';
$string['sd_act_submit'] = 'Submit';
$string['sd_act_reply'] = 'Reply';
$string['sd_act_choose'] = 'Choose';
$string['sd_act_respond'] = 'Respond';
$string['sd_act_start'] = 'Start';
$string['sd_searchnoresults'] = 'No matches';

// Bespoke quiz view (renderer-quiz.php).
$string['sd_q_notattempted'] = 'Not yet attempted';
$string['sd_q_inprogress'] = 'Attempt in progress';
$string['sd_q_submitted'] = 'Submitted';
$string['sd_q_attemptsofmax'] = '{$a->used} of {$a->max} attempts used';
$string['sd_q_attemptsdone'] = '{$a} attempts made';
$string['sd_q_closeson'] = 'Closes {$a}';
$string['sd_q_availability'] = 'Availability';
$string['sd_q_opened'] = 'Opened';
$string['sd_q_closes'] = 'Closes';

// Bespoke forum post form (reply / edit / new discussion).
$string['sd_f_replyingto'] = 'Replying to';
$string['sd_f_editingpost'] = 'Editing your post';
$string['sd_f_newdiscussionin'] = 'New discussion in';

// Bespoke quiz header on attempt / summary / review.
$string['sd_q_chip_attempt'] = 'Quiz · Attempt';
$string['sd_q_chip_summary'] = 'Quiz · Summary';
$string['sd_q_chip_review'] = 'Quiz · Review';
$string['sd_q_qprogress'] = 'Question {$a->qnum} of {$a->total} · {$a->answered} answered';
$string['sd_q_answered'] = '{$a->answered} of {$a->total} answered';

// Bespoke assignment (mod_assign) student view.
$string['sd_a_todo'] = 'To do';
$string['sd_a_todo_msg'] = "You haven't submitted anything yet";
$string['sd_a_todo_sub'] = 'Due {$a}';
$string['sd_a_draft_msg'] = 'Saved as a draft — not submitted for marking';
$string['sd_a_draft_sub'] = 'Submit before {$a} to have it graded';
$string['sd_a_overdue'] = 'Overdue';
$string['sd_a_late_sub'] = 'Late submissions accepted until {$a}';
$string['sd_a_closed'] = 'Closed';
$string['sd_a_closed_sub'] = 'The cut-off date passed on {$a}';
$string['sd_a_graded_msg'] = 'Your work has been marked';
$string['sd_a_graded_sub'] = 'See your feedback below';

// Bespoke forum advanced-search form section headers.
$string['sd_fs_terms'] = 'Search terms';
$string['sd_fs_filter'] = 'Filter by';
$string['sd_fs_dates'] = 'Date range';

// Bespoke lesson end-of-lesson card.
$string['sd_l_complete'] = 'Lesson complete';
$string['sd_l_complete_sub'] = 'You\'ve finished “{$a}”.';

$string['student_view_interestzone'] = 'Interest Zone page';
$string['student_view_interestzone_desc'] = 'Show the bespoke navy/gold Interest Zone (clubs category) page to student-only users. Requires the master student-experience setting to be on.';

// Bespoke Interest Zone (clubs category) page.
$string['iz_kicker'] = '{$a} · Clubs';
$string['iz_subtitle'] = 'Explore student-run clubs and communities — pick what interests you, join, and get involved beyond the classroom.';
$string['iz_count'] = 'clubs and programs to explore';
$string['iz_explore'] = 'Explore';
$string['iz_open'] = 'Joined · Open';
$string['iz_empty'] = 'No clubs are available here yet. Check back soon.';

$string['sd_levelup'] = 'Level Up';
$string['sd_xp'] = 'XP';
$string['sd_xptonext'] = '{$a} XP to the next level';
$string['sd_xprank'] = 'You\'re ranked #{$a} in your cohort';

$string['sd_raiseticket'] = 'Raise a ticket';
$string['sd_contactsupport'] = 'Contact the support team';
$string['sd_footnote'] = '© {$a->year} {$a->name}. All rights reserved.';
$string['sd_hasevents'] = 'has events';
$string['sd_searchph'] = 'Search courses…';
$string['sd_streak'] = '{$a}-day streak';
$string['sd_announcements'] = 'Announcements';
$string['sd_businessnews'] = 'Business news';
$string['sd_podcasts'] = 'Podcasts';
$string['sd_supportsop'] = 'Guidelines & process';
$string['task_refreshadminengagement'] = 'Refresh admin dashboard engagement trend';
$string['task_refreshdashboardnews'] = 'Refresh student dashboard news feeds';
$string['mycoursesnavlimit'] = 'My Courses sidebar limit';
$string['mycoursesnavlimit_desc'] = 'Maximum number of courses listed in the My Courses sidebar. The list is rebuilt on every page, so an unbounded list is slow for accounts enrolled in very many courses. When the list is capped, a link to My courses is shown with the true total. Set to 0 for no limit.';
$string['mycoursesnavmore'] = 'View all {$a} courses';

// Bespoke admin pages shell (admin_wrap).
$string['admin_view_pages'] = 'Administration and staff pages in the bespoke shell';
$string['admin_view_pages_desc'] = 'Wrap the administration and staff pages in the bespoke navy shell: Site administration and its settings and category pages, the management screens, the reports, and the course administration screens, which get a course-scoped sidebar rather than the site one. Applies to site administrators and to staff audiences; students keep their own student-experience pages. Each page keeps its own content, forms, filters and JavaScript untouched — only the chrome and styling change. Off by default; falls back to the classic layout on any error.';
$string['gate_off'] = 'Off — keep the classic view';
$string['gate_pilot'] = 'Pilot accounts only';
$string['gate_all'] = 'On for everyone this applies to';
$string['gate_pilot_roster'] = 'Pilot accounts';
$string['gate_pilot_roster_desc'] = 'The accounts that see any bespoke view whose setting above is in pilot mode — the teacher dashboard, the admin dashboard and the administration pages shell all share this one list. One entry per line, or separated by commas; each may be a username, an email address or a numeric user id. Everyone else keeps the classic view, so a change can be checked against real work before it reaches the whole of staff. Pilot mode with an empty list shows the bespoke view to nobody.';
$string['aw_nav_overview'] = 'Overview';
$string['aw_nav_more'] = 'More in this course';
$string['aw_nav_yourarea'] = 'Your area';
$string['aw_searchsettings'] = 'Search settings';
$string['student_course_blocks'] = 'Blocks in the student course rail';
$string['student_course_blocks_desc'] = 'Comma-separated block names to show as cards in a student\'s course sidebar, for example <code>livesessionstats</code>. The bespoke course view does not render the side-pre region, so a block placed for students is otherwise invisible to them. Leave empty to keep the rail free of blocks.';
$string['supportcontactemail'] = 'Support email address';
$string['supportcontactemail_desc'] = 'Shown in the floating "Need help?" panel as a mailto: link. Leave empty to show no email address.';
$string['supportcontactphone'] = 'Support phone number';
$string['supportcontactphone_desc'] = 'Shown in the floating "Need help?" panel as a tel: link. Punctuation and spacing are kept as typed for reading, and stripped for dialling — so "87929 97407" is fine.';
$string['help_contact_lead'] = 'Or reach us directly';
$string['help_sidebar_lead'] = 'Support';
$string['supportticketurl'] = 'Support ticket URL';
$string['supportticketurl_desc'] = 'Where the floating "Need help?" button sends people. Use the site\'s own helpdesk, or a root-relative path to a local endpoint that signs the user in and redirects onward, so they are not asked to log in twice — for example /blocks/support/support_api.php. Leave empty to fall back to local_helpdesk and then to the Support block.';
$string['sd_cal_full'] = 'Full calendar';
$string['sd_cal_export'] = 'Import or export calendars';
$string['student_rail_progress'] = 'Show "Your progress" in the student course rail';
$string['student_rail_progress_desc'] = 'The built-in per-section progress card. Turn it off on sites whose rail already carries a progress-shaped block, so the same figures are not shown twice.';

// "New theme" -- two-level top bar dashboards (settings/newtheme.php).
$string['settingsnewtheme'] = 'New theme';
$string['newthemeheading'] = 'New theme: two-level top bar';
$string['newthemeheading_desc'] = 'A redesigned student and admin dashboard with a navy top bar instead of the sidebar. It shows the same data as the current dashboard. The current design stays the default until you switch this on. To preview it on your own dashboard without changing the site, open <a href="{$a}/my/?newtheme=1">/my/?newtheme=1</a> (and <a href="{$a}/my/?newtheme=0">/my/?newtheme=0</a> to go back).';
$string['newthemedesign'] = 'Dashboard design';
$string['newthemedesign_desc'] = 'Which design students and site administrators see on their dashboard (/my/).';
$string['newthemedesign_current'] = 'Current design (default)';
$string['newthemedesign_new'] = 'New theme: two-level top bar';
$string['nt_overview'] = 'Overview';
$string['nt_notifications'] = 'Notifications';
$string['nt_activity'] = 'Activity';
$string['nt_mainnav'] = 'Main navigation';
$string['nt_nofocus'] = 'No courses this semester yet';
$string['nt_comingup'] = 'Upcoming';
$string['nt_opencalendar'] = 'Open calendar';
$string['nt_nothingcoming'] = 'Nothing scheduled in the next 30 days.';
$string['nt_liveprefix'] = 'Live class · {$a}';
$string['nt_deadlinesweek'] = 'Deadlines this week';
$string['nt_next'] = 'Next: {$a}';
$string['nt_nothingdue'] = 'Nothing due — nice';
$string['nt_nolive'] = 'No live class scheduled';
$string['nt_mygrades'] = 'My grades';
$string['nt_semprogress'] = 'Semester {$a} progress';
$string['nt_streak'] = '{$a}-day streak';
$string['nt_nocourses'] = 'No courses to show yet.';
$string['nt_studytime'] = 'Study time';
$string['nt_last7days'] = 'Last 7 days';
$string['nt_last7weeks'] = 'Last 7 weeks';
$string['nt_total'] = 'Total';
$string['nt_dailyavg'] = 'Daily avg';
$string['nt_weeklyavg'] = 'Weekly avg';
$string['nt_mostfocused'] = 'Most focused';
$string['nt_studytime_note'] = 'Time between your actions on the site; breaks longer than 30 minutes are not counted.';
$string['nt_adduser'] = 'Add user';
$string['nt_createcourse'] = 'Create course';
$string['nt_reportslogs'] = 'Reports & logs';
$string['nt_kpi_users'] = 'Total users';
$string['nt_kpi_users_tip'] = 'New users in the last 7 days';
$string['nt_kpi_enrolments'] = 'Enrolments';
$string['nt_kpi_enrolments_tip'] = 'Growth in the last 30 days';
$string['nt_kpi_activetoday'] = 'Active today';
$string['nt_kpi_activetoday_tip'] = 'Compared with yesterday';
$string['nt_kpi_newcourses'] = 'New courses';
$string['nt_kpi_newcourses_tip'] = 'Created in the last 7 days (tile: last 30 days)';
$string['nt_siteactivitytoday'] = 'Site activity · today';
$string['nt_actions'] = '{$a} actions';
$string['nt_act_vs'] = '{$a->pct} vs last {$a->day}';
$string['nt_act_views'] = 'Views';
$string['nt_act_submissions'] = 'Submissions';
$string['nt_act_grades'] = 'Grades';
$string['nt_act_edits'] = 'Edits';
$string['nt_viewlogs'] = 'View all logs';
$string['nt_norecent'] = 'No changes on the site in the last 14 days.';
$string['nt_engagement'] = 'Learner engagement';
$string['nt_ndays'] = '{$a} days';
$string['nt_engagement_note'] = 'People signing in per day · peak {$a}';
$string['nt_weeklyactive'] = 'Weekly active';
$string['nt_monthlyactive'] = 'Monthly active';
$string['nt_stickiness'] = 'Stickiness';
$string['nt_catsstarred'] = '{$a->cats} categories · {$a->starred} starred';
$string['nt_whosonline'] = 'Who\'s online';
$string['nt_onlinenow'] = 'Online now';
$string['nt_nobodyonline'] = 'Nobody has been online in the last 24 hours.';
$string['nt_ago_min'] = '{$a} min ago';
$string['nt_ago_hr'] = '{$a} hr ago';
$string['nt_ago_day'] = '{$a} days ago';
$string['nt_health_ok'] = 'All systems OK';
$string['nt_health_warn'] = '{$a} need attention';
$string['nt_viewall'] = 'View all';
$string['nt_admin_sub_ok'] = 'Here is how your site is doing today — all systems look healthy.';
$string['nt_admin_sub_warn'] = 'Here is how your site is doing today — {$a} item(s) need your attention.';
$string['nt_hasevents'] = 'Has events';
$string['nt_next30'] = 'Next 30 days';
$string['nt_holiday'] = 'Holiday';
$string['nt_festival'] = 'Festival';
$string['nt_today'] = 'Today';
$string['nt_scheduled'] = 'Scheduled';
$string['nt_addevent'] = 'Add event';
$string['nt_level'] = 'Level';
$string['nt_xp_tip'] = 'Earn XP by completing activities, submitting on time and taking part in forums.';
$string['nt_viewall'] = 'View all';
$string['nt_new'] = 'New';
$string['nt_nonews'] = 'No announcements yet';
$string['nt_nonews_sub'] = 'News from the university and your courses will appear here.';
$string['nt_f_activities'] = 'Activities done';
$string['nt_f_left'] = 'Left to do';
$string['nt_f_last'] = 'Last opened';
$string['nt_f_grade'] = 'Grade so far';
$string['nt_f_upnext'] = 'Up next';
$string['nt_daysago'] = '{$a} days ago';
$string['nt_help_sub'] = 'Questions about classes, fees or your account? Contact student support.';
$string['nt_day'] = 'day';
$string['nt_days'] = 'days';
$string['nt_nextholiday'] = 'Next holiday';
$string['nt_holidays'] = 'Holidays';
$string['nt_festivals'] = 'Festivals';
$string['nt_recentgrades'] = 'Recent grades';
$string['nt_nogrades'] = 'No graded activities yet.';
$string['nt_mytasks'] = 'My tasks';
$string['nt_overduen'] = '{$a} overdue';
$string['nt_thisweekn'] = '{$a} this week';
$string['nt_alldone'] = 'You\'re all caught up';
$string['nt_alldone_sub'] = 'No pending assignments, quizzes or activities in the next 30 days.';
$string['nt_due_overdue'] = 'Overdue';
$string['nt_overdue_days'] = 'Overdue · {$a}d';
$string['nt_due_today'] = 'Due today';
$string['nt_due_tomorrow'] = 'Tomorrow';
$string['nt_due_indays'] = 'In {$a} days';
$string['nt_todo'] = 'To do';
$string['nt_open'] = 'Open';
$string['nt_focustimer'] = 'Focus timer';
$string['nt_tm_focus'] = 'Focus';
$string['nt_tm_short'] = 'Short break';
$string['nt_tm_long'] = 'Long break';
$string['nt_tm_start'] = 'Start';
$string['nt_tm_pause'] = 'Pause';
$string['nt_tm_today'] = 'Focus sessions today:';
$string['nt_myweek'] = 'My week';
$string['nt_ws_done'] = 'Activities completed';
$string['nt_ws_quiz'] = 'Quiz attempts';
$string['nt_ws_subs'] = 'Assignments submitted';
$string['nt_ws_posts'] = 'Forum posts';
$string['nt_ws_badges'] = 'Badges earned (all time)';

// Navigation layout: left sidebar or top bar (same theme, one setting).
$string['navlayoutheading'] = 'Navigation layout';
$string['navlayoutheading_desc'] = 'BAZ can show its navigation in two ways from the same code: the left sidebar (default) or the two-level top bar. Everything else (courses, modules, plugins, settings) stays the same.';
$string['navlayout'] = 'Navigation (all pages)';
$string['navlayout_desc'] = 'Click an option to apply it to the whole site straight away. No Save needed.';
$string['navlayout_left'] = 'Option 1 – Left sidebar (default)';
$string['navlayout_top'] = 'Option 2 – Top bar (Ednxt)';
$string['ntset_designheading'] = 'Design options';
$string['ntset_designheading_desc'] = 'Brand, colours, holidays and custom CSS used by the Option 2 features above.';
$string['ntset_layoutheading'] = 'Top bar layout';
$string['ntset_layoutheading_desc'] = 'Ednxt Top Bar uses all the <a href="{$a}">BAZ theme settings</a> (logos, menus, fonts, login page, footer ...). The settings below only change the two-level top bar design.';
$string['ntset_scope'] = 'Use the top bar on';
$string['ntset_scope_desc'] = 'All pages: every page uses the navy top bar instead of the left sidebar, like the dashboard. Dashboards only: only the student and admin dashboards are redesigned.';
$string['ntset_scope_all'] = 'All pages (recommended)';
$string['ntset_scope_dashboard'] = 'Dashboards only';
$string['ntset_showtabs'] = 'Show the second-level tabs';
$string['ntset_showtabs_desc'] = 'With Navigation = Option 2: the white bar under the navy bar with the page\'s section links (for example Users, Courses, Grades on admin pages, or a course\'s sections).';
$string['ntset_cardstyle'] = 'Dashboard card style';
$string['ntset_cardstyle_desc'] = 'Outlined cards with a navy offset shadow (as in the design), or flat cards with a soft shadow.';
$string['ntset_cardstyle_outlined'] = 'Outlined (design)';
$string['ntset_cardstyle_flat'] = 'Flat';
$string['ntset_dockposition'] = 'AI assistant buttons';
$string['ntset_dockposition_desc'] = 'Where the round AI assistant buttons sit.';
$string['ntset_dockposition_right'] = 'Bottom right, in a row (design)';
$string['ntset_dockposition_left'] = 'Bottom left, in a column (BAZ)';
$string['ntset_colourheading'] = 'Colours';
$string['ntset_colourheading_desc'] = 'Colours of the top bar design. Saving purges the theme cache so the change shows straight away.';
$string['ntset_barcolour'] = 'Top bar colour';
$string['ntset_barcolour_desc'] = 'Background of the navy bar and the dark dashboard cards.';
$string['ntset_accentcolour'] = 'Accent colour';
$string['ntset_accentcolour_desc'] = 'The active menu pill, buttons, progress bars and the logo text.';
$string['ntset_pagecolour'] = 'Page background';
$string['ntset_pagecolour_desc'] = 'Background behind the dashboard cards.';
$string['ntset_customcss'] = 'Custom CSS';
$string['ntset_customcss_desc'] = 'Extra CSS added after everything else, for small adjustments.';
$string['ntset_calendarheading'] = 'Dashboard calendar';
$string['ntset_calendarheading_desc'] = 'The month calendar in "Coming up" on the student dashboard. Everything in the Moodle calendar (site events, course events and each person\'s own events added with "New event") is shown automatically; holidays and festivals come from the list below.';
$string['ntset_holidays'] = 'Holidays and festivals';
$string['ntset_holidays_desc'] = 'One per line: <code>YYYY-MM-DD | Name | holiday</code> or <code>YYYY-MM-DD | Name | festival</code>. Holidays are shown in red, festivals in amber. Pre-filled with the Government of India gazetted holidays and common festivals for 2026; add next year\'s dates here when they are announced.';
$string['ntset_holidays_default'] = '2026-01-01 | New Year\'s Day | festival
2026-01-14 | Makar Sankranti / Pongal | festival
2026-01-26 | Republic Day | holiday
2026-03-04 | Holi | holiday
2026-03-19 | Ugadi / Gudi Padwa | festival
2026-03-21 | Id-ul-Fitr | holiday
2026-03-26 | Ram Navami | holiday
2026-03-31 | Mahavir Jayanti | holiday
2026-04-03 | Good Friday | holiday
2026-04-14 | Dr Ambedkar Jayanti / Vaisakhi | festival
2026-05-01 | Buddha Purnima | holiday
2026-05-27 | Id-ul-Zuha (Bakrid) | holiday
2026-06-26 | Muharram | holiday
2026-08-15 | Independence Day | holiday
2026-08-26 | Milad-un-Nabi / Onam | holiday
2026-08-28 | Raksha Bandhan | festival
2026-09-04 | Janmashtami | holiday
2026-09-14 | Ganesh Chaturthi | festival
2026-10-02 | Gandhi Jayanti | holiday
2026-10-20 | Dussehra | holiday
2026-10-29 | Karwa Chauth | festival
2026-11-08 | Diwali | holiday
2026-11-09 | Govardhan Puja | festival
2026-11-11 | Bhai Dooj | festival
2026-11-15 | Chhath Puja | festival
2026-11-24 | Guru Nanak Jayanti | holiday
2026-12-24 | Christmas Eve | festival
2026-12-25 | Christmas | holiday';
$string['ntset_brandheading'] = 'Branding';
$string['ntset_brandheading_desc'] = 'The name and logo shown at the top left of every page (instead of the Moodle logo), in the browser tab title and in the footer. The site name stored in Moodle is not changed.';
$string['ntset_brandname'] = 'Brand name';
$string['ntset_brandname_desc'] = 'Shown as the wordmark in the top bar when no brand logo is uploaded, and used in place of the site name in page titles and footers.';
$string['ntset_brandlogo'] = 'Brand logo';
$string['ntset_brandlogo_desc'] = 'Optional. A logo for the navy top bar (a light/white version works best, about 160 x 40 px). Leave empty to use the built-in JAIN Online logo.';
$string['ednxt_moved'] = 'The top bar layout is now part of BAZ. Choose "Navigation layout" and set its options on the <a href="{$a}">BAZ settings page, Top Bar tab</a>. Selecting this "Ednxt Top Bar" theme is the same as BAZ with Option 2 – Top bar.';
// Features per navigation layout.
$string['ntf_heading'] = 'Features';
$string['ntf_heading_desc'] = 'One common code for everything. Pick the Navigation option below: it applies at once, and every feature below follows it automatically (the line under each one shows what is in use now).';
$string['ntf_auto'] = 'Same as Navigation (automatic)';
$string['ntf_both'] = 'Both layouts';
$string['ntf_left'] = 'Option 1 – Left sidebar';
$string['ntf_top'] = 'Option 2 – Top bar';
$string['ntf_rail'] = 'Option 3 – Menu pill';
$string['ntf_dock'] = 'Option 4 – Floating side menu';
$string['ntf_campus'] = 'Option 5 – Tab bar';
$string['ntf_off'] = 'Off';
$string['ntf_dashboards'] = 'New dashboards';
$string['ntf_dashboards_desc'] = 'The redesigned student dashboard (greeting, Focus next, Upcoming calendar with holidays, Focus timer, My week, Recent grades ...) and admin dashboard. Option 1 keeps the classic BAZ dashboards.';
$string['ntf_askjo'] = 'Ask JO on every page';
$string['ntf_askjo_desc'] = 'The Ask JO chat opens as the same right-hand panel on every page, without leaving the page.';
$string['ntf_assistants'] = 'Assistant buttons (bottom right)';
$string['ntf_assistants_desc'] = 'The round 4-icon assistants button at the bottom right, with the AI Tutor and Nestor lined up above it.';
$string['ntf_helptab'] = '"Need help?" side tab';
$string['ntf_helptab_desc'] = 'The vertical "Need help?" tab fixed at the bottom of the left edge.';
$string['ntf_holidays'] = 'Holidays on the calendar';
$string['ntf_holidays_desc'] = 'Holidays (red) and festivals (amber) from the list below, shown on the calendar pages.';
$string['ntf_branding'] = 'Brand logo and name';
$string['ntf_branding_desc'] = 'The brand logo and name (below) on the login page, the front page, the top bar, page titles and footers instead of the Moodle placeholders.';
$string['ntf_logindesign'] = 'New sign-in form design';
$string['ntf_logindesign_desc'] = 'The card-style sign-in form (Welcome back, icon fields, navy Log in button).';
$string['ntf_now'] = 'In use now:';
$string['ntf_follows'] = '(follows Navigation)';
$string['ntf_always'] = 'Always on (both options)';

// Options 3-5 dashboards.
$string['nv_daystreak'] = 'day streak';
$string['nv_nowlearning'] = 'Now learning';
$string['nv_timeline'] = 'Your timeline';
$string['nv_continue'] = 'Continue learning';
$string['nv_bythenumbers'] = 'By the numbers';
$string['nv_notices'] = 'Notice board';
$string['nv_fromthepress'] = 'From the press';

// Options 3-5: student widgets and admin dashboards.
$string['nv_7weeks'] = 'Last 7 weeks';
$string['nv_a_appearance'] = 'Appearance';
$string['nv_a_cohorts'] = 'Cohorts';
$string['nv_a_courses'] = 'Manage courses';
$string['nv_a_notifications'] = 'Notifications';
$string['nv_a_plugins'] = 'Plugins';
$string['nv_a_roles'] = 'Roles';
$string['nv_a_tasks'] = 'Scheduled tasks';
$string['nv_a_upload'] = 'Upload users';
$string['nv_a_users'] = 'Browse users';
$string['nv_admined'] = 'Administrator\'s edition';
$string['nv_agenda'] = 'Agenda';
$string['nv_almanac'] = 'Almanac';
$string['nv_another'] = 'Another tip';
$string['nv_ask_ph'] = 'Stuck on something? Ask JO about a topic, an assignment or a concept…';
$string['nv_average'] = 'Average';
$string['nv_avgrecent'] = 'average of recent grades';
$string['nv_b_finisher'] = 'Finisher';
$string['nv_b_focused'] = '5h this week';
$string['nv_b_halfway'] = 'Halfway there';
$string['nv_b_levelup'] = 'Level 3';
$string['nv_b_streak'] = '3-day streak';
$string['nv_b_topscore'] = 'Top score';
$string['nv_badges'] = 'Achievements';
$string['nv_bulletin'] = 'Bulletin';
$string['nv_catalogue'] = 'Course catalogue';
$string['nv_circulation'] = 'Circulation';
$string['nv_commands'] = 'Commands';
$string['nv_controlcenter'] = 'Control center';
$string['nv_deadlines'] = 'Deadlines';
$string['nv_eventstream'] = 'Event stream';
$string['nv_feed'] = 'Feed';
$string['nv_gazette'] = 'The Learner\'s Gazette';
$string['nv_gradepulse'] = 'Grade pulse';
$string['nv_healthscore'] = 'Health score';
$string['nv_healthy'] = 'healthy';
$string['nv_launchpad'] = 'Launchpad';
$string['nv_ledger'] = 'Ledger';
$string['nv_less'] = 'Less';
$string['nv_livefeed'] = 'Live feed';
$string['nv_minutes'] = 'Minutes of the day';
$string['nv_mission'] = 'Today\'s mission';
$string['nv_more'] = 'More';
$string['nv_mycourses'] = 'My courses';
$string['nv_nextup'] = 'Next deadline';
$string['nv_notes'] = 'Quick notes';
$string['nv_notes_ph'] = 'Jot down a thought, a formula, a question for your mentor…';
$string['nv_offices'] = 'Offices';
$string['nv_opsconsole'] = 'Ops console';
$string['nv_perweek'] = 'a week';
$string['nv_pickup'] = 'Pick up where you left off';
$string['nv_presence'] = 'Presence';
$string['nv_private'] = 'Only you can see this. It is kept in this browser.';
$string['nv_q1'] = 'Explain my next topic simply';
$string['nv_q2'] = 'Make me a 30-minute study plan';
$string['nv_q3'] = 'Quiz me on what I studied';
$string['nv_queue'] = 'Up next';
$string['nv_quicklinks'] = 'Quick links';
$string['nv_quote1'] = 'Small steps every day add up to big results at the end of the semester.';
$string['nv_quote2'] = 'Understanding grows when you explain an idea in your own words.';
$string['nv_quote3'] = 'Curiosity is the engine of learning; questions are its fuel.';
$string['nv_quote4'] = 'Progress, not perfection, is what a good week looks like.';
$string['nv_quote5'] = 'The best time to revise is a little while after you first learn something.';
$string['nv_quote6'] = 'A focused hour is worth more than a distracted afternoon.';
$string['nv_quote7'] = 'Mistakes are simply lessons that arrived early.';
$string['nv_readinglist'] = 'Reading list';
$string['nv_registrar'] = 'The Registrar\'s Gazette';
$string['nv_reportcard'] = 'Report card';
$string['nv_saved'] = 'Saved';
$string['nv_scoreboard'] = 'Scoreboard';
$string['nv_sofar'] = 'so far this week';
$string['nv_streak_hint'] = 'Log in and learn each day to keep it going.';
$string['nv_studiedweek'] = 'Studied this week';
$string['nv_studypulse'] = 'Study pulse';
$string['nv_systemcheck'] = 'System check';
$string['nv_thisperiod'] = 'recently';
$string['nv_thisweek'] = 'This week';
$string['nv_thought'] = 'Thought for the day';
$string['nv_timetable'] = 'This week\'s timetable';
$string['nv_timetable_note'] = 'Classes, deadlines and holidays from your calendar.';
$string['nv_tip1'] = 'Study in 25-minute blocks with a 5-minute break; your focus stays sharp longer.';
$string['nv_tip2'] = 'Before reading a unit, skim its headings and write down two questions you want answered.';
$string['nv_tip3'] = 'Revise yesterday\'s topic for 10 minutes before starting a new one.';
$string['nv_tip4'] = 'Teach a concept to a friend (or to JO) to find the gaps in your understanding.';
$string['nv_tip5'] = 'Put your phone in another room during study time; out of sight is out of mind.';
$string['nv_tip6'] = 'Start assignments the day they open: a rough first draft beats a perfect last-minute one.';
$string['nv_tip7'] = 'Sleep is study time too; memory is strengthened while you rest.';
$string['nv_tipday'] = 'Tip of the day';
$string['nv_todo'] = 'My to-do';
$string['nv_todo_empty'] = 'Nothing yet. Add your first task above.';
$string['nv_todo_ph'] = 'Add a task and press Enter';
$string['nv_tracks'] = 'Course tracks';
$string['nv_visitors'] = 'Visitors\' book';
$string['nv_weeklygoal'] = 'weekly goal';
$string['nv_wireless'] = 'On the wireless';

// Quick Navigation switch (theme/baz/navigation.php).
$string['nvq_title'] = 'BAZ Navigation – quick switch';
$string['nvq_intro'] = 'Pick how the whole site looks. The change applies at once for everyone; nothing else is changed.';
$string['nvq_apply'] = 'Apply this option';
$string['nvq_current'] = 'In use now';
$string['nvq_applied'] = '{$a} is now in use.';
$string['nvq_allsettings'] = 'All BAZ settings';
$string['nvq_view'] = 'View the site →';
$string['nvq_left'] = 'Classic BAZ left sidebar with the full menu.';
$string['nvq_top'] = 'Navy top bar with the menu links in a row underneath the logo.';
$string['nvq_rail'] = 'Search on the left and a Menu pill that opens the module tiles. Soft rounded cards. Same navy and gold as Option 1.';
$string['nvq_dock'] = 'A floating menu pill at the left edge, centred, showing every icon with its name. Flat cards. Same navy and gold as Option 1.';
$string['nvq_campus'] = 'Search in the middle with a tab row for Dashboard, My Courses, Calendar and more. Square ruled cards. Same navy and gold as Option 1.';

// Options 3-5: unique widgets (one option each).
$string['nv_habits'] = 'Habit tracker';
$string['nv_habit_ph'] = 'Add a daily habit (e.g. Revise 30 min)';
$string['nv_habit_empty'] = 'Add up to 5 daily habits and tick each day you do them.';
$string['nv_mood'] = 'Mood check-in';
$string['nv_mood_q'] = 'How do you feel about your studies today?';
$string['nv_mood_l1'] = 'Stressed';
$string['nv_mood_l2'] = 'Low';
$string['nv_mood_l3'] = 'Okay';
$string['nv_mood_l4'] = 'Good';
$string['nv_mood_l5'] = 'Great';
$string['nv_mood_t1'] = 'Take a 10-minute break, then pick just one small task. Need support? Use "Need help?" on the left.';
$string['nv_mood_t2'] = 'Start with something easy: re-read your notes for 15 minutes to warm up.';
$string['nv_mood_t3'] = 'Steady is good. Finish the next activity in your current course.';
$string['nv_mood_t4'] = 'Nice! A good day to tackle the hardest item on your agenda.';
$string['nv_mood_t5'] = 'You are on fire - try a practice quiz or help a classmate in the forum.';
$string['nv_mood_today'] = 'Today';
$string['nv_wordday'] = 'Word of the day';
$string['nv_word1'] = '<b>Synthesis</b> — combining ideas from different sources into one new, connected whole.';
$string['nv_word2'] = '<b>Empirical</b> — based on observation or experiment rather than theory alone.';
$string['nv_word3'] = '<b>Paradigm</b> — a model or pattern of thinking that shapes how a field sees problems.';
$string['nv_word4'] = '<b>Leverage</b> — using a resource or advantage to achieve a bigger result.';
$string['nv_word5'] = '<b>Heuristic</b> — a practical rule of thumb that helps you solve problems faster.';
$string['nv_word6'] = '<b>Stakeholder</b> — any person or group affected by, or able to affect, a decision.';
$string['nv_word7'] = '<b>Iterate</b> — to repeat a process, improving it a little each time.';
$string['nv_almanac_courses'] = 'Courses this term';
$string['nv_almanac_today'] = 'Day of the year';
$string['nv_quickfind'] = 'Quick find';
$string['nv_qf_settings'] = 'Find a setting';
$string['nv_qf_settings_ph'] = 'e.g. password policy';
$string['nv_qf_courses'] = 'Find a course';
$string['nv_qf_courses_ph'] = 'Course name or code';
$string['nv_go'] = 'Go';
$string['nv_achecklist'] = 'Admin checklist';
$string['nv_achecklist_ph'] = 'Add a task (e.g. Review new enrolments)';
$string['nv_achecklist_empty'] = 'Your own admin tasks for today. Kept in this browser only.';
$string['nv_opsnotes'] = 'Ops notes';
$string['nv_opsnotes_ph'] = 'Handover notes, incidents, things to check tomorrow…';
$string['nv_regnote'] = "Registrar's note";
$string['nv_atip1'] = 'Run "Check for updates" monthly and read plugin release notes before upgrading.';
$string['nv_atip2'] = 'Keep cron running every minute - many features (emails, grades, backups) depend on it.';
$string['nv_atip3'] = 'Use cohorts and cohort sync to enrol whole batches instead of enrolling students one by one.';
$string['nv_atip4'] = 'Review Site administration > Reports > Security checks after any configuration change.';
$string['nv_atip5'] = 'Test theme or plugin changes on a copy of the site before applying them to the live one.';
$string['nv_atip6'] = 'Schedule automated course backups outside teaching hours to keep the site fast.';
$string['nv_atip7'] = 'Purge only the caches you need (e.g. theme or language) instead of purging all caches.';

// Per-user dashboard style.
$string['ms_menu'] = 'My dashboard style';
$string['ms_title'] = 'My dashboard style';
$string['ms_intro'] = 'Choose how the site looks for you. This only changes your own view; other users keep their own choice.';
$string['ms_sitedefault'] = 'Use the site default';
$string['ms_sitedefault_on'] = 'You are following the site default ({$a}).';
$string['ms_sitedefault_off'] = 'You chose your own style. The site default is {$a}.';
$string['ms_use'] = 'Use this style';
$string['ms_saved'] = 'Your dashboard style is saved.';
$string['ms_reset'] = 'Back to the site default.';
$string['ms_default_is'] = 'Default for all users: {$a}.';
$string['ms_admin_label'] = 'Admin – set the default for all users:';
$string['ms_default_saved'] = '{$a} is now the default for all users. Users who chose their own style keep it.';
$string['ms_applyall'] = 'Apply to all users';
$string['ms_applyall_help'] = 'Select a style above, then click this to show it to every user (admins, teachers and students). Users can still change their own style afterwards.';
$string['ms_applyall_done'] = '{$a} is now applied to all users.';

// Student study tools (Options 3-5).
$string['nv_attend'] = 'Attendance calculator';
$string['nv_attend_att'] = 'Classes attended';
$string['nv_attend_tot'] = 'Total classes';
$string['nv_attend_tgt'] = 'Target %';
$string['nv_attend_hint'] = 'Enter classes attended and total classes to see where you stand.';
$string['nv_attend_ok'] = 'You are safe. You can still miss {n} more class(es) and stay above your target.';
$string['nv_attend_need'] = 'Below target. Attend the next {n} class(es) in a row to reach it.';
$string['nv_exams'] = 'Exam planner';
$string['nv_ex_ph'] = 'Exam or deadline name';
$string['nv_ex_date'] = 'Date';
$string['nv_ex_today'] = 'Today';
$string['nv_ex_d'] = ' d left';
$string['nv_ex_empty'] = 'Add your exams and submissions to see the days left.';
$string['nv_kanban'] = 'Assignment board';
$string['nv_kanban_sub'] = 'To do · Doing · Done';
$string['nv_k_ph'] = 'Add a task, assignment or topic';
$string['nv_k_todo'] = 'To do';
$string['nv_k_doing'] = 'Doing';
$string['nv_k_done'] = 'Done';
$string['nv_cards'] = 'Flashcards';
$string['nv_c_q'] = 'Question (tap to flip)';
$string['nv_c_a'] = 'Answer (tap to flip back)';
$string['nv_c_prev'] = 'Previous card';
$string['nv_c_next'] = 'Next card';
$string['nv_c_del'] = 'Delete card';
$string['nv_c_empty'] = 'Make your first card below. Tap a card to see the answer.';
$string['nv_c_phq'] = 'Question or term';
$string['nv_c_pha'] = 'Answer or meaning';
$string['nv_c_add'] = 'Add card';
$string['nv_gcalc'] = 'Grade calculator';
$string['nv_g_lead'] = 'Enter each assessment with its weight and your score to see your weighted mark and what you need on the rest.';
$string['nv_g_name'] = 'Assessment';
$string['nv_g_weight'] = 'Weight %';
$string['nv_g_score'] = 'Score %';
$string['nv_g_addrow'] = 'Add assessment';
$string['nv_g_target'] = 'Target mark %';
$string['nv_g_hint'] = 'Fill in at least one assessment with its weight and score.';
$string['nv_g_sofar'] = 'So far: {p} marks from {w}% of the weight (average {a}%).';
$string['nv_g_need'] = 'To reach your target, score {n}% on the remaining {r}%.';
$string['nv_g_hard'] = 'Your target of {g}% is out of reach with the weight that is left.';
$string['nv_g_safe'] = 'You have already reached your target of {g}%.';
$string['nv_cite'] = 'Citation helper';
$string['nv_cite_lead'] = 'Make a reference in APA, MLA or Harvard, then copy it into your assignment.';
$string['nv_cite_style'] = 'Style';
$string['nv_cite_year'] = 'Year';
$string['nv_cite_author'] = 'Author (Surname, Initial.)';
$string['nv_cite_title'] = 'Title';
$string['nv_cite_site'] = 'Website or publisher';
$string['nv_cite_hint'] = 'Type a title to build your reference.';
$string['nv_cite_noauthor'] = 'Anonymous';
$string['nv_cite_copy'] = 'Copy reference';
$string['nv_cite_copied'] = 'Copied';

// Widget help text.
$string['nv_plan'] = 'Today\'s plan';
$string['nv_plan_time'] = 'Time';
$string['nv_plan_ph'] = 'What will you do?';
$string['nv_plan_empty'] = 'Plan your day in time slots. Tick each one when done. It clears itself tomorrow.';
$string['nv_h_plan'] = 'A simple hour-by-hour plan for today. Add a time and a task, tick it when done. Tomorrow it starts fresh.';
$string['nv_tips_plan'] = 'Put your hardest subject in your best energy hours.|Keep slots short (30 to 90 minutes) with breaks between.|Tick a slot as soon as you finish to see your progress.';
$string['nv_h_notes'] = 'A private scratch pad for formulas, questions for your mentor or anything to remember. It saves as you type.';
$string['nv_tips_notes'] = 'Only you can see it. It is kept in this browser, not on the server.|Use a separate line for each point so it is easy to scan.';
$string['nv_h_todo'] = 'Your personal task list. Add a task, tick it when done, remove it with the cross.';
$string['nv_tips_todo'] = 'Press Enter to add a task quickly.|Break big work into small tasks you can finish in one sitting.|Only you can see this list. It is kept in this browser.';
$string['nv_h_habit'] = 'Track up to 5 daily study habits across the last 7 days and see your consistency score.';
$string['nv_tips_habit'] = 'Tap a day circle to mark the habit done for that day.|Pick small habits, such as Revise 30 min or Read 10 pages.|The percentage is the share of ticks you completed over 7 days.';
$string['nv_h_attend'] = 'Works out your attendance % from the classes you attended and the total held, against your target.';
$string['nv_tips_attend'] = 'Enter the number from your attendance record for one subject at a time.|The result says how many classes you can still miss, or how many you must attend in a row.|Change the target to match your university rule.';
$string['nv_h_exams'] = 'Your own countdown for exams, viva dates and assignment submissions.';
$string['nv_tips_exams'] = 'Add a name and a date. Items are sorted with the nearest first.|Red means 7 days or fewer are left.|Past dates show a tick.';
$string['nv_h_kanban'] = 'A three-column board for your own assignments and topics: To do, Doing and Done.';
$string['nv_tips_kanban'] = 'Add a task, then use the arrows to move it between columns.|Keep Doing to two or three cards so you finish what you start.|Only you can see this board. It is kept in this browser.';
$string['nv_h_cards'] = 'Make flashcards for definitions, formulas and key terms and revise them any time.';
$string['nv_tips_cards'] = 'Tap the card to flip between question and answer.|Use the arrows to move through your deck.|Short, single-idea cards work best.';
$string['nv_h_mood'] = 'A private daily check-in on how you feel about studying, with a short tip for each mood.';
$string['nv_tips_mood'] = 'Pick the face that fits your day. It is saved for today.|The strip below shows your last days.|Only you can see this.';
$string['nv_h_gcalc'] = 'Add each assessment with its weight and your score to see your weighted mark so far and the score you need on the rest to reach a target.';
$string['nv_tips_gcalc'] = 'Weights are the % each assessment counts towards the final mark.|Scores are your % on that assessment.|Set a target mark to see the score you need on the remaining weight.';
$string['nv_h_cite'] = 'Builds a reference for your assignment in APA 7, MLA 9 or Harvard style.';
$string['nv_tips_cite'] = 'Write the author as Surname, Initial.|Pick the style your department asks for.|Check the result against your department guide before you submit.';
$string['nvq_menu'] = 'Dashboard style (all users)';

// Change my email / mobile number with a one-time code (theme/baz/contact_otp.php).
$string['ct_change'] = 'Change';
$string['ct_title_email'] = 'Change email address';
$string['ct_title_phone'] = 'Change mobile number';
$string['ct_new_email'] = 'New email address';
$string['ct_new_phone'] = 'New mobile number';
$string['ct_send'] = 'Send code';
$string['ct_resend'] = 'Resend code';
$string['ct_verify'] = 'Verify and save';
$string['ct_cancel'] = 'Cancel';
$string['ct_code'] = 'Enter the 6-digit code';
$string['ct_sentto_email'] = 'We sent a 6-digit code to {$a}. It works for 5 minutes.';
$string['ct_sentto_phone'] = 'We sent a 6-digit code by SMS to {$a}. It works for 5 minutes.';
$string['ct_resent'] = 'The same code was sent again. A new code is made only after the 5 minutes end.';
$string['ct_timeleft'] = 'Code valid for {$a}';
$string['ct_expired_note'] = 'The code has expired. Press Resend to get a new one.';
$string['ct_same'] = 'This is already your current value.';
$string['ct_taken'] = 'This email address is already used by another account.';
$string['ct_bademail'] = 'Enter a valid email address.';
$string['ct_badphone'] = 'Enter a valid mobile number (8 to 15 digits, optional + at the start).';
$string['ct_locked'] = 'Too many wrong codes. Try again in {$a} minutes.';
$string['ct_wait'] = 'Please wait {$a} seconds before asking again.';
$string['ct_nocode'] = 'Ask for a code first.';
$string['ct_expired'] = 'The code has expired. Press Resend to get a new one.';
$string['ct_wrong'] = 'Wrong code. {$a} tries left.';
$string['ct_mailfail'] = 'The email could not be sent. Please try again later.';
$string['ct_smsfail'] = 'SMS is not available right now. Please try again later or contact support.';
$string['ct_error'] = 'Something went wrong. Please try again.';
$string['ct_done_email'] = 'Your email address was updated.';
$string['ct_done_phone'] = 'Your mobile number was updated.';
$string['ct_mailsubject'] = '{$a->code} is your {$a->site} verification code';
$string['ct_mailtext'] = 'Hello {$a->name},

Your verification code is {$a->code}.
It works for {$a->min} minutes. If you did not ask to change your email address, ignore this message.

{$a->site}';
$string['ct_smstext'] = '{$a->code} is your verification code. Valid for {$a->min} minutes.';
$string['ct_smsheading'] = 'SMS for mobile-number verification';
$string['ct_smsheading_desc'] = 'Used to send the one-time code when a student changes their mobile number. Email codes use Moodle outgoing mail.';
$string['ct_sms_provider'] = 'SMS provider';
$string['ct_sms_key'] = 'SMS key / account SID / auth key';
$string['ct_sms_secret'] = 'SMS secret / auth token (Twilio)';
$string['ct_sms_sender'] = 'Sender / From number / MSG91 template ID / Twilio Verify Service SID (VA...) / TextBee Device ID';
$string['ct_sms_demo'] = 'Demo mode (show the code on screen)';
$string['ct_sms_demo_desc'] = 'For demos only. If the SMS cannot be sent, the one-time code is shown in the popup instead. Keep this OFF on a live site: anyone could then change a mobile number without owning it.';
$string['ct_limit'] = 'You have already used all {$a} allowed changes this month. Please try again next month or contact support.';
$string['ct_max_changes'] = 'Allowed email / mobile changes per person, per month';
$string['ct_max_changes_desc'] = 'How many times each person may change their email address, and separately their mobile number, with a code, in one calendar month. The count starts again every month. 0 = unlimited. Default 5.';
