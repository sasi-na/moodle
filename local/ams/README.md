# AMS Integration Plugin

This plugin provides automated student account creation and course enrollment based on fee payment in the AMS (Academic Management System).

## Features

- ✅ **Automated Student Account Creation**: Creates student accounts with all required custom profile fields
- ✅ **Automatic Course Enrollment**: Auto-enrolls students in pre-semester courses based on their program during account creation
- ✅ **Email Notifications**: Sends welcome emails to students with login credentials
- ✅ **Admin Notifications**: Notifies administrators when new students are created
- ✅ **RESTful API**: Easy integration with external systems via web services
- ✅ **Zero AMS Code Changes**: Works with existing AMS integration - just call the same API

## How It Works

When AMS calls `local_ams_create_student` (your existing API call), the plugin now:
1. Creates the student account with all profile fields
2. **Automatically enrolls** the student in program-specific courses based on `mainprogram` field
3. Sends welcome email with login credentials
4. Returns enrollment status in the response

**No changes required on the AMS side** - your existing integration continues to work!

## Installation

1. Run the Moodle upgrade script:
   ```bash
   php admin/cli/upgrade.php
   ```
   Or visit **Site Administration > Notifications** in your browser to trigger the upgrade.

## Configuration

### 1. Enable Web Services
1. Go to **Site administration > Advanced features**
2. Enable "**Enable web services**"
3. Click **Save changes**

### 2. Enable REST Protocol
1. Go to **Site administration > Plugins > Web services > Manage protocols**
2. Enable **REST protocol**

### 3. Create Web Service Token
1. Go to **Site administration > Plugins > Web services > Manage tokens**
2. Click **Add**
3. Select a user with appropriate capabilities (admin or user with `moodle/user:create` and `moodle/course:enrolconfig`)
4. Select service: **AMS Student Creation Service**
5. Click **Save changes**
6. Copy the generated token for API calls

### 4. Create Custom Profile Fields

Create the following custom user profile fields via **Site administration > Users > User profile fields**:

| Field Name | Short Name | Field Type | Required |
|------------|------------|------------|----------|
| Date of Birth | dob | Date/Time | No |
| Gender | gender | Text | No |
| Mobile Number | mobile | Text | No |
| Enrolment Number | enrolmentnumber | Text | Yes |
| Main Program | mainprogram | Text | Yes |
| Program Register For | programregister | Text | No |
| Elective | elective | Text | No |
| USN | usn | Text | No |
| Batch | batch | Text | Yes |
| Father Name | fathername | Text | No |
| Mother Name | mothername | Text | No |
| Nationality | nationality | Text | No |
| DEB ID | debid | Text | No |
| ABC ID | abcid | Text | No |

### 5. Create Pre-Semester Courses

Ensure the following courses exist in your Moodle with the correct **shortnames**:

| Course Shortname | Description |
|------------------|-------------|
| LBESEM0 | Common pre-semester course for all programs |
| FCIBBAJAN2026 | BBA - January 2026 batch |
| FCIBCOMJAN2026 | B.Com - January 2026 batch |
| FCIBCAJAN2026 | BCA - January 2026 batch |
| FCIMAJAN2026 | MA - January 2026 batch |
| FCIJAN2026 | MBA - January 2026 batch |
| FCIMCAJAN2026 | MCA - January 2026 batch |

## Program to Course Mapping

The plugin automatically enrolls students in courses based on their main program:

| Program | Course 1 | Course 2 |
|---------|----------|----------|
| Bachelor of Business Administration | LBESEM0 | FCIBBAJAN2026 |
| Bachelor of Commerce | LBESEM0 | FCIBCOMJAN2026 |
| Bachelor of Computer Application | LBESEM0 | FCIBCAJAN2026 |
| Master of Arts | LBESEM0 | FCIMAJAN2026 |
| Master of Business Administration | LBESEM0 | FCIJAN2026 |
| Master of Commerce | LBESEM0 | FCIMAJAN2026 |
| Master of Computer Application | LBESEM0 | FCIMCAJAN2026 |

> **Note**: To modify this mapping, edit the `get_program_course_mapping()` method in `/local/ams/classes/external.php`.

## API Usage

**Base URL**: `http://your-moodle-site.com/webservice/rest/server.php`  
**Method**: POST  
**Format**: JSON

---

### 🎯 **Primary API: Create Student** (`local_ams_create_student`)

**This is the API your AMS currently calls**. As of version 1.3.0, it now **automatically enrolls students** in program-specific courses based on the `mainprogram` field.

#### ✨ What's New in v1.3.0
- **Auto-enrollment is now built-in** to `local_ams_create_student`
- **No changes needed** in your AMS code
- Student account creation + course enrollment happens in **one API call**
- Returns enrollment status in the response

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `wstoken` | String | Yes | Your web service token |
| `wsfunction` | String | Yes | `local_ams_create_student` |
| `moodlewsrestformat` | String | Yes | `json` |
| `student[firstname]` | String | Yes | First name |
| `student[lastname]` | String | Yes | Last name |
| `student[email]` | String | Yes | Email address (must be unique) |
| `student[enrolmentnumber]` | String | Yes | Enrollment number |
| `student[mainprogram]` | String | Yes | Main program (triggers auto-enrollment) |
| `student[batch]` | String | No | Batch year/identifier |
| `student[dob]` | Integer | No | Date of birth (Unix timestamp) |
| `student[gender]` | String | No | Gender |
| `student[mobile]` | String | No | Mobile number |
| `student[programregister]` | String | No | Program registered for |
| `student[elective]` | String | No | Elective chosen |
| `student[usn]` | String | No | University Serial Number |
| `student[fathername]` | String | No | Father's name |
| `student[mothername]` | String | No | Mother's name |
| `student[nationality]` | String | No | Nationality |
| `student[debid]` | String | No | DEB ID |
| `student[abcid]` | String | No | ABC ID |

#### Example cURL Request (Same as before!)

```bash
curl -X POST "http://your-moodle-site.com/webservice/rest/server.php" \
     -d "wstoken=YOUR_TOKEN_HERE" \
     -d "wsfunction=local_ams_create_student" \
     -d "moodlewsrestformat=json" \
     -d "student[firstname]=Rajesh" \
     -d "student[lastname]=Kumar" \
     -d "student[email]=rajesh.kumar@example.com" \
     -d "student[enrolmentnumber]=ENR2026001" \
     -d "student[mainprogram]=Bachelor of Computer Application" \
     -d "student[batch]=JAN2026" \
     -d "student[mobile]=9876543210" \
     -d "student[gender]=Male" \
     -d "student[nationality]=Indian" \
     -d "student[abcid]=ABC123456"
```

#### Example Response (New fields: `enrolledcourses` and `enrollmenterrors`)

```json
{
    "id": 245,
    "username": "rajesh.kumar@example.com",
    "emailsent": true,
    "enrolledcourses": [
        {
            "courseid": 12,
            "shortname": "LBESEM0",
            "fullname": "Pre-Semester Foundation Course"
        },
        {
            "courseid": 18,
            "shortname": "FCIBCAJAN2026",
            "fullname": "BCA - January 2026 Orientation"
        }
    ],
    "enrollmenterrors": []
}
```

> **Note**: If `enrolledcourses` is empty and `enrollmenterrors` has messages, check:
> - The course shortnames exist in Moodle
> - The `mainprogram` value exactly matches one from the mapping table
> - Manual enrollment is enabled on the courses

#### Response

```json
{
    "id": 123,
    "username": "john.doe@example.com",
    "emailsent": true
}
```

---

### API 2: Enroll Existing Student (`local_ams_enrol_student`)

Enrolls an existing student in specific courses by course IDs.

#### Example cURL

```bash
curl -X POST "http://your-moodle-site.com/webservice/rest/server.php" \
     -d "wstoken=YOUR_TOKEN_HERE" \
     -d "wsfunction=local_ams_enrol_student" \
     -d "moodlewsrestformat=json" \
     -d "userid=123" \
     -d "courseids[0]=2" \
     -d "courseids[1]=5"
```

#### Response

```json
{
    "status": "success",
    "enrolledcourses": [2, 5]
}
```

---

## Integration Workflow

### From AMS Fee Payment to LMS Access

```
┌─────────────────────┐
│ Student pays fees   │
│ in AMS              │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│ AMS calls Moodle API:                   │
│ local_ams_create_and_enrol_student      │
└──────────┬──────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│ Moodle Plugin:                          │
│ 1. Creates user account                 │
│ 2. Sets custom profile fields           │
│ 3. Maps program to courses              │
│ 4. Enrolls in pre-semester courses      │
│ 5. Sends welcome email with credentials │
│ 6. Notifies admins                      │
└──────────┬──────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│ Student receives email with:            │
│ - Username (email)                      │
│ - Password                              │
│ - Login link                            │
└──────────┬──────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│ Student logs in and accesses            │
│ pre-semester courses automatically      │
└─────────────────────────────────────────┘
```

## Error Handling

The API returns appropriate error messages for common scenarios:

- **Email already exists**: `"emailtaken"`
- **Course not found**: Listed in `errors` array
- **Program mapping not found**: Listed in `errors` array
- **Enrollment failed**: Details in `errors` array

## Security Considerations

1. **Token Security**: Keep your web service token secure. Do not expose it in client-side code.
2. **HTTPS**: Always use HTTPS for production environments.
3. **IP Restrictions**: Consider restricting web service access by IP address in Moodle settings.
4. **Capability Checks**: The plugin requires appropriate capabilities (`moodle/user:create`, `moodle/course:enrolconfig`).

## Troubleshooting

### Check if plugin is registered
```bash
php admin/cli/purge_caches.php
php admin/cli/upgrade.php
```

### Test API connection
```bash
curl "http://your-moodle-site.com/webservice/rest/server.php?wstoken=YOUR_TOKEN&wsfunction=core_webservice_get_site_info&moodlewsrestformat=json"
```

### View logs
Check Moodle error logs and web server logs for detailed error messages. The plugin logs important events with the prefix "AMS:".

## Support

For issues or questions, contact the LMS Team:
- **Email**: sharan.kumar@usdcglobal.com
- **Team**: Sharanakumar D. Pattankar - LMS Team

## Version History

- **v1.2** (Dec 2025): Added `create_and_enrol_student` API with automatic program-based enrollment
- **v1.1** (Nov 2025): Added custom profile fields support
- **v1.0** (Oct 2025): Initial release with basic student creation
