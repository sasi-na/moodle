# Module 1: Core Dashboard & General UI — Documentation

## Overview
This module modernizes and standardizes the dashboard UI for Edwiser Reports Pro, making it fully compatible with Moodle 5.0+.

## Key Changes
- Replaced legacy dropdown logic with a robust, accessible, and testable AMD module (`dashboardui.js`).
- All dropdowns now:
  - Open/close with event delegation and global click handling
  - Close other dropdowns when one is opened
  - Are accessible via keyboard (Enter/Space) and have correct ARIA attributes
- The "Add Insight" button now uses a modular, mockable AJAX logic for adding dashboard blocks, with error handling.
- All event listeners are re-initializable after AJAX content loads.
- All UI logic is now testable and maintainable, separated from unrelated code.

## Technical Approach
- See [../specs/01_core_dashboard_general_ui.md](../specs/01_core_dashboard_general_ui.md) for detailed specifications and tasks.
- All dropdown and Insights logic is encapsulated in `amd/src/dashboardui.js`.
- `amd/src/main.js` now delegates UI logic to `dashboardui.js` and only coordinates initialization.

## User-Facing Improvements
- Consistent, accessible dropdown menus throughout the dashboard
- Reliable Insights block addition with error feedback
- Improved keyboard navigation and screen reader support

## Implementation Notes
- If new dropdowns or Insights features are added, use `dashboardui.init()` after DOM or AJAX updates.
- For future test automation, see the structure in `tests/js/module1_dashboard_ui.test.js`.

## Manual QA Steps
- Open and close dropdowns, verify only one is open at a time
- Use keyboard to open/close dropdowns and check ARIA attributes
- Add an Insight block and verify correct AJAX and error handling
- After AJAX content loads, ensure all UI features still work 