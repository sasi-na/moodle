# Module 1: Core Dashboard & General UI

## Technical Specifications

- **Dropdown menus overlap and don't close properly**
  - Use AMD module for JS UI logic.
  - Implement a global document click event listener using event delegation.
  - Re-initialize dropdown event listeners after AJAX loads.
  - Encapsulate dropdown logic; close all others when toggling.
  - Validate sesskey for CSRF in AJAX actions.
  - Ensure accessibility (keyboard navigation, ARIA attributes).

- **"Date" label and filter are misaligned**
  - In dashboard header template, wrap label and dropdown in a div with a unique class (e.g., `.edwiser-date-container`).
  - Use Flexbox for alignment; add gap.
  - Use unique classes to avoid CSS conflicts.

- **Inconsistent icon and text colors in buttons**
  - In styles.css, target both icon fonts and SVGs inside button class and set `color: inherit`.
  - Use unique classes for plugin buttons.

- **Inability to add "Insights" (blocks) to the dashboard**
  - JS: Use AMD, check for JS errors, use core/ajax API, validate sesskey, log errors, re-init listeners after AJAX.
  - PHP: Use required_param/optional_param for input validation, use Moodle's DB/capability APIs, log errors.

## Implementation Tasks

- [ ] **Dropdown Overlap & Closing**
  - Implement AMD-based global click listener with event delegation.
  - Re-init dropdown listeners after AJAX.
  - Ensure accessibility and CSRF protection.

- [ ] **Align "Date" Label & Filter**
  - Update template to wrap label and dropdown in a uniquely-classed div.
  - Apply Flexbox and gap in CSS.

- [ ] **Standardize Button Icon Colors**
  - Add CSS for `.btn-edwiser-reports i, .btn-edwiser-reports svg { color: inherit; }`.

- [ ] **Debug/Add Insights Functionality**
  - JS: Use AMD, core/ajax, sesskey, error logging, re-init listeners.
  - PHP: Use param validation, DB/capability APIs, error logging. 