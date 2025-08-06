#!/bin/bash

# Claude Code Hook Script for Local DevTrack
# This script sends session transcripts to your local DevTrack dashboard
#
# Installation:
# 1. Copy this entire script
# 2. Go to Claude Code settings by running /hooks
# 3. Select "Stop" hook event  
# 4. Add a new hook with this script as the command
#
# The script will run after each Claude Code session ends

# Configuration - POINTING TO LOCAL DEVTRACK
API_TOKEN="local-dev-token"
API_URL="http://localhost:8001/api/webhook/session"

# Read JSON input from stdin
json_input=$(cat)

# Collect user and machine metadata
user_info=$(whoami)
hostname=$(hostname)
machine_id=$([ -f /etc/machine-id ] && cat /etc/machine-id || echo "unknown")
os_type=$(uname -s)
os_version=$(uname -r)
architecture=$(uname -m)
ip_address=$(ifconfig 2>/dev/null | grep -E "inet " | grep -v '127.0.0.1' | head -n 1 | awk '{print $2}' || echo "unknown")
claude_version=$(claude --version 2>/dev/null | head -1 || echo "unknown")
working_dir=$(pwd)
iso_timestamp=$(date -u "+%Y-%m-%dT%H:%M:%SZ")

# Log the raw JSON metadata with system info to a file (optional - comment out if not needed)
{
    echo "=== Session End: $iso_timestamp ==="
    echo "User: $user_info@$hostname"
    echo "OS: $os_type $os_version ($architecture)"
    echo "Location: $working_dir"
    echo "$json_input"
    echo "---"
} >> ~/devtrack_hook_metadata.log

# Extract data from JSON
session_id=$(echo "$json_input" | jq -r '.session_id')
transcript_path=$(echo "$json_input" | jq -r '.transcript_path')
stop_hook_active=$(echo "$json_input" | jq -r '.stop_hook_active')

# Expand tilde in path
transcript_path="${transcript_path/#\~/$HOME}"

# Check if transcript file exists
if [ ! -f "$transcript_path" ]; then
    # The current claude code hook system has a bug where the transcript path
    # points to a file that doesn't exist if a session is reused / cleared.
    # In this case, search inside the base path of the transcript path for a file
    # that contains the session_id
    transcript_path="${transcript_path%/*}"
    transcript_path=$(find "$transcript_path" -type f -exec grep -l "$session_id" {} + | head -n 1)

    if [ ! -f "$transcript_path" ]; then
        echo "Error: Transcript file not found at $transcript_path" >&2
        exit 1
    fi
fi

# Create a temporary file for the compressed transcript
temp_file=$(mktemp)
trap "rm -f $temp_file" EXIT

# Compress the transcript file with gzip
gzip -c "$transcript_path" > "$temp_file"

# Send to API as multipart form data with gzip compression including metadata
response=$(curl -s -w "\n%{http_code}" -X POST \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json" \
    -H "Content-Encoding: gzip" \
    -F "transcript=@$temp_file;type=application/gzip" \
    -F "session_id=$session_id" \
    -F "stop_hook_active=$stop_hook_active" \
    -F "user=$user_info" \
    -F "hostname=$hostname" \
    -F "machine_id=$machine_id" \
    -F "os=$os_type" \
    -F "os_version=$os_version" \
    -F "architecture=$architecture" \
    -F "ip_address=$ip_address" \
    -F "claude_version=$claude_version" \
    -F "timestamp=$iso_timestamp" \
    -F "working_directory=$working_dir" \
    "$API_URL")

# Extract HTTP status code
# Check if response has content
if [ -z "$response" ]; then
    http_code=""
    response_body=""
else
    # Count lines in response
    line_count=$(echo "$response" | wc -l)
    if [ "$line_count" -eq 1 ]; then
        # Only status code, no body
        http_code="$response"
        response_body=""
    else
        # Extract status code and body
        http_code=$(echo "$response" | tail -n1)
        response_body=$(echo "$response" | sed '$d')
    fi
fi

# Check response
if [ "$http_code" -eq 200 ] || [ "$http_code" -eq 201 ]; then
    echo "Session data sent successfully to Local DevTrack"
    exit 0
else
    echo "Failed to send session data. HTTP status: $http_code" >&2
    echo "Response: $response_body" >&2
    exit 1
fi