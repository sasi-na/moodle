#!/bin/bash
# =============================================================================
# Slack Notification Script
# =============================================================================
# Usage: ./notify.sh <status> <environment> <image_tag> <pipeline_url>
# Example: ./notify.sh success staging abc1234 https://gitlab.com/...
# =============================================================================

set -euo pipefail

# Arguments
STATUS="${1:-}"
ENVIRONMENT="${2:-}"
IMAGE_TAG="${3:-}"
PIPELINE_URL="${4:-}"

# Validate arguments
if [[ -z "$STATUS" || -z "$ENVIRONMENT" ]]; then
    echo "Error: Missing required arguments"
    echo "Usage: $0 <status> <environment> <image_tag> <pipeline_url>"
    exit 1
fi

# Check for Slack webhook URL
if [[ -z "${SLACK_WEBHOOK_URL:-}" ]]; then
    echo "Warning: SLACK_WEBHOOK_URL not set, skipping notification"
    exit 0
fi

# Configuration
case "$ENVIRONMENT" in
    staging)
        ENV_EMOJI=":test_tube:"
        ENV_COLOR="#36a64f"
        CLUSTER="moodel-cluster"
        ;;
    production)
        ENV_EMOJI=":rocket:"
        ENV_COLOR="#ff9900"
        CLUSTER="usdc-prod-cluster-1"
        ;;
    *)
        ENV_EMOJI=":question:"
        ENV_COLOR="#808080"
        CLUSTER="unknown"
        ;;
esac

# Status configuration
case "$STATUS" in
    success)
        STATUS_EMOJI=":white_check_mark:"
        STATUS_TEXT="Deployment Successful"
        STATUS_COLOR="#36a64f"
        ;;
    failure)
        STATUS_EMOJI=":x:"
        STATUS_TEXT="Deployment Failed"
        STATUS_COLOR="#ff0000"
        ;;
    rollback)
        STATUS_EMOJI=":warning:"
        STATUS_TEXT="Deployment Rolled Back"
        STATUS_COLOR="#ff9900"
        ;;
    started)
        STATUS_EMOJI=":hourglass_flowing_sand:"
        STATUS_TEXT="Deployment Started"
        STATUS_COLOR="#3498db"
        ;;
    *)
        STATUS_EMOJI=":grey_question:"
        STATUS_TEXT="Deployment Status: $STATUS"
        STATUS_COLOR="#808080"
        ;;
esac

# Build Slack message payload
TIMESTAMP=$(date -u +"%Y-%m-%d %H:%M:%S UTC")

PAYLOAD=$(cat <<EOF
{
    "attachments": [
        {
            "color": "$STATUS_COLOR",
            "blocks": [
                {
                    "type": "header",
                    "text": {
                        "type": "plain_text",
                        "text": "$STATUS_EMOJI $STATUS_TEXT",
                        "emoji": true
                    }
                },
                {
                    "type": "section",
                    "fields": [
                        {
                            "type": "mrkdwn",
                            "text": "*Environment:*\n$ENV_EMOJI ${ENVIRONMENT^}"
                        },
                        {
                            "type": "mrkdwn",
                            "text": "*Cluster:*\n$CLUSTER"
                        },
                        {
                            "type": "mrkdwn",
                            "text": "*Image Tag:*\n\`$IMAGE_TAG\`"
                        },
                        {
                            "type": "mrkdwn",
                            "text": "*Timestamp:*\n$TIMESTAMP"
                        }
                    ]
                },
                {
                    "type": "actions",
                    "elements": [
                        {
                            "type": "button",
                            "text": {
                                "type": "plain_text",
                                "text": "View Pipeline",
                                "emoji": true
                            },
                            "url": "$PIPELINE_URL",
                            "style": "primary"
                        }
                    ]
                }
            ]
        }
    ]
}
EOF
)

# Send notification
echo "Sending Slack notification..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d "$PAYLOAD" \
    "$SLACK_WEBHOOK_URL")

if [[ "$HTTP_CODE" == "200" ]]; then
    echo "Slack notification sent successfully"
else
    echo "Warning: Slack notification failed with HTTP code $HTTP_CODE"
fi
