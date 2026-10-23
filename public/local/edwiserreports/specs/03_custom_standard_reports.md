# Module 3: Custom & Standard Reports

## Technical Specifications

- **Search filter in reports not working**
  - JS: Use AMD, core/ajax API, capture and send search term, re-init listeners after AJAX.
  - PHP: Use required_param/optional_param and PARAM_* types, log SQL in debug mode only.

- **UI glitches in Custom Reports filter (dropdown width, extra checkboxes)**
  - Use unique classes for dropdowns/checkboxes.
  - Set min-width/width on dropdown containers.
  - Update template to match Moodle's form rendering.

- **Table column data not visible/aligned**
  - Use parent class (e.g., .edwiser-report-table) for all report tables for CSS specificity.
  - Use more specific CSS selectors.

- **"All Scheduled Reports" tab not clickable in modals**
  - JS: Re-bind click event handlers after modal loads, check z-index, ensure accessibility.

- **"Undefined" popup on Course Activity Summary**
  - JS: Check AJAX response is valid before use, log response for debugging.

## Implementation Tasks

- [ ] **Fix Report Search Filter**
  - JS: Use AMD/core/ajax, re-init listeners.
  - PHP: Use param validation, debug logging.

- [ ] **Fix UI Glitches in Custom Reports Filter**
  - Use unique classes, set min-width, update template.

- [ ] **Fix Table Column Visibility & Alignment**
  - Use parent class for tables, update CSS selectors.

- [ ] **Enable "All Scheduled Reports" Tab in Modals**
  - Re-bind event handlers after modal loads, check z-index, ensure accessibility.

- [ ] **Fix "Undefined" Popup on Course Activity Summary**
  - Check AJAX response validity, log for debugging. 