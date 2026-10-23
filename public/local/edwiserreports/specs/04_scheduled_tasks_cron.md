# Module 4: Scheduled Tasks & Cron

## Technical Specifications

- **Deprecation warnings in "Update Course Progress Data" task**
  - Declare the progress property in both classes with PHPDoc.

- **Cron task failure: "Can't find data record in database table context"**
  - Prefer using Moodle's admin tools or CLI scripts for data cleanup.
  - If manual DB queries are needed, document and provide a script.
  - Ensure plugin code cleans up context/tag data when deleting items.

- **Email function could not be instantiated during cron**
  - Document that this is a server config issue, not a plugin code issue.
  - Verify SMTP settings in Moodle admin.

## Implementation Tasks

- [ ] **Fix Deprecation Warnings in "Update Course Progress" Task**
  - Declare $progress property with PHPDoc in both classes.

- [ ] **Fix Cron Task Failure (Database Context)**
  - Investigate orphaned context data, use admin tools/CLI for cleanup, document manual queries/scripts, ensure code cleans up context/tag data.

- [ ] **Fix Email Instantiation Error**
  - Verify SMTP settings in Moodle admin, document as server config issue. 