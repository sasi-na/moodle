
Zoom Online Integration Plugin for Moodle
-----------------------------------------
Version: 1.0.0
Release Date: October 2024

Overview:
The Zoom Online Integration Plugin for Moodle simplifies the management of Zoom meetings within Moodle courses. 
This plugin automates meeting creation, recording management, and attendance tracking, offering a seamless experience 
for educators using Zoom in online learning environments.

Features:
- Automatic Zoom meeting creation and link integration within Moodle courses.
- Video recording management with options to store recordings on AWS S3 or Moodle.
- Automatic deletion of Zoom recordings post-upload to save Zoom storage space.
- Attendance tracking using Zoom’s attendance reports integrated with Moodle’s Attendance module.
- Automated creation of Attendance modules for courses where they are not present.

Installation:
1. Download the plugin and upload it to your Moodle installation.
2. Complete the installation and configuration as described in the documentation.

Configuration:
- Set up your Zoom OAuth app and configure the plugin with the Client ID, Client Secret, and Account ID.
- Choose storage options for video recordings and enable/disable attendance tracking.

Changelog:
- Version 1.0.0:
  - Initial release with full meeting creation, recording management, and attendance tracking features.
  - Integrated support for AWS S3 and local Moodle storage.
  - Automatic Attendance module creation and Zoom attendance syncing.

Known Issues:
- The plugin requires a Zoom Pro or higher account for creating meetings and accessing recordings.
- Dependent on the AWS S3 PHP SDK if using AWS storage.

Support:
For support, please contact support@inneall.com.

License:
This plugin is licensed under the GNU GPL v3 or later. Refer to the LICENSE file or visit https://www.gnu.org/licenses/gpl-3.0.html for more details.
