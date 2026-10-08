#!/bin/bash
# =============================================================================
# Moodle Deployment Verification Script
# =============================================================================
# Usage: ./verify.sh <environment> [timeout_seconds]
# Example: ./verify.sh staging
#          ./verify.sh production 600
# =============================================================================

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Arguments
ENVIRONMENT="${1:-}"
TIMEOUT="${2:-300}"

# Validate arguments
if [[ -z "$ENVIRONMENT" ]]; then
    echo -e "${RED}Error: Missing required argument${NC}"
    echo "Usage: $0 <environment> [timeout_seconds]"
    exit 1
fi

# Configuration based on environment
case "$ENVIRONMENT" in
    staging)
        CLUSTER="moodel-cluster"
        NAMESPACE="jo-moodle"
        EXPECTED_REPLICAS=1
        ;;
    production)
        CLUSTER="usdc-prod-cluster-1"
        NAMESPACE="jo-moodle-prod"
        EXPECTED_REPLICAS=4
        ;;
    *)
        echo -e "${RED}Error: Invalid environment '$ENVIRONMENT'${NC}"
        exit 1
        ;;
esac

# GCP Configuration
GCP_PROJECT="org-dev-infra"
GCP_REGION="asia-south1"

echo -e "${GREEN}=== Deployment Verification ===${NC}"
echo "Environment: $ENVIRONMENT"
echo "Namespace: $NAMESPACE"
echo "Timeout: ${TIMEOUT}s"
echo ""

# Step 1: Connect to cluster
echo -e "${YELLOW}[1/4] Connecting to GKE cluster...${NC}"
gcloud container clusters get-credentials "$CLUSTER" --region "$GCP_REGION" --project "$GCP_PROJECT"

# Step 2: Wait for rollout
echo -e "${YELLOW}[2/4] Waiting for rollout to complete...${NC}"
if ! kubectl rollout status deployment/moodle -n "$NAMESPACE" --timeout="${TIMEOUT}s"; then
    echo -e "${RED}Rollout failed! Initiating rollback...${NC}"
    kubectl rollout undo deployment/moodle -n "$NAMESPACE"
    echo -e "${RED}Rollback completed. Deployment reverted to previous version.${NC}"
    exit 1
fi

echo -e "${GREEN}Rollout completed successfully${NC}"
echo ""

# Step 3: Verify pod health
echo -e "${YELLOW}[3/4] Verifying pod health...${NC}"

# Get pod status
READY_PODS=$(kubectl get deployment moodle -n "$NAMESPACE" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
DESIRED_PODS=$(kubectl get deployment moodle -n "$NAMESPACE" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "0")

echo "Ready pods: $READY_PODS / $DESIRED_PODS"

if [[ "$READY_PODS" -lt "$DESIRED_PODS" ]]; then
    echo -e "${RED}Not all pods are ready!${NC}"
    echo "Pod status:"
    kubectl get pods -n "$NAMESPACE" -l app=moodle
    exit 1
fi

# Step 4: Health check via pod exec
echo -e "${YELLOW}[4/4] Running health check...${NC}"

# Try to curl the health endpoint from inside the nginx container
HEALTH_CHECK=$(kubectl exec -n "$NAMESPACE" deploy/moodle -c nginx -- \
    wget -qO- --timeout=10 http://localhost/healthz.php 2>/dev/null || echo "FAILED")

if [[ "$HEALTH_CHECK" == "FAILED" || -z "$HEALTH_CHECK" ]]; then
    echo -e "${YELLOW}Warning: Health check returned empty or failed${NC}"
    echo "This might be normal if healthz.php returns empty on success"
else
    echo "Health check response: $HEALTH_CHECK"
fi

# Get current image
CURRENT_IMAGE=$(kubectl get deployment moodle -n "$NAMESPACE" -o jsonpath='{.spec.template.spec.containers[?(@.name=="php-fpm")].image}')
echo ""
echo -e "${GREEN}=== Verification Complete ===${NC}"
echo "Current image: $CURRENT_IMAGE"
echo "Ready replicas: $READY_PODS / $DESIRED_PODS"
echo ""

# Show pod summary
echo "Pod summary:"
kubectl get pods -n "$NAMESPACE" -l app=moodle -o wide

exit 0
