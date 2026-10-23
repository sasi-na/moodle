# Module 5: Data Export

## Technical Specifications

- **Export options not clickable**
  - JS: Use AMD for export menu logic, check for JS errors, ensure z-index, attach event listeners using AMD.
  - Ensure export menu is accessible (keyboard, ARIA).

- **Exported Site Overview report includes users not visible on the report page**
  - Align export and on-screen filters.
  - Write a test to compare on-screen and exported data for consistency.

- **CSV export fails for files with long names**
  - Use Moodle's clean_filename() for all generated filenames.
  - Truncate if necessary.

- **Deprecation warning in CSV export from the completion block**
  - Use is_numeric() and provide a fallback value (e.g., 'N/A') for non-numeric grades.

## Implementation Tasks

- [ ] **Enable Export Options**
  - JS: Use AMD, check for JS errors, ensure z-index, attach listeners, ensure accessibility.

- [ ] **Correct Data Discrepancy in Exported Report**
  - Align export and on-screen filters, write consistency test.

- [ ] **Fix CSV Export Failure with Long Filenames**
  - Use clean_filename(), truncate if needed.

- [ ] **Fix Deprecation Warning in Completion Block CSV Export**
  - Use is_numeric() and fallback value for non-numeric grades. 