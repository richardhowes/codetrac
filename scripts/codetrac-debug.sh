#!/bin/bash

# Debug script to see what Claude Code provides to hooks
echo "=== CodeTrac Hook Debug ===" >&2
echo "Arguments passed: $@" >&2
echo "Number of arguments: $#" >&2
echo "" >&2

echo "=== JSON Input from stdin ===" >&2
# Read JSON input and display it
json_input=$(cat)
echo "$json_input" >&2

# Parse the JSON if jq is available
if command -v jq >/dev/null 2>&1; then
    echo "" >&2
    echo "=== Parsed JSON Fields ===" >&2
    echo "session_id: $(echo "$json_input" | jq -r '.session_id')" >&2
    echo "transcript_path: $(echo "$json_input" | jq -r '.transcript_path')" >&2
    echo "stop_hook_active: $(echo "$json_input" | jq -r '.stop_hook_active')" >&2
    
    # Check if the transcript file exists
    transcript_path=$(echo "$json_input" | jq -r '.transcript_path')
    transcript_path="${transcript_path/#\~/$HOME}"
    echo "" >&2
    echo "=== Transcript File Check ===" >&2
    echo "Expanded path: $transcript_path" >&2
    if [ -f "$transcript_path" ]; then
        echo "Transcript file exists: YES" >&2
        echo "File size: $(ls -lh "$transcript_path" | awk '{print $5}')" >&2
    else
        echo "Transcript file exists: NO" >&2
        # Try to find it using the fallback method
        transcript_dir="${transcript_path%/*}"
        session_id=$(echo "$json_input" | jq -r '.session_id')
        fallback_path=$(find "$transcript_dir" -type f -exec grep -l "$session_id" {} + 2>/dev/null | head -n1)
        if [ -n "$fallback_path" ]; then
            echo "Found transcript via fallback: $fallback_path" >&2
        else
            echo "Could not find transcript via fallback either" >&2
        fi
    fi
fi

echo "" >&2
echo "=== Environment Variables ===" >&2
env | sort >&2

echo "" >&2
echo "=== Working Directory ===" >&2
pwd >&2

echo "" >&2
echo "=== Script completed ===" >&2
exit 0