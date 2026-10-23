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
 * Language strings for the Edwiser Video Activity module.
 *
 * @package    mod_edwiservideoactivity
 * @copyright  2024 Edwiser
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $CFG;

$string['modulename'] = 'Edwiser Video ';
$string['modulenameplural'] = 'Edwiser Video Activities';
$string['modulename_help'] = 'The Edwiser Video Activity module allows teachers to add video content to their courses.';
$string['edwiservideoactivityname'] = 'Activity name';
$string['edwiservideoactivityname_help'] = 'Enter a name for this video activity.';
$string['pluginname'] = 'Edwiser Video Activity';
$string['pluginadministration'] = 'Edwiser Video Activity administration';
$string['edwiservideoactivity:addinstance'] = 'Add a new Edwiser Video Activity';
$string['edwiservideoactivity:view'] = 'View Edwiser Video Activity';

$string['edwiservideoactivityname'] = 'Activity title';
$string['overview'] = 'Overview';
$string['resources'] = 'Resources';
$string['transcript'] = 'Transcript';
$string['edwiservideoactivityfieldset'] = 'Custom video activity fields';
$string['source'] = 'Source';
$string['source_help'] = 'Select the source of the video (e.g., YouTube, Vimeo, etc.).';
$string['overview_help'] = 'Provide a detailed overview of what the student will learn.';
$string['resources_help'] = 'Upload files or instructions that supplement the video.';
$string['transcript_help'] = 'Upload a transcript or subtitle file for the video.';
$string['insertmedia'] = 'Insert media';
$string['insertmedia_help'] = 'Provide the basic metadata for this video activity.';
$string['activitytitle'] = 'Activity title';
$string['activitytitle_help'] = 'Enter the title of the activity. This will be displayed to students.';
$string['overviewcontent'] = 'Overview';
$string['overviewcontent_help'] = 'Provide an overview of what the student will learn from this activity.';
$string['resourcesfiles'] = 'File';
$string['resourcesfiles_help'] = 'Upload any supporting resources or instructions.';
$string['transcriptfiles'] = 'File';
$string['transcriptfiles_help'] = 'Upload a transcript file for accessibility and reference.';
$string['mediasource'] = 'Source';
$string['uploadfile'] = 'Upload file';
$string['addurl'] = 'Add URL';
$string['mediafile'] = 'Media file';
$string['mediaurl'] = 'Media URL';
$string['embedvideo'] = 'Embed video';
$string['embedcode'] = 'Add code';
$string['notsupported'] = 'Your browser does not support HTML5 video.';
$string['mediafilevalidation'] = "Upload a video file.";
$string['mediaurlvalidation'] = "Insert a validate URL.";
$string['embedcodevalidation'] = "Insert a validate Embed code.";
$string['duedate'] = "Due";
$string['uploadbandwidthwarning'] = "Upload video files directly from your computer into Moodle.<br> <span class='text-danger'>⚠️ Not recommended for large files — big videos can quickly fill up server storage and slow down performance. Best used for short clips, tutorials, or small-sized videos</span>";

$string['mediaurlnotice'] = 'Add the direct link to a video file, such as .mp4, .mov, or similar formats. This link should go straight to the file — not just a share page.
<br>Example:
<br><i class="fa-solid fa-check" style="color:green;"></i> Correct: https://yourserver.com/videos/lesson1.mp4
<br><i class="fa-solid fa-xmark" style="color:red;"></i> Not allowed:  YouTube or Google Drive share links (they don’t point to the actual video file). When used correctly, the video will stream inside your course player.';

$string['embedurlnotice'] = 'Paste the embed code from platforms like,
                              <img src="'.$CFG->wwwroot.'/mod/edwiservideoactivity/pix/youtube.png" alt="YouTube" >
                              <img src="'.$CFG->wwwroot.'/mod/edwiservideoactivity/pix/vimeo.svg" alt="Vimeo">
                               or similar. <br> 👉 You’ll find this under the video’s Share → Embed option.<br> Example: A YouTube embed code will start with &lt;iframe ...&gt; and display the video directly in your course page.';
$string['overviewsectiondescription'] = "Add a short description or summary of the video. This helps learners understand what the video covers.";
$string['resourcessectiondescription'] = "You can attach supporting materials or resources along with your video to provide extra value to your learners.";
$string['transcriptsectiondescription'] = "Add the full transcript of the video. This makes your content more accessible for learners who prefer reading, have hearing difficulties, or want to follow along";

// Progress tracking strings.
$string['activitycompleted'] = 'Activity marked as complete';
$string['completionnotenabled'] = 'Completion tracking is not enabled for this activity';

// Completion percentage strings
$string['completionpercent'] = 'Custom completion percentage';
$string['completionpercent_label'] = 'Enable';
$string['completionpercent_help'] = 'Marks completion when the student views the set video percentage. Accept values 1 to 100. Supported platforms: YouTube and Vimeo.';
$string['completionpercent_error'] = 'Accept values from 1 to 100';
$string['completiondetail:completionpercent'] = 'You have to watch {$a}% of the video';
$string['onlyintegervalueallowed'] = 'Only integer value between 0 and 100 is allowed';
