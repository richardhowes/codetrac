#!/bin/bash

# CodeTrac Claude Code Hook Script
# This script sends Claude Code session transcripts to CodeTrac for analytics

# Configuration - Update these values for your setup
CODETRAC_URL="http://codetrac.dev/api/webhook/session"
CODETRAC_TOKEN="47lz0q2eFBf50hXwcn1x9Pl4flzTpxi5uY3N7RBV7Br9y3VcTWKEkPrUANs5"

# Read JSON input from stdin
json_input=$(cat)

# Extract data from JSON
SESSION_ID=$(echo "$json_input" | jq -r '.session_id')
TRANSCRIPT_PATH=$(echo "$json_input" | jq -r '.transcript_path')
STOP_HOOK_ACTIVE=$(echo "$json_input" | jq -r '.stop_hook_active')
HOOK_EVENT="Stop"

# Expand tilde in path
TRANSCRIPT_PATH="${TRANSCRIPT_PATH/#\~/$HOME}"

# Check if transcript file exists
if [ ! -f "$TRANSCRIPT_PATH" ]; then
    # The current claude code hook system has a bug where the transcript path
    # points to a file that doesn't exist if a session is reused / cleared.
    # In this case, search inside the base path of the transcript path for a file
    # that contains the session_id
    transcript_dir="${TRANSCRIPT_PATH%/*}"
    TRANSCRIPT_PATH=$(find "$transcript_dir" -type f -exec grep -l "$SESSION_ID" {} + 2>/dev/null | head -n1)

    if [ ! -f "$TRANSCRIPT_PATH" ]; then
        echo "Error: Transcript file not found for session $SESSION_ID" >&2
        exit 1
    fi
fi

# Debug output (comment out in production)
# echo "Session ID: $SESSION_ID" >&2
# echo "Transcript Path: $TRANSCRIPT_PATH" >&2

# Get system information
HOSTNAME=$(hostname)
USERNAME=$(whoami)
OS=$(uname -s)
OS_VERSION=$(uname -r)
ARCHITECTURE=$(uname -m)
WORKING_DIR=$(pwd)
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
MACHINE_ID=$([ -f /etc/machine-id ] && cat /etc/machine-id || echo "unknown")
IP_ADDRESS=$(ifconfig 2>/dev/null | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -1 | awk '{print $2}' || echo "unknown")
CLAUDE_VERSION=$(claude --version 2>/dev/null | head -1 || echo "unknown")

# Create a temporary file for the compressed transcript
TEMP_FILE=$(mktemp)
trap "rm -f $TEMP_FILE" EXIT

# Compress the transcript file with gzip
echo "Compressing transcript..." >&2
gzip -c "$TRANSCRIPT_PATH" > "$TEMP_FILE"

# Send to CodeTrac as multipart form data
echo "Sending session data to CodeTrac..." >&2
RESPONSE=$(curl -s -X POST "$CODETRAC_URL" \
    -H "Authorization: Bearer $CODETRAC_TOKEN" \
    -H "Content-Encoding: gzip" \
    -F "transcript=@$TEMP_FILE;type=application/gzip" \
    -F "session_id=$SESSION_ID" \
    -F "stop_hook_active=$STOP_HOOK_ACTIVE" \
    -F "user=$USERNAME" \
    -F "hostname=$HOSTNAME" \
    -F "machine_id=$MACHINE_ID" \
    -F "os=$OS" \
    -F "os_version=$OS_VERSION" \
    -F "architecture=$ARCHITECTURE" \
    -F "ip_address=$IP_ADDRESS" \
    -F "claude_version=$CLAUDE_VERSION" \
    -F "timestamp=$TIMESTAMP" \
    -F "working_directory=$WORKING_DIR" \
    -F "hook_event=$HOOK_EVENT" \
    -w "\n%{http_code}")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

# Check response
if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 201 ] || [ "$HTTP_CODE" -eq 302 ]; then
    echo "✓ Session data sent to CodeTrac successfully"
    exit 0
else
    echo "✗ Failed to send session data to CodeTrac (HTTP $HTTP_CODE)" >&2
    echo "Response: $BODY" >&2
    exit 1
fi