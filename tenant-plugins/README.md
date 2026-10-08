# Tenant-scoped plugins

Plugins here are shipped in the shared image but are **not installed anywhere by
default**. Moodle only scans its own plugin-type directories (`mod/`, `blocks/`,
`local/`, …), so a plugin parked here is invisible to every tenant: no tables,
no entry in the activity chooser, nothing for `admin/cli/upgrade.php` to install.

A tenant that wants one copies it into place from its own overlay, by extending
the `setup-logs-and-code` init container — see
`deploy/overlays/iiitd-prod/patch-tenant-plugins.yaml`.

## Why this exists

`mod/pdfannotator` was dropped at the Moodle 5.0 migration, but IIITD's
production import carried four live activities that referenced it, leaving them
broken in real courses. Restoring the plugin fixed those four — but the image is
shared, and `deploy:production` runs `admin/cli/upgrade.php`, so simply putting
it back under `mod/` would have **installed it on all five tenants**: eight
tables in each of four databases, and a new activity type in their chooser, for
an institution that has never used it.

The plugin is one institution's concern. This directory keeps it that way
without needing a second image or a second pipeline.
