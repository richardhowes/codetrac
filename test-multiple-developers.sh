#!/bin/bash

# DevTrack Multi-Developer Test Script
# This script simulates multiple developers sending sessions to test the multi-developer functionality

# Configuration
API_URL="${1:-http://localhost:8000/api/webhook/session}"
USE_TOKENS="${2:-false}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}DevTrack Multi-Developer Test Script${NC}"
echo "API URL: $API_URL"
echo "----------------------------------------"

# Function to generate a realistic transcript
generate_transcript() {
    local task=$1
    cat <<EOF
{"version":"1.0","sessionId":"$(uuidgen || echo "session-$RANDOM")","events":[
{"type":"start","timestamp":"$(date -u +"%Y-%m-%dT%H:%M:%SZ")"},
{"type":"message","role":"user","content":"$task"},
{"type":"message","role":"assistant","content":"I'll help you with that task."},
{"type":"toolUse","tool":"read","params":{"path":"src/main.js"}},
{"type":"toolResult","content":"// File contents here"},
{"type":"toolUse","tool":"write","params":{"path":"src/components/NewComponent.vue","content":"<template>...</template>"}},
{"type":"message","role":"assistant","content":"Task completed successfully."},
{"type":"metrics","tokens":{"input":500,"output":300},"cost":0.024},
{"type":"end","timestamp":"$(date -u +"%Y-%m-%dT%H:%M:%SZ")"}
]}
EOF
}

# Function to simulate a developer session
send_session() {
    local dev_name=$1
    local hostname=$2
    local project=$3
    local token=${4:-"test-token-123"}
    local task=$5
    
    echo -e "${YELLOW}Sending session: $dev_name@$hostname${NC}"
    echo "  Project: $project"
    echo "  Task: $task"
    
    # Generate transcript
    transcript_file=$(mktemp)
    generate_transcript "$task" | gzip > "${transcript_file}.gz"
    
    # Send request
    response=$(curl -s -w "\n%{http_code}" -X POST "$API_URL" \
        -H "Authorization: Bearer $token" \
        -F "session_id=test-$(date +%s)-$RANDOM" \
        -F "transcript=@${transcript_file}.gz;type=application/gzip" \
        -F "stop_hook_active=true" \
        -F "user=$dev_name" \
        -F "hostname=$hostname" \
        -F "machine_id=test-machine-$hostname" \
        -F "os=$(uname -s)" \
        -F "os_version=$(uname -r)" \
        -F "architecture=$(uname -m)" \
        -F "ip_address=192.168.1.$((RANDOM % 254 + 1))" \
        -F "claude_version=1.0.0-test" \
        -F "timestamp=$(date -u +"%Y-%m-%dT%H:%M:%SZ")" \
        -F "working_directory=$project")
    
    http_code=$(echo "$response" | tail -n1)
    response_body=$(echo "$response" | head -n-1)
    
    if [ "$http_code" -eq 201 ] || [ "$http_code" -eq 200 ]; then
        echo -e "  ${GREEN}✓ Session sent successfully${NC}"
    else
        echo -e "  ${RED}✗ Failed (HTTP $http_code)${NC}"
        echo "  Response: $response_body"
    fi
    
    # Cleanup
    rm -f "$transcript_file" "${transcript_file}.gz"
    echo ""
}

# Test Scenario 1: Multiple developers on same project
echo -e "${GREEN}=== Scenario 1: Multiple Developers, Same Project ===${NC}"
send_session "alice" "ubuntu-dev" "/home/projects/website" "test-token-123" "Add user authentication"
sleep 1
send_session "bob" "macbook-pro" "/home/projects/website" "test-token-123" "Fix navigation menu"
sleep 1
send_session "charlie" "windows-wsl" "/home/projects/website" "test-token-123" "Update database schema"
echo ""

# Test Scenario 2: Same developer, multiple projects
echo -e "${GREEN}=== Scenario 2: One Developer, Multiple Projects ===${NC}"
send_session "alice" "ubuntu-dev" "/home/projects/api-service" "test-token-123" "Create REST endpoints"
sleep 1
send_session "alice" "ubuntu-dev" "/home/projects/mobile-app" "test-token-123" "Implement push notifications"
sleep 1
send_session "alice" "ubuntu-dev" "/home/projects/documentation" "test-token-123" "Write API documentation"
echo ""

# Test Scenario 3: Different developers, different projects
echo -e "${GREEN}=== Scenario 3: Different Developers, Different Projects ===${NC}"
send_session "david" "fedora-workstation" "/var/www/blog" "test-token-123" "Add comment system"
sleep 1
send_session "eve" "debian-server" "/opt/services/analytics" "test-token-123" "Create dashboard charts"
sleep 1
send_session "frank" "arch-desktop" "/home/frank/ml-project" "test-token-123" "Train neural network model"
echo ""

# Summary
echo -e "${GREEN}=== Test Complete ===${NC}"
echo "Total sessions sent: 9"
echo "Developers simulated: 6 (alice, bob, charlie, david, eve, frank)"
echo "Projects touched: 7"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Open the dashboard: http://localhost:8000"
echo "2. Check the developer dropdown - should show all 6 developers"
echo "3. Check the project dropdown - should show all 7 projects"
echo "4. Try filtering by different developers and projects"
echo "5. Verify charts and statistics update correctly"
echo ""

# Optional: Show current stats
if command -v php &> /dev/null; then
    echo -e "${GREEN}=== Current Database Stats ===${NC}"
    php artisan tinker --execute="
        echo 'Total Developers: ' . App\Models\Developer::count() . PHP_EOL;
        echo 'Total Projects: ' . App\Models\Project::count() . PHP_EOL;
        echo 'Total Sessions: ' . App\Models\ClaudeSession::count() . PHP_EOL;
    " 2>/dev/null || echo "  (Run 'php artisan tinker' to check database stats)"
fi