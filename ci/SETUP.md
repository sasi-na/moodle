# JO Moodle LMS - CI/CD Pipeline Guide

This guide covers the GitLab CI/CD pipeline for JO Moodle LMS, including deployment workflows, base image management, and troubleshooting.

## Overview

| Component | Description |
|-----------|-------------|
| **Application** | JO Moodle LMS |
| **Platform** | GitLab CI/CD |
| **Authentication** | GCP Workload Identity Federation |
| **Docker Build** | Google Cloud Build |
| **Container Registry** | Google Artifact Registry |
| **Kubernetes** | Google Kubernetes Engine (GKE) |
| **Notifications** | Slack |

### Environments

| Environment | Branch | Cluster | Namespace | URL | Deployment |
|-------------|--------|---------|-----------|-----|------------|
| Staging | `develop` | `moodel-cluster` | `jo-moodle` | https://dev.lms.thekaios.com | Automatic |
| Production | `main` | `usdc-prod-cluster-1` | `jo-moodle-prod` | https://learn.onlinejain.com | Manual |

---

## Current Architecture

### Authentication: Workload Identity Federation

The pipeline uses **GCP Workload Identity Federation** - no service account keys required.

- **Workload Identity Pool**: `projects/794839270073/locations/global/workloadIdentityPools/gitlab`
- **Provider**: `gitlab-gitlab`
- **Service Account**: `gitlab-ci-cd@org-dev-infra.iam.gserviceaccount.com`

GitLab CI jobs authenticate using OIDC tokens, which are exchanged for GCP credentials automatically.

### Docker Build: Cloud Build + Pre-built Base Image

Docker images are built using **Google Cloud Build** with a **pre-built base image** for faster builds.

| Image | Purpose | Build Time |
|-------|---------|------------|
| `moodle-base:php8.2` | Base image with PHP extensions | ~5 min (built occasionally) |
| `qa-jain-moodle:<sha>` | Staging app image | ~3 min |
| `jain-moodle:<sha>` | Production app image | ~3 min |

### Container Registry

All images are stored in Google Artifact Registry:
```
asia-south1-docker.pkg.dev/org-dev-infra/services/
```

---

## Prerequisites

- Access to GitLab repository (`coderepos.thekaios.com/kai/lms/moodle/moodle-lms-5.0`)
- GCP project: `org-dev-infra`
- Slack workspace with webhook configured

---

## GitLab CI/CD Variables

Only one variable is required:

| Variable | Type | Description | Protected | Masked |
|----------|------|-------------|-----------|--------|
| `SLACK_WEBHOOK_URL` | Variable | Slack incoming webhook URL | No | Yes |

### Setting Up SLACK_WEBHOOK_URL

1. Go to **Settings** > **CI/CD** > **Variables**
2. Click **Add Variable**
3. Key: `SLACK_WEBHOOK_URL`
4. Value: Your Slack webhook URL
5. Check **Mask variable**
6. Uncheck **Protect variable** (so it works on all branches)
7. Click **Add Variable**

> **Note**: Workload Identity Federation is pre-configured. No GCP service account keys are needed.

---

## Pipeline Flow

### Staging Pipeline (develop branch) - Automatic

```
Push to develop
       │
       ▼
┌──────────────┐
│   lint:php   │  PHP syntax check
└──────┬───────┘
       ▼
┌──────────────┐
│build:staging │  Build Docker image via Cloud Build
└──────┬───────┘
       ▼
┌──────────────┐
│deploy:staging│  Update K8s deployment
└──────┬───────┘
       ▼
┌──────────────┐
│verify:staging│  Wait for rollout, auto-rollback on failure
└──────┬───────┘
       ▼
┌──────────────┐
│notify:staging│  Slack notification (success/failure)
└──────────────┘
```

### Production Pipeline (main branch) - Manual Gates

```
Push to main
       │
       ▼
┌──────────────┐
│   lint:php   │  PHP syntax check
└──────┬───────┘
       ▼
┌──────────────┐
│build:production│  Build Docker image via Cloud Build
└──────┬───────┘
       ▼
┌──────────────────────┐
│notify:build-complete │  Slack: "Awaiting manual backup"
└──────┬───────────────┘
       ▼
┌──────────────┐
│backup:production│  [MANUAL] Trigger database backup
└──────┬───────┘
       ▼
┌───────────────────────┐
│notify:backup-complete │  Slack: "Awaiting manual deploy"
└──────┬────────────────┘
       ▼
┌──────────────┐
│deploy:production│  [MANUAL] Update K8s deployment
└──────┬───────┘
       ▼
┌──────────────┐
│verify:production│  Wait for rollout, auto-rollback on failure
└──────┬───────┘
       ▼
┌──────────────┐
│notify:production│  Slack notification (success/failure)
└──────────────┘
```

---

## Base Image Management

### What is the Base Image?

The pipeline uses a pre-built base image (`moodle-base:php8.2`) containing:

- PHP 8.2-FPM runtime
- All required PHP extensions:
  - redis, gd, mysqli, pdo_mysql
  - intl, xsl, soap, opcache
  - zip, xml, mbstring
- System dependencies (git, curl, etc.)
- PHP configuration for Moodle
- Pre-created directories with correct permissions

**This reduces build time from ~12 minutes to ~3 minutes.**

### Base Image Location

```
asia-south1-docker.pkg.dev/org-dev-infra/services/moodle-base:php8.2
```

### When to Rebuild

Rebuild the base image when:

| Scenario | Example |
|----------|---------|
| PHP version upgrade | Upgrading from PHP 8.2 to 8.3 |
| New PHP extension needed | Adding `imagick` or `ldap` |
| System dependency change | Adding `ffmpeg` for video processing |
| Security patches | Critical PHP or OS security updates |

### How to Rebuild

#### Option 1: GitLab Pipeline (Recommended)

1. Go to: https://coderepos.thekaios.com/kai/lms/moodle/moodle-lms-5.0/-/pipelines
2. Find a pipeline on `main` or `develop` branch
3. Locate the `build:base-image` job (shows as "manual")
4. Click the **Play** button to trigger
5. Wait ~5 minutes for build to complete

#### Option 2: Command Line

```bash
# Navigate to project root
cd /path/to/moodle-lms-5.0

# Create temp directory with Dockerfile
mkdir -p /tmp/moodle-base
cp Dockerfile.base /tmp/moodle-base/Dockerfile

# Build and push using Cloud Build
gcloud builds submit /tmp/moodle-base \
  --tag "asia-south1-docker.pkg.dev/org-dev-infra/services/moodle-base:php8.2" \
  --timeout=1800s \
  --project=org-dev-infra

# Clean up
rm -rf /tmp/moodle-base
```

### Dockerfile Reference

The base image is defined in `Dockerfile.base` at the project root:

```dockerfile
FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git curl libpng-dev ... \
    && pecl install redis \
    && docker-php-ext-install gd mysqli intl ...

# Configure PHP for Moodle
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/...

# Create directories
RUN mkdir -p /var/www/html /var/moodledata ...
```

The application `Dockerfile` then simply extends this base:

```dockerfile
FROM asia-south1-docker.pkg.dev/org-dev-infra/services/moodle-base:php8.2

COPY --chown=www-data:www-data . /var/www/html
```

---

## Slack Notifications

### Notification Types

| Notification | Trigger | Color |
|--------------|---------|-------|
| **JO Moodle LMS - Staging Deployment Successful** | Staging deploy completes | Green |
| **JO Moodle LMS - Staging Deployment Failed** | Staging deploy fails | Red |
| **JO Moodle LMS - Production Build Complete** | Prod build done, awaiting backup | Blue |
| **JO Moodle LMS - Production Backup Complete** | Backup done, awaiting deploy | Green |
| **JO Moodle LMS - Production Deployment Successful** | Prod deploy completes | Green |
| **JO Moodle LMS - Production Deployment Failed** | Prod deploy fails | Red |

### Information Included

Each notification includes:

| Field | Description |
|-------|-------------|
| Environment | Staging or Production |
| Branch | `develop` or `main` |
| Commit | Short SHA (e.g., `abc1234`) |
| Message | Commit message (first 100 chars) |
| Author | Who made the commit |
| Triggered By | Who triggered the manual job (production) |
| Deployed By | Who triggered the deploy (production) |
| Image | Docker image tag |
| Duration | Pipeline duration in seconds |
| Buttons | View Pipeline, View Staging/Production |

### Production Notification Flow

```
1. Build completes
   └── Slack: "JO Moodle LMS - Production Build Complete"
       └── "Awaiting manual backup trigger"
       └── [Trigger Backup] button

2. Backup completes (after manual trigger)
   └── Slack: "JO Moodle LMS - Production Backup Complete"
       └── "Awaiting manual deployment trigger"
       └── [Trigger Deploy] button

3. Deploy completes (after manual trigger)
   └── Slack: "JO Moodle LMS - Production Deployment Successful"
       └── [View Pipeline] [View Production] buttons
```

---

## Monitoring & Logs

### Pipeline Logs

**GitLab CI Job Logs:**
1. Go to pipeline page
2. Click on any job (e.g., `build:staging`)
3. View real-time logs

**Cloud Build Logs:**
1. Find the Cloud Build URL in the job logs (printed during build)
2. Or go to: https://console.cloud.google.com/cloud-build/builds?project=org-dev-infra

### Kubernetes Logs

**View Pod Logs (Staging):**
```bash
# Connect to staging cluster
gcloud container clusters get-credentials moodel-cluster \
  --region asia-south1 --project org-dev-infra

# View logs
kubectl logs -n jo-moodle -l app=moodle -c php-fpm --tail=100

# Stream logs
kubectl logs -n jo-moodle -l app=moodle -c php-fpm -f
```

**View Pod Logs (Production):**
```bash
# Connect to production cluster
gcloud container clusters get-credentials usdc-prod-cluster-1 \
  --region asia-south1 --project org-dev-infra

# View logs
kubectl logs -n jo-moodle-prod -l app=moodle -c php-fpm --tail=100
```

### Pod Status & Events

```bash
# Check pod status
kubectl get pods -n jo-moodle -l app=moodle

# Describe pod (shows events, errors)
kubectl describe pod -n jo-moodle -l app=moodle

# Check deployment status
kubectl rollout status deployment/moodle -n jo-moodle
```

### Google Cloud Monitoring

**Logs Explorer:**
- URL: https://console.cloud.google.com/logs/query?project=org-dev-infra
- Filter by:
  - `resource.type="k8s_container"`
  - `resource.labels.namespace_name="jo-moodle"`

**Metrics Explorer:**
- URL: https://console.cloud.google.com/monitoring/metrics-explorer?project=org-dev-infra
- Useful metrics:
  - `kubernetes.io/container/cpu/utilization`
  - `kubernetes.io/container/memory/used_bytes`
  - `kubernetes.io/container/restart_count`

### Checking Deployment History

```bash
# View rollout history
kubectl rollout history deployment/moodle -n jo-moodle

# View specific revision
kubectl rollout history deployment/moodle -n jo-moodle --revision=5
```

---

## Troubleshooting

### Pipeline Fails at Cloud Build

**Error**: `PERMISSION_DENIED` or `forbidden from accessing bucket`

**Solution**: Verify IAM permissions for `gitlab-ci-cd@org-dev-infra.iam.gserviceaccount.com`:
- `roles/cloudbuild.builds.editor`
- `roles/storage.admin`
- `roles/serviceusage.serviceUsageConsumer`

### Pipeline Fails at Deploy

**Error**: `kubectl: command not found`

**Solution**: The pipeline should install kubectl automatically. If failing:
```yaml
# Verify k8s_auth template includes:
- apt-get update && apt-get install -y kubectl google-cloud-cli-gke-gcloud-auth-plugin
```

### Build Times Out

**Error**: Cloud Build exceeds timeout

**Solution**: 
1. Check if base image exists: `gcloud artifacts docker images list asia-south1-docker.pkg.dev/org-dev-infra/services/moodle-base`
2. If missing, rebuild base image first
3. Increase timeout in `.gitlab-ci.yml` if needed

### Rollout Fails

**Error**: `deployment exceeded its progress deadline`

**Solution**:
1. Check pod events: `kubectl describe pods -n jo-moodle -l app=moodle`
2. Check pod logs: `kubectl logs -n jo-moodle -l app=moodle -c php-fpm`
3. Common issues:
   - Image pull errors (check image exists)
   - Resource limits (check memory/CPU)
   - Readiness probe failures (check health endpoints)

### Slack Notifications Not Working

**Error**: Notification job fails or no message appears

**Solution**:
1. Verify `SLACK_WEBHOOK_URL` is set in GitLab CI/CD Variables
2. Ensure variable is **not** protected (or branch is protected)
3. Test webhook manually:
   ```bash
   curl -X POST -H 'Content-type: application/json' \
     --data '{"text":"Test from CLI"}' \
     "$SLACK_WEBHOOK_URL"
   ```

### Base Image Not Found

**Error**: `manifest unknown` or `image not found`

**Solution**: Rebuild the base image:
1. Go to pipeline, trigger `build:base-image` manually
2. Or use CLI method in "Base Image Management" section

---

## Rollback

### Automatic Rollback

The `verify:staging` and `verify:production` jobs automatically rollback if:
- Rollout fails to complete within timeout (5 min staging, 10 min production)
- Pods fail to become ready

### Manual Rollback

**Staging:**
```bash
gcloud container clusters get-credentials moodel-cluster \
  --region asia-south1 --project org-dev-infra

kubectl rollout undo deployment/moodle -n jo-moodle
```

**Production:**
```bash
gcloud container clusters get-credentials usdc-prod-cluster-1 \
  --region asia-south1 --project org-dev-infra

kubectl rollout undo deployment/moodle -n jo-moodle-prod
```

### Rollback to Specific Revision

```bash
# View history
kubectl rollout history deployment/moodle -n jo-moodle-prod

# Rollback to specific revision
kubectl rollout undo deployment/moodle -n jo-moodle-prod --to-revision=3
```

---

## Security Notes

1. **Workload Identity Federation**: No long-lived service account keys - uses short-lived OIDC tokens
2. **Masked Variables**: Slack webhook URL is masked in logs
3. **Manual Approval**: Production deployments require explicit manual trigger
4. **Pre-deployment Backup**: Database backup runs before production deployment
5. **Auto-rollback**: Failed deployments are automatically rolled back

---

## Quick Reference

### URLs

| Resource | URL |
|----------|-----|
| GitLab Repository | https://coderepos.thekaios.com/kai/lms/moodle/moodle-lms-5.0 |
| Staging | https://dev.lms.thekaios.com |
| Production | https://learn.onlinejain.com |
| Cloud Build | https://console.cloud.google.com/cloud-build/builds?project=org-dev-infra |
| Artifact Registry | https://console.cloud.google.com/artifacts/docker/org-dev-infra/asia-south1/services |
| GKE Clusters | https://console.cloud.google.com/kubernetes/list?project=org-dev-infra |

### Common Commands

```bash
# Connect to staging
gcloud container clusters get-credentials moodel-cluster --region asia-south1 --project org-dev-infra

# Connect to production
gcloud container clusters get-credentials usdc-prod-cluster-1 --region asia-south1 --project org-dev-infra

# Check pods
kubectl get pods -n jo-moodle -l app=moodle

# View logs
kubectl logs -n jo-moodle -l app=moodle -c php-fpm --tail=100

# Rollback
kubectl rollout undo deployment/moodle -n jo-moodle
```

---

## Appendix: Legacy Setup (Reference)

> **Note**: This section is kept for reference only. The current pipeline uses Workload Identity Federation and does not require service account keys.

### Legacy: Service Account Key Method

If you need to set up a new project without Workload Identity Federation:

#### Create Service Account

```bash
PROJECT_ID="org-dev-infra"
SA_NAME="gitlab-ci-deployer"
SA_EMAIL="${SA_NAME}@${PROJECT_ID}.iam.gserviceaccount.com"

# Create service account
gcloud iam service-accounts create $SA_NAME \
    --display-name="GitLab CI/CD Deployer" \
    --project=$PROJECT_ID

# Grant permissions
gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:${SA_EMAIL}" \
    --role="roles/artifactregistry.writer"

gcloud projects add-iam-policy-binding $PROJECT_ID \
    --member="serviceAccount:${SA_EMAIL}" \
    --role="roles/container.developer"
```

#### Create Key File

```bash
gcloud iam service-accounts keys create gitlab-ci-key.json \
    --iam-account=$SA_EMAIL \
    --project=$PROJECT_ID
```

#### Configure GitLab Variable

1. Go to **Settings** > **CI/CD** > **Variables**
2. Add variable:
   - Key: `GCP_SERVICE_ACCOUNT_KEY`
   - Type: **File**
   - Value: Contents of `gitlab-ci-key.json`
   - Protected: Yes

#### Modify Pipeline

Replace the `gcp_auth` template with:

```yaml
.gcp_auth:
  before_script:
    - echo "$GCP_SERVICE_ACCOUNT_KEY" > /tmp/gcp-key.json
    - gcloud auth activate-service-account --key-file=/tmp/gcp-key.json
    - gcloud config set project $GCP_PROJECT
```

---

## Support

For issues with the CI/CD pipeline:

1. Check GitLab CI job logs
2. Check Cloud Build logs
3. Check Kubernetes events and pod logs
4. Review Slack notifications for error details
5. Contact DevOps team if issue persists
