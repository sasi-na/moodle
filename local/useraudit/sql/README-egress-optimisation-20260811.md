# Egress optimisation, 2026-08-11 (jain-prod)

`learn.onlinejain.com` served **5.01 TB egress in 30 days** (measured at the GKE
Gateway backend, 2026-07-11 → 08-10). Three fixes removed roughly **45%** of it.
This directory holds the two content scripts; the third fix is in `deploy/`.

Egress composition, from a 25-minute deduplicated nginx sample
(11.33 GB / 82,832 requests, 2026-08-11 07:03–07:29 UTC):

| | share | note |
|---|---|---|
| `pluginfile: submission_files` | 55.1% | 8.5 MB average — video assignment downloads, not optimisable |
| `pluginfile: overviewfiles` | 10.8% | course covers → `covers_optimise.php` |
| `lib/requirejs.php` | 10.0% | 966 KB avg, already `immutable`; a `jsrev` bump re-costs it fleet-wide |
| theme_baz chrome | 7.6% | → `theme_images_install.php` |
| `theme/styles.php` | 2.7% | was **30.8%** before the pre-warm fix |

**Measure shares, never extrapolate the window's rate.** That sample window is a
~4x traffic peak (452 MB/min against a 116 MB/min daily average); scaling it to a
day overstated one saving by 4x. Apply *share × known monthly LB total* instead.

## 1. theme/styles.php served with `no-store` — 31% of all egress

Not in this directory: fixed by the `warm-theme-css` initContainer in
`deploy/base/deployment.yaml` (merged via MR !149).

`$CFG->localcachedir` is `/tmp/moodle-cache`, an `emptyDir`, so it is empty on every
pod start. `theme/styles.php` then found no `all_<subrev>.css`, fell back to
`$CFG->tempdir/theme/baz/all.css` on NFS, and served it via
`css_send_temporary_css()` — which sets `no-cache, no-store, must-revalidate`. It
tried to regenerate while holding a **1-second** lock, lost that race under load,
and so never populated the cache. Result: 2.3 MB of CSS re-downloaded on *every*
page view, for the life of the pod.

Only jain-prod was affected; at lower traffic the 1s lock is winnable, and
sharda / nitk / learn-usdc were verified already serving `immutable`.

**Do not use `admin/cli/build_theme_css.php` to fix this on a live fleet.** It bumps
the theme sub-revision *globally*, so with N replicas only the pod that bumped last
owns a matching file. Worse, a request whose URL sub-revision disagrees with the
serving pod takes the generate path and blocks `rand(90,120)` **seconds** on
`core_theme_get_css_content`, pinning a php-fpm worker (measured: 60 ms matching,
>10 s timeout mismatched).

## 2. `theme_images_install.php` — theme chrome images

**2.27 MB → 272 KB (−88%).** Seven images, worst offenders a 5000×2500 PNG body
background (634 KB → 37 KB) and four 5375px-wide JPEG banners (~91% each).

- The banners are flat vector artwork with text, which JPEG handles badly and WebP
  handles superbly. Same-format re-encoding is a **trap** — it made `loginbg`
  *bigger* (176 KB → 218 KB).
- Prod GD has **no WebP support** and no Imagick, so these were converted offline
  with `cwebp -q 82` and are committed under `assets/theme_baz_webp_20260811/`.
- `webp` was **not registered** in `core_filetypes` — a `.webp` would be stored and
  served as `document/unknown`. The script registers it. The theme's own
  `accepted_types` already listed `.webp`, so it was only ever the registration
  that was missing.
- **Skipped deliberately: `block5itemimg2-5`** (3.7 MB of 1920×1080 PNGs). Zero
  requests — only `settings/block5.php` and the pluginfile handler in `lib.php`
  reference them; no template renders them. Optimising them saves nothing.
- Saving a theme setting calls `theme_reset_all_caches()`, which bumps themerev and
  wipes the theme localcache — undoing the CSS pre-warm on running pods. **Always
  `kubectl rollout restart deploy/moodle -n <ns>` afterwards.**

## 3. `covers_optimise.php` — course cover images

**224.8 MB → 30.0 MB**, average 355 KB → 47 KB, max 2.92 MB → 0.18 MB. 364 files
over 200 KB converted (−91% on those); afterwards nothing exceeds 200 KB.

Many were 1448×1086 PNGs of 2–3 MB, rendered into `.rui-course-card-img-top` —
`width:100%; height:150px; background-size:cover`
(`theme/baz/scss/moodle/course.scss:16-26`). The win is **resize + PNG→JPEG, not the
codec**, so the pod's own GD is sufficient and nothing needs transferring.

- 364 rows share only **191 distinct contenthashes** — encode once per hash and let
  Moodle's dedup do the rest.
- Course file URLs carry **no themerev**, so this needs **no pod restart** and does
  not disturb the CSS pre-warm.
- Old `.png` URLs 404 after conversion. Cosmetic: a cached page shows one broken
  thumbnail until it re-renders; `purge_by_event('changesincourse')` covers fresh
  renders.
- Rollback: originals in `acu_bak_overviewfiles_20260811` (364 rows, 213.9 MB),
  built with `CREATE TABLE LIKE` + `INSERT ... SELECT` because **`CREATE TABLE AS
  SELECT` fails on Galera/PXC**.

Run `covers_safety_check.php` first on any new tenant. It verifies no cover has a
real alpha channel (JPEG would black it out) and that no course summary references
`overviewfiles` by URL (which would make renaming unsafe). On jain-prod both were
clean: 0 of 60 sampled PNGs had transparency, 0 summaries referenced the filearea.

## Usage

All three scripts are **dry-run by default** and take `--commit` to write.

    php local/useraudit/sql/covers_safety_check.php
    php local/useraudit/sql/covers_optimise.php --limit=8       # preview
    php local/useraudit/sql/covers_optimise.php --commit

    php local/useraudit/sql/theme_images_install.php
    php local/useraudit/sql/theme_images_install.php --commit
    kubectl rollout restart deploy/moodle -n jo-moodle-prod     # required after

## Not done

- **Cloud CDN is not a cost lever.** Compute Engine publishes 48 `CDNCacheFill` SKUs
  and *zero* cache-egress SKUs, so CDN egress bills at the same
  Internet-Data-Transfer-Out rate — it saves nothing and adds fill cost
  ($0.02/GiB intra-region). Only sending fewer bytes helps. Note
  `GCPBackendPolicy` v1 supports `logging` but has no `cdnPolicy` field.
- **Ingress is free** — GCP does not bill inbound transfer, so upload volume is a
  capacity question (php-fpm workers, NFS IOPS), not a billing one.
- A **self-healing re-warm** is still missing: an admin saving a theme setting
  re-breaks CSS caching until someone restarts the pods.
- LB request logging and a BigQuery billing export are both still off, so all cost
  figures here are catalog-price estimates rather than invoice numbers.
