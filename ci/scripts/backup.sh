#!/bin/bash
# =============================================================================
# Pre-Deployment Database Backup Script
# =============================================================================
# Usage: ./backup.sh
# This script triggers a database backup before production deployments
# =============================================================================

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
GCP_PROJECT="org-dev-infra"
GCP_REGION="asia-south1"
CLUSTER="usdc-prod-cluster-1"
NAMESPACE="jo-moodle-prod"
BACKUP_JOB_NAME="moodle-db-backup"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)

echo -e "${GREEN}=== Pre-Deployment Database Backup ===${NC}"
echo "Timestamp: $TIMESTAMP"
echo ""

# Step 1: Connect to production cluster
echo -e "${YELLOW}[1/4] Connecting to production cluster...${NC}"
gcloud container clusters get-credentials "$CLUSTER" --region "$GCP_REGION" --project "$GCP_PROJECT"

# Step 2: Create backup job
echo -e "${YELLOW}[2/4] Creating backup job...${NC}"
JOB_NAME="${BACKUP_JOB_NAME}-predeploy-${TIMESTAMP}"

kubectl create job "$JOB_NAME" \
    --from=cronjob/"$BACKUP_JOB_NAME" \
    -n "$NAMESPACE"

echo "Backup job created: $JOB_NAME"

# Step 3: Wait for backup to complete
echo -e "${YELLOW}[3/4] Waiting for backup to complete...${NC}"
TIMEOUT=300
ELAPSED=0
INTERVAL=10

while [[ $ELAPSED -lt $TIMEOUT ]]; do
    STATUS=$(kubectl get job "$JOB_NAME" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Complete")].status}' 2>/dev/null || echo "")
    FAILED=$(kubectl get job "$JOB_NAME" -n "$NAMESPACE" -o jsonpath='{.status.conditions[?(@.type=="Failed")].status}' 2>/dev/null || echo "")
    
    if [[ "$STATUS" == "True" ]]; then
        echo -e "${GREEN}Backup completed successfully!${NC}"
        break
    fi
    
    if [[ "$FAILED" == "True" ]]; then
        echo -e "${RED}Backup job failed!${NC}"
        kubectl logs job/"$JOB_NAME" -n "$NAMESPACE" --tail=50
        exit 1
    fi
    
    echo "Backup in progress... (${ELAPSED}s / ${TIMEOUT}s)"
    sleep $INTERVAL
    ELAPSED=$((ELAPSED + INTERVAL))
done

if [[ $ELAPSED -ge $TIMEOUT ]]; then
    echo -e "${RED}Backup timed out after ${TIMEOUT}s${NC}"
    exit 1
fi

# Step 4: Verify backup
echo -e "${YELLOW}[4/4] Verifying backup...${NC}"
kubectl logs job/"$JOB_NAME" -n "$NAMESPACE" --tail=20

echo ""
echo -e "${GREEN}=== Backup Complete ===${NC}"
echo "Job: $JOB_NAME"
echo "Backup is ready. Proceeding with deployment is safe."
