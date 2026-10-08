# Tenant-scoped static assets

Files here ship in the shared image but are **not served by any tenant** unless
that tenant asks for them. A tenant opts in by naming the directory in the
`TENANT_ASSETS` environment variable in its own overlay; the `setup-logs-and-code`
init container copies each named directory into the web root at pod start.

## Why this exists

`jain-images/` sat at the web root of the shared image, so all five institutions
served it. `https://learning.iiitdwd.ac.in/jain-images/banner-jain-lms.png`
returned 200 — Jain marketing creative, on IIITD's domain. The same was true on
nitk and usdc.

Nothing in the codebase or in any tenant's database referenced the files, so
they were almost certainly working files committed by accident. They are kept
rather than deleted because an absolute URL to them may exist outside Moodle --
in an email template at the ESP, for instance, which is not visible from here --
and Jain keeps serving them so any such link survives.

Same reasoning and same shape as `tenant-plugins/`: one institution's content
should not be reachable on another's domain.
