# CodeTrac Testing Guide

This guide explains how to test CodeTrac with multiple developers using VMs, Docker, or multiple local environments.

## Testing Strategies

### Option 1: Virtual Machines (Recommended)

VMs provide the most realistic testing environment with complete isolation.

#### Setup Steps

1. **Create VMs** (using VirtualBox, VMware, or cloud providers):
   - Ubuntu 22.04 VM
   - macOS VM (if available)
   - Windows with WSL2

2. **Install Claude Code on each VM**:
   ```bash
   # On each VM
   npm install -g @anthropic/claude-code
   ```

3. **Configure unique developer profiles**:
   Each VM will naturally have different:
   - Username
   - Hostname
   - Machine ID
   - IP address

4. **Setup webhook hooks** on each VM:
   ```bash
   # Copy the hook script
   cp scripts/codetrac-hook.sh ~/claude-hook.sh
   
   # Edit with VM-specific settings
   nano ~/claude-hook.sh
   # Update API_URL and API_TOKEN
   
   # Configure Claude Code
   claude hooks add stop ~/claude-hook.sh
   ```

5. **Generate API tokens** for each developer:
   ```bash
   # On the CodeTrac server
   php artisan api:generate-token --developer=john@ubuntu-vm
   php artisan api:generate-token --developer=jane@macos-vm
   ```

### Option 2: Docker Containers

Lighter weight than VMs but still provides isolation.

#### Docker Compose Setup

Create `docker-compose.test.yml`:

```yaml
version: '3.8'

services:
  dev1:
    image: ubuntu:22.04
    container_name: codetrac-dev1
    hostname: dev1-machine
    environment:
      - USER=developer1
    volumes:
      - ./test-projects/dev1:/workspace
    command: tail -f /dev/null

  dev2:
    image: ubuntu:22.04
    container_name: codetrac-dev2
    hostname: dev2-machine
    environment:
      - USER=developer2
    volumes:
      - ./test-projects/dev2:/workspace
    command: tail -f /dev/null

  dev3:
    image: ubuntu:22.04
    container_name: codetrac-dev3
    hostname: dev3-machine
    environment:
      - USER=developer3
    volumes:
      - ./test-projects/dev3:/workspace
    command: tail -f /dev/null
```

Run containers:

```bash
docker-compose -f docker-compose.test.yml up -d
```

Install Claude Code in each container:

```bash
docker exec -it codetrac-dev1 bash
# Inside container
apt-get update && apt-get install -y curl nodejs npm
npm install -g @anthropic/claude-code
```

### Option 3: Multiple Local Profiles

Test with different Claude Code profiles on the same machine.

```bash
# Create different config directories
mkdir -p ~/.claude-profiles/dev1
mkdir -p ~/.claude-profiles/dev2
mkdir -p ~/.claude-profiles/dev3

# Run Claude Code with different profiles
CLAUDE_CONFIG_DIR=~/.claude-profiles/dev1 claude
CLAUDE_CONFIG_DIR=~/.claude-profiles/dev2 claude
CLAUDE_CONFIG_DIR=~/.claude-profiles/dev3 claude
```

## Test Scenarios

### Scenario 1: Multiple Developers, Same Project

Test how the system handles multiple developers working on the same project path.

1. Create a shared project directory
2. Have each developer run Claude Code sessions in that directory
3. Verify:
   - Sessions are attributed to correct developers
   - Project statistics aggregate correctly
   - Filtering works properly

### Scenario 2: One Developer, Multiple Projects

Test a single developer working across different projects.

1. Use the same developer profile
2. Run sessions in different project directories
3. Verify:
   - Projects are tracked separately
   - Developer totals sum correctly
   - Project switching is reflected in dashboard

### Scenario 3: Concurrent Sessions

Test multiple developers working simultaneously.

1. Start Claude Code sessions on multiple VMs/containers at the same time
2. Execute various commands and edits
3. Verify:
   - No data conflicts
   - Real-time updates work
   - Performance remains acceptable

### Scenario 4: Token Authentication

Test the API token system.

1. Generate tokens with different expiration times
2. Test expired tokens
3. Test invalid tokens
4. Test token deactivation
5. Verify proper error messages

## Automated Testing Script

Create `test-multi-developer.sh`:

```bash
#!/bin/bash

# Configuration
API_URL="http://localhost:8000/api/webhook/session"
CODETRAC_DIR="/path/to/codetrac"

# Function to simulate a developer session
simulate_session() {
    local dev_name=$1
    local hostname=$2
    local project=$3
    local token=$4
    
    echo "Simulating session for $dev_name@$hostname on project $project"
    
    # Create a fake transcript
    transcript=$(cat <<EOF
Session started at $(date -u +"%Y-%m-%dT%H:%M:%SZ")
User: Help me create a test file
Assistant: I'll create a test file for you.
[Tool use: Write file test.txt]
File created successfully.
Session ended at $(date -u +"%Y-%m-%dT%H:%M:%SZ")
EOF
    )
    
    # Create temp file
    temp_file=$(mktemp)
    echo "$transcript" > "$temp_file"
    
    # Send to API
    curl -X POST "$API_URL" \
        -H "Authorization: Bearer $token" \
        -F "session_id=test-$(date +%s)-$RANDOM" \
        -F "transcript=@$temp_file" \
        -F "stop_hook_active=true" \
        -F "user=$dev_name" \
        -F "hostname=$hostname" \
        -F "machine_id=machine-$hostname" \
        -F "os=Linux" \
        -F "os_version=5.15.0" \
        -F "architecture=x86_64" \
        -F "ip_address=192.168.1.$((RANDOM % 254 + 1))" \
        -F "claude_version=1.0.0" \
        -F "timestamp=$(date -u +"%Y-%m-%dT%H:%M:%SZ")" \
        -F "working_directory=$project"
    
    rm "$temp_file"
    echo "Session sent for $dev_name"
    echo "---"
}

# Simulate multiple developers
simulate_session "alice" "ubuntu-vm" "/home/alice/project1" "token-alice-123"
sleep 2
simulate_session "bob" "macos-vm" "/Users/bob/project1" "token-bob-456"
sleep 2
simulate_session "charlie" "windows-vm" "/mnt/c/projects/project2" "token-charlie-789"
sleep 2
simulate_session "alice" "ubuntu-vm" "/home/alice/project2" "token-alice-123"

echo "All test sessions sent!"
echo "Check the dashboard at http://localhost:8000"
```

## Verification Checklist

After running tests, verify:

### Dashboard
- [ ] Developer dropdown shows all test developers
- [ ] Project dropdown shows all test projects
- [ ] Filtering by developer shows only their sessions
- [ ] Filtering by project shows sessions from all developers
- [ ] Time period filters work correctly
- [ ] Charts update based on filters

### Data Integrity
- [ ] Session counts match what was sent
- [ ] Token usage is tracked per developer
- [ ] Costs are calculated correctly
- [ ] Files and commands are parsed properly

### API Authentication
- [ ] Valid tokens allow access
- [ ] Invalid tokens are rejected
- [ ] Expired tokens fail appropriately
- [ ] Token usage is logged

### Performance
- [ ] Dashboard loads quickly with multiple developers
- [ ] Concurrent webhook requests are handled
- [ ] Database queries are optimized

## Load Testing

For production readiness, test with larger volumes:

```bash
# Generate 100 sessions across 10 developers
for i in {1..100}; do
    dev_num=$((i % 10))
    simulate_session "developer$dev_num" "machine$dev_num" "/project$((i % 5))" "token-$dev_num"
    sleep 0.5
done
```

## Monitoring During Tests

Watch logs during testing:

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Database queries (enable query log first)
php artisan tinker
>>> DB::enableQueryLog();
>>> // Run tests
>>> DB::getQueryLog();

# Monitor webhook processing
php artisan tinker
>>> App\Models\ClaudeSession::latest()->take(10)->get();
```

## Troubleshooting Common Issues

### Sessions Not Appearing
- Check API token is valid
- Verify webhook URL is correct
- Check Laravel logs for errors
- Ensure database migrations are run

### Developer Not Created
- Verify metadata is being sent correctly
- Check unique constraints (username, hostname, machine_id)
- Review Developer model's findOrCreateByMetadata method

### Filtering Not Working
- Clear browser cache
- Check Vue DevTools for state
- Verify query parameters in network tab
- Check AnalyticsService filtering logic

### Performance Issues
- Add database indexes if needed
- Enable query caching
- Optimize transcript parsing
- Consider pagination for large datasets