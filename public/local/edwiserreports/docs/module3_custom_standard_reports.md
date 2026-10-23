# Module 3: Custom & Standard Reports — Documentation

## Overview
This module modernizes and standardizes the custom and standard reports in Edwiser Reports Pro for Moodle 5.0+ compatibility, accessibility, and maintainability.

## Key Changes
- **Search filter logic:**
  - Search input now performs a global search across all columns in the DataTable, not just the first column.
  - Search input is accessible (ARIA label added).
- **UI glitches in custom report filters:**
  - Select2 dropdowns for cohort and course selection are now forced to 100% width for consistent appearance.
  - Field group containers use `w-100` for alignment.
  - Comments added for maintainers about duplicate checkboxes and Select2 width.
- **Table column data visibility/alignment:**
  - Preview table uses `w-100 edwiser-report-table` for full width and easier CSS targeting.
  - DataTables initialization includes `autoWidth: false` and `responsive: true` for better column sizing and alignment.
  - Comments added for maintainers about columns/data structure matching.

## Technical Approach
- Followed Moodle's AMD/JS, accessibility, and PHP coding standards.
- Used DataTables and Bootstrap 4 for table rendering and responsiveness.
- All new/changed JS code is modular and maintainable.

## Manual QA Steps
- Verify search input filters all columns and is accessible.
- Check dropdowns are full width and field groups/checkboxes are rendered correctly.
- Ensure all columns are visible, properly aligned, and responsive in the report preview.
- Review for any UI glitches or alignment issues in custom and standard reports.

## References
- See `specs/03_custom_standard_reports.md` for detailed technical specs and tasks. 