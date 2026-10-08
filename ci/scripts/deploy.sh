#!/bin/bash
# =============================================================================
# Moodle Deployment Script
# =============================================================================
# Usage: ./deploy.sh <environment> <image_tag>
# Example: ./deploy.sh staging abc1234
#          ./deploy.sh production abc1234
# =============================================================================

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Arguments
ENVIRONMENT="${1:-}"
IMAGE_TAG="${2:-}"

# Validate arguments
if [[ -z "$ENVIRONMENT" || -z "$IMAGE_TAG" ]]; then
    echo -e "${RED}Error: Missing required arguments${NC}"
    echo "Usage: $0 <environment> <image_tag>"
    echo "Example: $0 staging abc1234"
    exit 1
fi

# Configuration based on environment
case "$ENVIRONMENT" in
    staging)
        CLUSTER="moodel-cluster"
        NAMESPACE="jo-moodle"
        IMAGE_NAME="qa-jain-moodle"
        UPDATE_CRONJOBS="false"
        ;;
    production)
        CLUSTER="usdc-prod-cluster-1"
        NAMESPACE="jo-moodle-prod"
        IMAGE_NAME="jain-moodle"
        UPDATE_CRONJOBS="true"
        ;;
    *)
        echo -e "${RED}Error: Invalid environment '$ENVIRONMENT'${NC}"
        echo "Valid environments: staging, production"
        exit 1
        ;;
esac

# GCP Configuration
GCP_PROJECT="org-dev-infra"
GCP_REGION="asia-south1"
ARTIFACT_REGISTRY="asia-south1-docker.pkg.dev/${GCP_PROJECT}/services"
FULL_IMAGE="${ARTIFACT_REGISTRY}/${IMAGE_NAME}:${IMAGE_TAG}"

echo -e "${GREEN}=== Moodle Deployment ===${NC}"
echo "Environment: $ENVIRONMENT"
echo "Cluster: $CLUSTER"
echo "Namespace: $NAMESPACE"
echo "Image: $FULL_IMAGE"
echo ""

# Step 1: Connect to GKE cluster
echo -e "${YELLOW}[1/5] Connecting to GKE cluster...${NC}"
gcloud container clusters get-credentials "$CLUSTER" --region "$GCP_REGION" --project "$GCP_PROJECT"

# Step 2: Verify cluster connection
echo -e "${YELLOW}[2/5] Verifying cluster connection...${NC}"
kubectl cluster-info
echo ""

# Step 3: Update Moodle deployment
echo -e "${YELLOW}[3/5] Updating Moodle deployment...${NC}"

# Update init container image
kubectl set image deployment/moodle \
    setup-logs-and-code="$FULL_IMAGE" \
    -n "$NAMESPACE"

# Update main container image
kubectl set image deployment/moodle \
    php-fpm="$FULL_IMAGE" \
    -n "$NAMESPACE"

echo "Deployment updated with image: $FULL_IMAGE"

# Step 4: Update CronJobs (production only)
if [[ "$UPDATE_CRONJOBS" == "true" ]]; then
    echo -e "${YELLOW}[4/5] Updating CronJobs...${NC}"
    
    # Update moodle-cron
    kubectl set image cronjob/moodle-cron \
        moodle-cron="$FULL_IMAGE" \
        -n "$NAMESPACE" 2>/dev/null || echo "Note: moodle-cron cronjob not found or update failed"
    
    # Update moodle-adhoc-tasks
    kubectl set image cronjob/moodle-adhoc-tasks \
        moodle-adhoc-tasks="$FULL_IMAGE" \
        -n "$NAMESPACE" 2>/dev/null || echo "Note: moodle-adhoc-tasks cronjob not found or update failed"
    
    echo "CronJobs updated"
else
    echo -e "${YELLOW}[4/5] Skipping CronJob update (staging environment)${NC}"
fi

# Step 5: Record deployment annotation
echo -e "${YELLOW}[5/5] Recording deployment metadata...${NC}"
kubectl annotate deployment/moodle \
    kubernetes.io/change-cause="Deployed image ${IMAGE_TAG} at $(date -u +"%Y-%m-%dT%H:%M:%SZ")" \
    -n "$NAMESPACE" \
    --overwrite

echo ""
echo -e "${GREEN}=== Deployment initiated successfully ===${NC}"
echo "Run 'kubectl rollout status deployment/moodle -n $NAMESPACE' to monitor progress"
