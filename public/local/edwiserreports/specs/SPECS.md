# Edwiser Reports Pro Moodle 5.0+ Compatibility: Specifications Overview

This document provides an overview of the technical specifications and implementation tasks required to make the Edwiser Reports Pro plugin fully compatible with Moodle 5.0+.

## Purpose
To ensure Edwiser Reports Pro is robust, maintainable, and future-proof for Moodle 5.0+ by addressing UI/UX, deprecation, functional, and export issues in a modular and standards-compliant way.

## Scope
- All compatibility, modernization, and bug-fix work for Edwiser Reports Pro as outlined in the technical specifications and task lists.
- Each module is specified in detail in its own markdown file in this directory.

## Modules

- [01_core_dashboard_general_ui.md](./01_core_dashboard_general_ui.md):
  - General UI, dashboard layout, dropdowns, button styling, and dashboard block (Insights) logic.

- [02_report_blocks.md](./02_report_blocks.md):
  - Report block UI, ellipsis actions, tooltips, deprecation warnings, and activity completion logic.

- [03_custom_standard_reports.md](./03_custom_standard_reports.md):
  - Custom and standard report UI, search/filtering, dropdowns, table alignment, and modal/tab logic.

- [04_scheduled_tasks_cron.md](./04_scheduled_tasks_cron.md):
  - Scheduled tasks, cron jobs, deprecation warnings, context/tag data integrity, and email configuration.

- [05_data_export.md](./05_data_export.md):
  - Export menu UI, export logic, filename handling, and export data consistency.

## How to Use
- Each module markdown file contains both the technical specifications and the actionable tasks for that module.
- Use these documents as the single source of truth for implementation, code review, and QA.

---

For detailed requirements and tasks, see the corresponding module files in this directory. 