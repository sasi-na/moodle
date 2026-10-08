# deploy/ — multi-tenant Kubernetes manifests

One codebase, one image, many LMS instances.

```
deploy/
  base/                  tenant-agnostic Moodle workload
  overlays/
    jain-prod/           learn.onlinejain.com   → ns jo-moodle-prod   (adopted, diff-clean)
    jain-qa/             dev.lms.thekaios.com   → ns jain-moodle-qa   (built, NOT diff-clean)
    nitk-prod/           learn.nitk.edu.in      → ns nitk-prod        (live)
    usdc-prod/           learn.usdcglobal.com   → ns learn-usdc-prod  (adopted 2026-09-01)
    global-jain-prod/    global.onlinejain.com  → ns global-jain-prod (adopted 2026-09-02)
    iiitd-prod/          learning.iiitdwd.ac.in → ns iiitd-prod        (migrated off AWS)
    learningshala-prod/  learn.learningshala.co.in → ns learningshala-prod (fresh install 2026-09-16)
```

All of them run on the **same cluster**, `usdc-prod-cluster-1`. There is no separate
staging cluster — see the QA section below.

## Adopting a tenant that already runs its own image

`usdc-prod` and `global-jain-prod` are the worked examples, and the order
matters. Both were live on their own fork-built images, so the overlay had to
describe the running namespace before it could change it. Steps 6-9 exist because
usdc hit each of them the hard way; global-jain hit none of them.

1. **Read the live objects first, write the overlay from them** — not from
   another tenant's overlay. Every number in `patch-scale`, `patch-hpa`,
   `patch-redis` and both `nfs-*` files came from `kubectl get -o json`. Copying
   nitk's sizing instead would have multiplied usdc's reservation on a shared
   node pool for a site with three accounts.
2. **Check `spec.selector.matchLabels` matches base** (`app: moodle`). It is
   immutable; a mismatch means `apply` is rejected and the Deployment has to be
   deleted and recreated to fix.
3. **Diff plugin versions against the shared image before anything else.** A
   tenant whose plugin is *newer* than the image cannot be adopted — Moodle
   refuses to downgrade a plugin and the upgrade step aborts. usdc had zero
   downgrades, 11 upgrades and 21 new installs, and nothing installed that the
   image lacks (which would have orphaned its tables).
4. **Verify the Secret before switching `$CFG->dbpass` to `getenv()`.** Compare
   the live env value against the ConfigMap literal inside a pod; if they differ,
   the switch takes the database offline.
5. `kubectl diff -k` until the only remaining differences are the ones you
   intend, then let CI apply it.
6. **Prune orphaned `{event}` rows BEFORE the deploy, not after.** `mod_feedback`'s
   2024100701 step loads every feedback open/close calendar event and calls
   `calendar_event->delete()`, which resolves the course context first. Any event
   pointing at a deleted course throws and the whole upgrade aborts mid-run — and
   CI's retry loop reads the deterministic failure as a transient pod race and
   burns all five attempts. `local/commonutils/cli/prune_orphan_events.php` is
   plain clilib + DML, so it runs on the tenant's OLD image: copy it in and run
   it before playing `deploy:production` the first time. usdc was pruned after a
   failed deploy; global-jain was pruned before, and its upgrade ran clean.
7. **Find every workload that mounts `moodle_config.php`** before switching
   `$CFG->dbpass` to `getenv()`. Base's cron/adhoc patches inject
   `MOODLE_DATABASE_PASSWORD`, but a tenant may have bespoke jobs that mount the
   same config and have no database env at all — global-jain's weekly submission
   report was one, and it would have started failing silently: a job nobody
   watches whose output is an email that just stops arriving.
8. **Diff the LIVE CR against Helm's stored values, not just the rendered
   output.** A `*-pxc-values.yaml` must be the COMPLETE user-supplied value set
   (see the PXC section), and settings patched straight into the CR are invisible
   to `helm get values` — usdc's `pxc_strict_mode` was reverted that way.
9. **`kubectl diff -k` is the review step, not a formality.** On usdc it caught a
   patch that hoisted a native sidecar ahead of the initContainer that populates
   its volume. On global-jain it caught six fields missing from a hand-reproduced
   CronJob, including `timeZone: Asia/Kolkata` — without which a 07:00 Monday
   schedule silently becomes 12:30 IST — and the `securityContext` that keeps it
   running as uid 33 instead of root.

Two things adoption changes that are easy to miss, because they are inherited
from base rather than written in the overlay: the tenant picks up base's
`nginx.conf`, `php.ini` and `www.conf` in place of its own drifted copies, and
its CronJobs move from inline plaintext passwords to `secretKeyRef`.

Render / verify (both read-only):

```sh
kubectl kustomize deploy/overlays/jain-prod       # render
kubectl diff -k    deploy/overlays/jain-prod      # compare against live
```

## Standing up a brand-new tenant

`nitk-prod` and `learningshala-prod` are the worked examples. A fresh install has
none of the adoption hazards above -- no plugin-version diff, no `{event}` prune,
no live objects to reproduce -- but it has its own ordering, and only the last
step needs DNS.

1. **Create the moodledata export on Filestore BEFORE the PV.** A missing export
   makes pods hang on mount rather than fail visibly. Mount `/moodle_nfs` (the
   share root) from a throwaway pod, `mkdir` the tenant directory, `chown 33:33`,
   `chmod 2755`. It must be a separate directory per tenant -- two sites sharing
   moodledata collide on filedir, sessions, muc and trashdir.
2. **Namespace, then `moodle-secrets`** (`database-password`,
   `database-root-password`, `admin-password`). Alphanumeric only: the value is
   interpolated into the CronJobs' mysqli wait-loop and into config.php.
3. **S3 credentials Secret**, or the PXC backup storage will not reconcile.
4. **PXC Helm release** from a complete `deploy/<tenant>-pxc-values.yaml`. Do not
   set `pxc.configuration` unless you have a reason -- the operator derives
   `innodb_buffer_pool_size` from the container memory, and a hand-set pool has to
   be kept in lockstep with every resize or mysqld will not start.
5. **Write the overlay**, then `kubectl apply -k --dry-run=server` until clean.
   Include a `HealthCheckPolicy`: on a new tenant the Service genuinely is created
   before any pod exists, so the Gateway has no readinessProbe to infer from and
   defaults to `requestPath: "/"`, which Moodle answers with a 303. Every backend
   goes UNHEALTHY and the LB serves 503 while the pods look perfectly healthy.
6. **HTTPRoute** into the `gateway-api` Helm release in `default`. Safe to add
   before DNS -- it simply receives no traffic.
7. **Two `.gitlab-ci.yml` matrix entries** (`deploy:production`, `backup:production`).
8. **Deploy, then install** with `$CFG->wwwroot` already at the real hostname, and
   apply the config seed (see the seeding section below).
9. **Certificate** -- the only DNS-gated step. The cert map `thekaios-cert-map`
   uses load-balancer-authorized managed certs (there are zero DNS authorizations
   in the project), so Certificate Manager validates by reaching the hostname at
   the LB. The tenant's A record must already point at `34.95.112.39`; issuance is
   then a matter of minutes.

Everything except step 9 can be built and validated first:

```sh
kubectl -n <ns> port-forward svc/moodle-service 8080:80
curl -sI --resolve <host>:443:34.95.112.39 https://<host>
```

## Work register

Only **two** items actually need DNS. Everything else can proceed in parallel.

| # | Item | Blocked by | Needs DNS? |
|---|---|---|---|
| 1 | NITK DNS → `34.95.112.39` | NITK IT (external) | — |
| 2 | TLS certificate + cert map entry | 1 | **yes** |
| 3 | NITK PXC cluster (Helm) + `nitkdb` schema | — | no |
| 4 | NFS export `/moodle_nfs/nitk_data_prod` | — | no |
| 5 | `moodle-secrets` Secret in `nitk-prod` | — | no |
| 6 | ~~Split the ConfigMap (base vs per-tenant)~~ **done** | — | no |
| 7 | ~~Redis into base~~ **done** | — | no |
| 8 | ~~Apply the CronJob secret fix to jain-prod~~ **done, applied & verified** | — | no |
| 9 | **Rotate the leaked DB + admin passwords** | — | no |
| 10 | Sealed Secrets / ESO, then manage the Secret | 9 | no |
| 11 | ~~Merge the multi-tenant CI pipeline~~ **done** | — | no |
| 12 | Rename image → `moodle-lms` — **phase 1 done**; phase 2 flips overlays + drops legacy tag | first deploy on new name | no |
| 13 | ~~Build + verify a `jain-qa` overlay~~ **done** | — | no |
| 19 | Point `deploy:staging` at the jain-qa overlay | 13 | no |
| 14 | Delete `Deployment-files/` | 6, 11, 13 | no |
| 15 | NITK Moodle install + enable CI matrix entry | 3,4,5,6,7 | no* |
| 16 | ~~Decide routing ownership~~ **done — stays in Helm** | — | no |
| 20 | Add nitk's HTTPRoute to the `gateway-api` Helm release | — | no |

\* nitk can be installed and fully validated before DNS moves — see below.

† Now happens automatically: `deploy:production` applies the whole overlay, and
the CronJob secret fix is the only thing in it that differs from live. Applying
by hand first is still fine, and gives a smaller blast radius on its first run.

### Validating nitk before DNS cuts over

DNS gates the certificate and public access, nothing else. The site can be stood
up and exercised end-to-end first, which is the right order anyway: cut DNS over
to something already known to work.

```sh
# once the overlay is applied and Moodle installed
kubectl -n nitk-prod port-forward svc/moodle-service 8080:80
curl -sI http://localhost:8080/login/index.php

# or exercise the real Gateway path, bypassing DNS
curl -sI --resolve learn.nitk.edu.in:443:34.95.112.39 https://learn.nitk.edu.in
```

The `--resolve` form will fail TLS until item 2 is done — that is expected and
is precisely the check that tells you the certificate has issued.

Install Moodle with `$CFG->wwwroot = 'https://learn.nitk.edu.in'` from the start.
Installing under a temporary hostname and changing `wwwroot` later means a
search-replace across the whole database; not worth it.

## Seeding nitk's database — fresh install, not a clone

nitk starts from a **fresh Moodle install plus a configuration seed**, not a copy
of jain's database.

Measured on jain, 2026-08-07:

| | |
|---|---|
| Database | **50.9 GB across 592 tables** |
| Users | 62,152 |
| Courses | 1,766 |
| Enrolments | 1,038,107 |
| Configuration actually worth carrying | **~8,700 rows** |

Clone-and-purge would mean starting from 50.9 GB and deleting down to those 8,700
rows across 592 tables. Moodle declares **no foreign keys**, so orphaned rows are
silent, and one missed table puts jain students' dates of birth, parents' names,
phone numbers and ABC/DEB IDs inside a different university's LMS. Building from
a clean schema makes that leak structurally impossible rather than dependent on
enumerating 592 tables correctly. It is also far less work.

```sh
# 1. Export config from jain (read-only)
POD=$(kubectl -n jo-moodle-prod get pod -l app=moodle -o jsonpath='{.items[0].metadata.name}')
kubectl -n jo-moodle-prod exec "$POD" -c php-fpm -- \
  php deploy/tools/export-tenant-config.php > /tmp/nitk-seed.sql

# 2. Review it. Confirm nothing references users, courses or enrolments,
#    and read the skipped-credentials count in the footer.

# 3. Fresh install on nitk, apply the seed, upgrade
php admin/cli/install_database.php --agree-license --adminuser=admin
mysql nitkdb < /tmp/nitk-seed.sql
php admin/cli/upgrade.php --non-interactive
```

The exporter carries plugin settings for the bespoke plugins and theme, custom
profile field **definitions** (never `user_info_data`), and custom roles with
their capability overrides. It strips every credential by name pattern, so SMTP,
API keys, VPL, Zoom/Teams and the AMS webservice token all have to be re-entered
on nitk. Core `config` rows are emitted commented out for review rather than
applied blindly.

If nitk also wants jain's **course structures**, that is a separate mechanism:
per-course backup/restore (`.mbz`) with "include user data" switched off. That is
the supported path and carries no personal data.

## Status

**Nothing here has been applied.** `deploy/` is additive; the live cluster is
still driven by `Deployment-files/`, unchanged.

`kubectl diff -k deploy/overlays/jain-prod` currently exits **0 with zero output** —
the overlay reproduces live production exactly, object for object and field for
field. That is the evidence that adopting it is a no-op rather than a change.

Re-run that diff immediately before adopting. It is only meaningful at the moment
it is run: every CI deploy moves the live image tag, and the overlay pins a tag.

## Why base was built from the live cluster, not from `Deployment-files/`

The committed manifests have drifted from live, and applying them would break the
site. Drift found on 2026-08-07:

| | `Deployment-files/prod/` | live | consequence if git were applied |
|---|---|---|---|
| php-fpm requests | `cpu 1` / `mem 3Gi` | `cpu 6` / `mem 12Gi` | **production cut from 6 cores to 1** |
| php-fpm limits | `cpu 2` / `mem 5Gi` | *(none)* | CPU throttling on every pod |
| `PHP_MEMORY_LIMIT` | `2G` | `4G` | OOM on heavy pages |
| config `$CFG->dbhost` | `moodle-db` | `mysql-db-pxc-db-haproxy` | **cannot reach the database** |
| cronjob `DBHOST` | `moodle-db` | `mysql-db-pxc-db-haproxy` | cron dead |
| config `$CFG->lock_factory` | *(absent)* | `\core\lock\db_record_lock_factory` | falls back to file locking on NFS |
| deployment image | `jain-moodle:5` | `jain-moodle:ba0091ea` | **rolls the site back to an old build** |

`moodle-db` is the stale, abandoned MySQL Deployment; the live database is the
PXC cluster. Anything still pointing at `moodle-db` is already broken.

The first three rows were caught only by `kubectl diff`, after the base had
already been written from the committed file. Diff-until-empty is the mechanism
that makes this safe — not careful reading.

**`Deployment-files/` has been deleted** (27 files). Leaving both trees meant the
next person would edit whichever they found first — and that one was the stale,
prod-breaking one. Nothing operational referenced it; only prose did.

### What happened to each thing it defined

**Migrated into `deploy/`** — Deployment, Service, HPA, GCPBackendPolicy, both
Moodle CronJobs, Redis, ConfigMap, Namespace, ServiceAccount, and
`moodle-nfs-backup` (now `overlays/jain-prod/cronjob-nfs-backup.yaml`, verified
diff-clean).

**Deleted as obsolete** — the objects no longer exist:

| file | why |
|---|---|
| `prod/mysql.yml` | the `moodle-db` Deployment is gone from prod; PXC replaced it |
| `prod/work-load.yml` | defined SA `moodle-gcs-ksa` in namespace `jo-moodle` — **neither exists** |
| `prod/moodle-db-backup.yml` | obsolete, see below |
| top-level `ingress.yml`, `ssl-certificate.yaml`, `storage_class.yaml`, `mysql_v2.yaml` | staging-era, for a cluster and namespace that no longer exist |

**Deliberately not carried over** — `prod/secret.yml`. It contained the
production database root password, database password and admin password **in
cleartext**. Removing it from the working tree stops it spreading further; it
does not un-leak git history, and does not rotate anything. The live Secret is
untouched.

### `moodle-db-backup` was broken, and is redundant

Worth stating plainly because it looks alarming: its last **successful** run was
**2026-06-26**, roughly six weeks before deletion. It runs
`mysqldump -h moodle-db`, and the `moodle-db` Service no longer exists — it was
removed during the PXC migration, which is exactly when the job stopped
succeeding.

**The database is nevertheless backed up.** The Percona operator takes daily full
backups to S3 (`0 1 * * *`, `keep=14`, PITR enabled), and every one has
succeeded — including 10 hours before this was written. `moodle-db-backup` was a
leftover of the pre-PXC world, duplicating a job the operator now does properly.

The **live CronJob still exists** and still fails nightly. Deleting it is tracked
separately — removing the file does not remove the object.

### Storage facts, preserved here because their manifests are gone

These objects are live, bound, and intentionally unmanaged (re-applying a bound
PV/PVC risks disturbing the claimRef). Recorded so they can be recreated:

| object | value |
|---|---|
| PV `moodle-nfs-pv` | 200Gi RWX, NFS `10.185.32.234:/moodle_nfs/jain_data_prod` |
| PVC `moodle-nfs-pvc` | 200Gi RWX, bound to `moodle-nfs-pv` |
| PVC `db-data-pvc` | 100Gi RWO, storageClass `standard-rwo` |
| SA `usdc-prod-service-account` | `usdc-prod-service-account@org-dev-infra.iam.gserviceaccount.com` |

One more live CronJob, `moodle-logstore-archive`, was **never** in
`Deployment-files/` and is defined nowhere in git. It is currently suspended
(`0 2 1 * *`). Tracked separately.

## nitk DNS and TLS — exact steps

**DNS is an A record, not a CNAME.** The Gateway publishes a static IP
(`34.95.112.39`), and there is no Google-provided hostname to point a CNAME at.
Every existing tenant does the same — `learn.onlinejain.com` and
`learn.shardaonline.ac.in` are both `A → 34.95.112.39`.

`learn.nitk.edu.in` **already resolves today**, to four NITK campus IPs
(`103.225.13.4`, `14.139.155.216`, `218.248.46.85`, `210.212.194.4`). It serves
*"Domain not found : NITK Surathkal"* — a campus catch-all, not a real service,
so nothing is being displaced. But the zone is controlled by NITK, not by us; the
response advertises `x-contact: Akshay Revankar <akshayrevankar@nitk.ac.in>`.
Ask them to replace those four records with a single `A → 34.95.112.39`.

For TLS, certificates are **load-balancer authorized**, not DNS authorized
(`gcloud certificate-manager dns-authorizations list` returns nothing, and
sharda's certificate shows a bare `authorizationAttemptInfo` with no
authorization object). That is the simpler path, but it means **issuance
validates by fetching over the load balancer, so DNS must point at
`34.95.112.39` first.** Doing it in the other order just fails.

```sh
# 1. NITK repoints DNS first. Confirm before proceeding:
dig +short learn.nitk.edu.in          # must return exactly 34.95.112.39

# 2. Google-managed certificate (LB-authorized, like every other tenant)
gcloud certificate-manager certificates create nitk-lms-prod \
  --domains=learn.nitk.edu.in \
  --project=org-dev-infra

# 3. Attach it to the shared map the Gateway already consumes
gcloud certificate-manager maps entries create nitk-lms-prod-entry \
  --map=thekaios-cert-map \
  --certificates=nitk-lms-prod \
  --hostname=learn.nitk.edu.in \
  --project=org-dev-infra

# 4. Wait for ACTIVE (issuance takes minutes to hours)
gcloud certificate-manager maps entries list --map=thekaios-cert-map \
  --project=org-dev-infra
```

The Gateway itself needs **no change** — it already terminates TLS from
`thekaios-cert-map`, and its listener is `allowedRoutes.namespaces.from: All`, so
the HTTPRoute in `nitk-prod` attaches without touching the Helm release.

> **Terraform.** Both the certificate map and the existing certificates carry
> `goog-terraform-provisioned: 'true'`. The gcloud commands above work, but they
> create drift against whatever Terraform owns this. Prefer adding the
> certificate and map entry in the Terraform repo and applying there; use gcloud
> only if that repo is not the source of truth for these resources.

## What is deliberately not managed yet

Each needs a decision or a cleanup before it belongs in a manifest tree. They are
excluded so that `kubectl diff` stays a trustworthy signal: an empty diff means
"everything managed here matches live", and that is only useful if nothing
managed here is known-wrong.

| Object | Status | Note |
|---|---|---|
| `CronJob moodle-cron` / `moodle-adhoc-tasks` | **now managed** | password moved to `secretKeyRef`; produces an intentional diff, see below |
| `ConfigMap moodle-config` | **now managed, split, diff-clean** | see below |
| `Secret moodle-secrets` | not managed | **DB and admin passwords are committed to git in cleartext** |
| `HTTPRoute` (all) | **not managed, by decision** | routing lives in the `gateway-api` Helm release — see below |
| `PV`/`PVC` (jain) | not managed | bootstrap-once, already Bound; re-applying risks the claimRef |
| Redis Deployment + Service | **now managed, diff-clean** | one per tenant namespace, never shared |
| PXC | not managed, and should stay that way | Helm + Percona operator own it, for every tenant |

### The CronJobs are now managed — applied and verified on 2026-08-07

**This is the one thing in `deploy/` that has actually been applied to
production.** Both CronJobs now carry zero occurrences of the plaintext password,
and `kubectl diff -k deploy/overlays/jain-prod` exits **0** — the overlay matches
live exactly.

Verified by watching real runs on the new spec:

| job | result |
|---|---|
| `moodle-cron-29768295` | `Complete 1/1`, 22m, *"Cron run completed correctly"* |
| `moodle-adhoc-tasks-29768310` | `Complete 1/1`, 8s, `secretRef=moodle-secrets` |

The database connected on the first attempt with zero `Database not ready`
retries, and the pod ran to completion with 0 restarts.

Two things that look alarming in the logs but are not:

- **433 "error" lines**, all from one plugin — 342 × *"Journal grader error:
  error/No submitted assignment found"* and 91 × *"No valid CSV file found"*.
  Pre-existing `local_journalgrader` application output, unrelated to this change.
- **A 22-minute run** against a `*/5` schedule. `cron_keepalive = 900` means cron
  keeps accepting new tasks for 15 minutes and finishes whatever it started, so a
  backlog overruns the window. With `concurrencyPolicy: Forbid` that blocks the
  next scheduled run, which is why cron effectively fires every 15–20 minutes
  rather than every 5. Worth its own look; not caused by this change.

### Why the diff was the point

`kubectl diff -k deploy/overlays/jain-prod` is no longer empty. It shows changes
to **exactly two objects**, `moodle-cron` and `moodle-adhoc-tasks`, and the only
substantive change is removing the production DB password:

- inline in the PHP wait-loop → `getenv("MOODLE_DOCKER_DBPASS")`
- `MOODLE_DOCKER_DBPASS: <literal>` → `secretKeyRef` on `moodle-secrets`

Deployment, Service, HPA and GCPBackendPolicy remain diff-clean, so the running
site is untouched by this apply. CronJobs are stateless — applying only affects
the next scheduled run — which makes this far lower-risk than a Deployment
change. Verify the next `moodle-cron` job completes before walking away.

**This does not rotate the password.** It stops it being printed into new
manifests. The value is already in git history and should be treated as
compromised and rotated on its own schedule.

### The ConfigMap split — done, and diff-clean

One ConfigMap named `moodle-config`, assembled from both layers. Six shared keys
come from `base/config/`; each overlay merges in its own `moodle_config.php`:

```
base/config/            healthz.php  my.cnf  nginx.conf  php.ini  redis.conf  www.conf
overlays/*/config/      moodle_config.php
```

The Deployment and both CronJobs reference `moodle-config` by name and are
**unchanged** — the object keeps its name and all seven keys, so nothing that
mounts it had to move.

**jain's rendered ConfigMap is byte-identical to live.** Every file was pulled
from the running cluster and verified by md5 against it, so `kubectl diff` does
not list the ConfigMap at all. Adopting it is a no-op: with `subPath` mounts,
identical content means running pods are unaffected and no restart is needed.

Two things worth knowing if you touch this again:

- **Source was live, not git.** `Deployment-files/prod/configmap.yml` had drifted
  on **six of its seven keys** — not just `moodle_config.php` but `nginx.conf`,
  `php.ini`, `my.cnf`, `www.conf` and `redis.conf` too. Only `healthz.php`
  matched. Sourcing from git would have silently changed prod behaviour.
- **`disableNameSuffixHash: true` is load-bearing.** Kustomize otherwise appends
  a content hash (`moodle-config-abc123`), and the Deployment and CronJobs
  reference the bare name — they would mount a ConfigMap that does not exist.

nitk's copy differs deliberately in two ways beyond the tenant values: `dbpass`
comes from `getenv('MOODLE_DATABASE_PASSWORD')` rather than a literal, so its
password never enters git; and its Redis prefixes are `nitk_*`, so a future
consolidation onto one Redis stays safe. Because the CronJobs mount that same
`config.php`, they inject `MOODLE_DATABASE_PASSWORD` too — without it, cron would
bootstrap with an empty password.

jain still uses a literal password. Switching it to `getenv()` is a one-line
change bundled with the credential rotation, not smuggled into this refactor:
the Secret's `database-password` was verified to match the literal exactly, so
the switch is safe whenever rotation happens.

`my.cnf` is kept for fidelity but appears to be **unused** — nothing in the
Deployment mounts it, and it configures the abandoned `moodle-db` MySQL
Deployment. Worth deleting once confirmed, but not during a refactor whose
value rests on changing nothing.

### jain-qa — built, but deliberately not diff-clean

Two things surfaced while building it.

**There is no staging cluster.** `.gitlab-ci.yml` pointed `STAGING_CLUSTER` at
`moodel-cluster` and `STAGING_NAMESPACE` at `jo-moodle`. Neither exists —
`gcloud container clusters list` returns only `usdc-prod-cluster-1`, and there is
no `jo-moodle` namespace on it. QA actually runs in `jain-moodle-qa` on the same
cluster as production, so `deploy:staging` could not have been working: it fails
at `get-credentials`. Both variables are now corrected.

**QA has drifted structurally from prod**, so unlike jain-prod this overlay
cannot be a no-op. Adopting it would:

| | effect |
|---|---|
| `moodle-hpa` | **created** (QA has none; patched to min 1 / max 3, not prod's 4) |
| `moodle-cron`, `moodle-adhoc-tasks` | **created** — QA runs *no Moodle cron at all* today |
| `moodle-config` | six tuning keys converge onto base |
| `moodle`, `redis` Deployments | resource requests added; QA currently sets none |
| `jain-qa-service-account` | annotations reconciled |

The config convergence is safe in the direction that matters: QA's live tuning is
*larger* than production's — `innodb_buffer_pool_size=50G` vs 48G,
`max_connections=10000` vs 1200, `maxmemory 6gb` vs 5gb — on a **one-replica**
environment. That reads as copy-paste drift, not deliberate sizing.

Adding cron is the real win. Without it QA cannot exercise bulk email, bulk user
upload, or any scheduled-task plugin.

Two QA-specific things the overlay deliberately preserves rather than
normalising:

- **Spot nodes.** Both QA workloads run on the `spot` pool. Base reflects
  production — its Redis pins `ondemand` and its moodle Deployment sets no
  affinity — so without `patch-nodepool.yaml` adoption would silently move QA
  onto on-demand nodes and cost more.
- **The NFS claim name.** QA's bound claim is `moodle-nfs-pvc-jain-qa`, not
  `moodle-nfs-pvc`. `patch-storage.yaml` repoints the volume; renaming a bound
  PVC would mean deleting and recreating it.

QA's `config.php` is authored from production's rather than copied from QA's
live one, which had fallen behind (missing `cache_redis_prefix` and
`lock_factory`). It sets `$CFG->noemailever = true` so QA can never mail real
learners, turns developer debugging on, and reads `dbpass` from the environment.

`deploy:staging` still uses `kubectl set image`. It auto-deploys on every push to
`develop`, so pointing it at this overlay would apply the convergence above
unattended — review `kubectl diff -k deploy/overlays/jain-qa` first, then swap in
the overlay-apply from `deploy:production`.

### Redis

One Redis per tenant namespace, in base, resolving as `redis-service`. Never
share it between tenants: sessions live in db 0 and MUC cache in db 1, both keyed
per site, so a shared instance would cross-contaminate logins and caches.

Also sourced from live rather than git — `Deployment-files/prod/redis.yml` claims
`4000m` CPU and `6Gi` memory; live runs `250m` / `1Gi`.

jain's overlay carries one patch, `patch-redis.yaml`, holding the
`kubectl.kubernetes.io/restartedAt` annotation from a manual rollout restart in
January. That annotation is object history rather than configuration, but it is
part of the pod template, so dropping it would count as a spec change and roll
Redis — **which drops every session and logs all users out**. It is reproduced to
keep adoption a genuine no-op, and lives in the overlay so it cannot leak into a
new tenant.

One inherited wart, reproduced deliberately rather than fixed: `redis.conf` sets
`maxmemory 5gb` while the container requests `1Gi` with no limit, so Redis can
grow well past its request on node headroom. Changing it restarts Redis, so it
wants its own window rather than being folded into this refactor.

The committed secret should be treated as compromised and rotated on its own
schedule, independently of this refactor.

## Before nitk-prod can be applied

The overlay renders, but is not deployable. Open items:

1. **DNS.** NITK repoints `learn.nitk.edu.in` to `A 34.95.112.39`. Longest lead
   time and blocks the certificate — start it first. See the section above.
2. **TLS certificate + map entry.** Commands in the section above. Must follow
   DNS, because issuance is load-balancer authorized.
3. **PXC cluster.** `helm install mysql-db percona/pxc-db -n nitk-prod -f
   deploy/nitk-pxc-values.yaml`, then create the `nitkdb` schema and `nitkuser`
   grants. Decided: nitk gets its **own** cluster, not a share of jain's.
4. **NFS export.** `/moodle_nfs/nitk_data_prod` must exist on `10.185.32.234`
   before apply, or pods hang on mount.
5. **Secret.** Create `moodle-secrets` in `nitk-prod` out of band, with keys
   `database-password` and `admin-password`. Do not commit it.
6. **ConfigMap + Redis.** nitk needs its own `moodle_config.php` (distinct Redis
   prefixes) and a Redis Deployment in-namespace. Both land with the ConfigMap
   split described below.
7. **Moodle install.** Fresh install + `admin/cli/upgrade.php`; the image ships
   code, not a database.
8. **Enable the CI matrix entry.** Uncomment the `nitk-prod` block in
   `deploy/ci/gitlab-ci.deploy.yml`.

Order matters: 1 → 2, and 3/4/5/6 before any apply.

## Adding a new tenant

Ordered by dependency. Most of these were learned the hard way during the NITK
bring-up; the notes are the parts that are not obvious from the manifests.

**1. Namespace**

```sh
kubectl create namespace <tenant>
```

**2. moodledata directory**

The NFS server is a GCP Filestore instance (`moodle-nfs`, fileshare `moodle_nfs`,
`10.185.32.234`). Every tenant is a *directory inside it*, named
`<tenant>_data_<env>` — this is **not** new infrastructure.

Mount the fileshare root in a throwaway pod and create it as `www-data` (33:33),
mode 2755 with the setgid bit, matching the existing directories. Never point two
sites at one directory: they collide on `filedir`, sessions, `muc` and `trashdir`
and corrupt each other.

**3. Secret**

```sh
kubectl -n <tenant> create secret generic moodle-secrets \
  --from-literal=database-password="$(openssl rand -hex 20)" \
  --from-literal=admin-password="$(openssl rand -hex 20)" \
  --from-literal=database-root-password="$(openssl rand -hex 20)"
```

Generate **distinct** credentials per tenant — never copy another tenant's. Hex
avoids the shell and connection-string escaping problems that characters like `@`
cause. Do not commit it; see the Secrets row in the table above.

**4. Database**

- Add the namespace to `watchNamespace` in
  `charts/mysql-operator/helmfile.yaml` of the `kai/infra/kube-deployments`
  repo — **not** via `helm upgrade` or `kubectl edit`, both of which get
  reverted. See `pxc-operator-values-patch.md`. **Skipping this fails silently:**
  the CR is created and simply never reconciled.
- Create the S3 credentials Secret referenced by the tenant's pxc values.
- `helm install mysql-db percona/pxc-db -n <tenant> --version 1.20.0 -f <values>`
- Create the schema and user; the password must match `database-password`.
- Verify **pods appear**, not merely that the CR exists.

**5. Overlay**

Copy `overlays/nitk-prod/` as the template — it is the newest and has no
historical baggage. Per tenant: `kustomization.yaml`, `namespace.yaml`,
`config/moodle_config.php`, and patches for env, scale, HPA and cron env.

In `moodle_config.php`, give the tenant **distinct**
`$CFG->session_redis_prefix`, `$CFG->cache_redis_prefix` and the MUC store
prefix. Each tenant has its own in-namespace Redis so they cannot collide today,
but distinct prefixes keep a future consolidation safe. Read `dbpass` from
`getenv('MOODLE_DATABASE_PASSWORD')`, and make sure the CronJob env patch injects
that variable too — the CronJobs mount the same `config.php`, and without it cron
bootstraps with an empty password.

`$CFG->wwwroot`, `MOODLE_SITE_URL` and both `MOODLE_DOCKER_WWWROOT` values must
agree. Cron builds absolute URLs for email from the last of those.

**6. DNS**

An **A record to `34.95.112.39`**, not a CNAME — the Gateway publishes a static
IP, and there is no hostname to point at.

If the tenant's own domain is not yet available, use a hostname under a zone we
control (`*.thekaios.com`) and switch later. **Do that switch early:** the cost
scales with how much content references the URL, and right after install there is
almost none.

Switching means three things, not one:

```sh
# 1. change wwwroot, MOODLE_SITE_URL and both MOODLE_DOCKER_WWWROOT together
# 2. rewrite URLs stored in content
php admin/tool/replace/cli/replace.php --non-interactive \
  --search='https://old.example' --replace='https://new.example'
php admin/cli/purge_caches.php
```

The tool is `admin/tool/replace/cli/replace.php`. There is **no**
`admin/cli/replace_all_text.php` in Moodle 5.0.

It also will **not** touch `config` or `config_plugins` —
`db_should_replace()` in `lib/adminlib.php` skips them deliberately, since
settings are cached and validated. So step 3 is to fix any settings holding the
old host through the supported API:

```php
set_config('noreplyaddress', 'noreply@new.example');
```

On the nitk switch that was `noreplyaddress` and `chat_serverhost`. Leave
`siteidentifier` alone even though it contains the old hostname — it is an opaque
install-time identifier, not a URL, and rewriting it invalidates caches and
duplicate-site detection.

**7. TLS**

Certificates are **load-balancer authorized**, so DNS must resolve to the gateway
*before* issuance — the reverse order just fails. Create the certificate and a
`thekaios-cert-map` entry. Both are `goog-terraform-provisioned`; prefer the
Terraform repo over `gcloud` if that is the source of truth.

**8. Routing**

Add an HTTPRoute to the `gateway-api` Helm release in namespace `default` — not
to this repo. All routes live there. `prod-gateway` needs no change; its listener
already accepts routes from every namespace. List both the temporary and final
hostnames from the start so the final one begins working the moment DNS moves.

**9. Moodle**

Fresh install, then the config seed from `tools/export-tenant-config.php`, then
`admin/cli/upgrade.php`. **Do not clone another tenant's database** — see the
seeding section above for why.

**10. CI**

Add the tenant to the `deploy:production` matrix in `.gitlab-ci.yml`. One entry:
`TENANT`, `TENANT_CLUSTER`, `TENANT_NAMESPACE`, `TENANT_URL`.

### Things that fail silently

Worth re-reading before declaring a tenant done. Each of these looks like success:

| symptom | cause |
|---|---|
| PXC CR exists, no pods, no events | namespace missing from operator `watchNamespace` |
| Deployment mounts a ConfigMap that does not exist | `disableNameSuffixHash` omitted — kustomize appended a content hash |
| cron fails with an empty DB password | `MOODLE_DATABASE_PASSWORD` missing from the CronJob env |
| every pod `ImagePullBackOff` after a rename | overlay pinned to a tag that was never published under the new name |
| pods roll unexpectedly on apply | `env` reordered — kustomize floats patched list entries to the front |
| CI job green, work not done | `|| true` on a step whose failure matters |

## Conventions

**Never branch per tenant.** A `nitk` branch means permanent merge tax and drift,
which defeats the point. Tenant differences are expressed as: separate database,
admin settings in that database, feature flags, and — where a tenant genuinely
needs different markup — a child theme with `$THEME->parents = ['baz']`.

**One image for every tenant.** The existing `sharda-moodle-prod` tenant runs
`sharda-moodle-prod:75393d02`, built from a forked copy of this codebase. That is
the pattern this structure exists to replace: a fix pushed here does not reach
sharda, and nobody finds out until it matters.

**No `commonLabels` in base.** Kustomize folds them into
`Deployment.spec.selector.matchLabels`, which is immutable on an existing
Deployment — the apply is rejected, and recovering means deleting and recreating
the Deployment. There is a comment to this effect in `base/kustomization.yaml`;
leave it there.

**Overlays own the full `env` list.** Kustomize's strategic merge floats patched
list entries to the front, so a base list plus a partial patch silently reorders
`env` — a spec change that rolls every pod. Base declares no `env` at all.

**The image tag is the deployment unit.** `deploy:production` in `.gitlab-ci.yml`
stamps the freshly built tag into the overlay with `kustomize edit set image`,
then applies. It no longer uses `kubectl set image`, which mutated the cluster
behind the manifests' back and is how `Deployment-files/` drifted into being
unapplyable.

## CI

`deploy:production` is a `parallel: matrix` over tenants. Adding one is a single
matrix entry; nitk's is present but commented out until its blockers clear. Each
tenant is its own manual job, so rollouts can be staged and one tenant's failure
never blocks another.

Per tenant it: installs kustomize (`kubectl kustomize` only renders — there is no
`edit` subcommand), stamps the tag, prints `kubectl diff`, applies, annotates,
rolls out **with rollback on failure**, runs `admin/cli/upgrade.php`, then does a
`rollout restart` to converge themerev.

Two of those are new. **The database upgrade has never run in CI** — it was a
manual step, and with more than one tenant someone will eventually forget one; a
plugin version bump without its upgrade leaves a broken site. And the themerev
convergence is a `rollout restart`, not sequential `purge_caches`, because those
race — each bumps `themerev=time()`, leaving pods serving different CSS.

`verify:production` was **deleted**. It was hardcoded to a single namespace so it
could not verify a second tenant, and running it after a deploy job that had
already rolled itself back would report success against the rolled-back version.
Its checks now live inside the deploy job, per tenant.

Two caveats worth knowing:

- **The upgrade runs against a live site.** No maintenance mode is enabled, which
  matches what the manual process did. If a future upgrade is schema-heavy,
  bracket it with `admin/cli/maintenance.php --enable|--disable`.
- **Rollback does not cover CronJobs.** `kubectl rollout undo` restores the
  Deployment only; CronJob images stay on the failed tag until the next
  successful deploy.

### Renaming the image — phase one done, phase two pending

`PROD_IMAGE` is now `moodle-lms`, renamed from `jain-moodle`. The image is shared
by every tenant — jain-prod and nitk-prod run the same build off the same `main` —
so a tenant-specific name was actively misleading.

**This has to be done in two phases, because `moodle-lms` does not exist in the
registry yet** (`gcloud artifacts docker images list .../moodle-lms` returns 0
items). Flipping the overlays' `newName` before anything has pushed under the new
name would point every pod at an image that isn't there — `ImagePullBackOff`
across the whole Deployment.

**Phase one (done).** CI builds `moodle-lms:<sha>` and *additionally* tags it
`jain-moodle:<sha>`. The overlays still say `jain-moodle`, so they keep resolving,
and `kubectl diff` against the running site stays clean. The `deploy:production`
job stamps `moodle-lms:<sha>` at deploy time, so the first deploy after this moves
production onto the new name.

**Phase two (after the first successful deploy on `moodle-lms`).**

1. Set `images.newName` to `.../moodle-lms` in all three overlays.
2. Delete `LEGACY_PROD_IMAGE` from the CI variables and the transitional
   `docker tags add` step in `build:production`.

Do not do phase two first. The legacy tag is what makes phase one reversible.

`STAGING_IMAGE` is deliberately left as `qa-jain-moodle`. Unifying it would put
staging and production builds in one repository, where their `:latest` tags would
overwrite each other — staging builds from `develop`, production from `main`. That
needs the `:latest` convention resolved first, and it buys little: the multi-tenant
requirement is that *production tenants* share an image, which `moodle-lms` now
delivers.

### Routing ownership — decided: Helm, not kustomize

**Routing stays in the `gateway-api` Helm release. This repo defines no
HTTPRoutes.**

The deciding evidence: *every* HTTPRoute in the cluster belongs to that one
release — eleven of them, across eight namespaces, for services well beyond
Moodle (monitoring, superset, vpl-prod, vpl-qa, zammad, sharda, humversity,
staloysius, nitk-online-portal). Not one exception. That is a deliberate,
uniformly applied convention, not an accident of history.

Three reasons to keep it:

1. **It spans far more than Moodle.** This repo owning a slice of cluster-wide
   routing would make one service's deploy pipeline a co-owner of shared
   networking.
2. **TLS is coupled to it.** Publishing a hostname needs both an HTTPRoute *and*
   an entry in the `thekaios-cert-map` certificate map, which is Terraform-managed
   and also central. Splitting those two halves across repos means every new
   hostname is a two-team, two-tool dance. Keeping them together makes it one
   request.
3. **Migrating out is genuinely risky.** Helm records ownership in
   `meta.helm.sh/release-name` annotations. Applying those objects from kustomize
   would not transfer ownership — Helm would still believe it owns them, and a
   later `helm upgrade` could revert or delete a live production route.

The counter-argument is real and worth naming: the Gateway API is *designed* for
split ownership — infrastructure owns the Gateway, application teams own their
Routes — and `prod-gateway` sets `allowedRoutes.namespaces.from: All`, which is
exactly the invitation for app namespaces to attach their own. If this cluster
had no established convention, app-owned routes would be the better default. It
loses here to eleven-out-of-eleven consistency and to the TLS coupling.

**Consequence:** `overlays/nitk-prod/httproute.yaml` was removed. nitk's route is
requested in the `gateway-api` chart alongside its certificate. The spec to add:

```yaml
apiVersion: gateway.networking.k8s.io/v1
kind: HTTPRoute
metadata:
  name: nitk-prod-route
  namespace: nitk-prod
spec:
  hostnames:
    - learn.nitk.edu.in
  parentRefs:
    - group: gateway.networking.k8s.io
      kind: Gateway
      name: prod-gateway
      namespace: default
  rules:
    - backendRefs:
        - group: ""
          kind: Service
          name: moodle-service
          namespace: nitk-prod
          port: 80
          weight: 1
      matches:
        - path:
            type: PathPrefix
            value: /
```

No change to `prod-gateway` itself is needed — its listener already accepts
routes from all namespaces.

### A caveat about "diff-clean"

`kubectl diff -k` being empty is a **verification technique for adoption**, not a
permanent invariant.

CI stamps the image tag into the overlay at deploy time and does not commit it
back, so after any deploy the committed tag lags what is running and `diff` will
show an image difference. That is expected, not drift in the dangerous sense —
every other field still matches.

When using `diff` to check something is safe, read *which fields* differ rather
than just the exit code. An image-tag-only difference is normal between deploys;
a difference in resources, volumes, or env is not.
