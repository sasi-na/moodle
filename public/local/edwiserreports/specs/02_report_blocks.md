# Module 2: Report Blocks

## Technical Specifications

- **Ellipsis icon for block actions misplaced/non-functional**
  - In block header template, ensure ellipsis menu is a child of header.
  - Use CSS: position: relative on header, absolute on menu, unique classes.
  - Ensure keyboard accessibility (tabindex, ARIA attributes).
  - Attach event listeners using AMD.

- **Info icon hover text (block description) not visible**
  - Use AMD for UI JS.
  - Use Moodle's core/tooltip AMD module for tooltips.
  - Re-init tooltips after AJAX.
  - Ensure accessibility (aria-labels, keyboard focus).

- **Deprecation warnings in "Active Users" block scheduled task**
  - Declare all dynamic properties at top of class with PHPDoc.

- **Incorrect count for "Completed activities"**
  - Debug SQL and logic; use Moodle's DB API.
  - Compare with core course completion logic.
  - Write PHPUnit tests to verify count logic.

## Implementation Tasks

- [ ] **Fix Ellipsis Icon for Block Actions**
  - Update template and CSS for correct structure and positioning.
  - Ensure accessibility and AMD event handling.

- [ ] **Fix Info Icon Hover Text**
  - Use core/tooltip AMD module, re-init after AJAX, ensure accessibility.

- [ ] **Fix Deprecation Warnings in "Active Users" Task**
  - Declare properties with PHPDoc at top of class.

- [ ] **Correct "Completed Activities" Count**
  - Debug SQL/logic, use DB API, write PHPUnit tests. 