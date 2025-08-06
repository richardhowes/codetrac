#!/bin/bash

# Test script for DevTrack webhook
# Simulates sending Claude Code session data

# Create a sample transcript file
cat > /tmp/test-transcript.txt << 'EOF'
Human: Help me create a new feature for user authentication