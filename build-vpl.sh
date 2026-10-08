#!/bin/bash
# Script to build VPL JavaScript files with correct Node version

export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

# Switch to Node 20.11.0
nvm use 20.11.0

# Verify Node version
echo "Using Node version: $(node --version)"

# Build AMD files
cd "$(dirname "$0")"
grunt amd --force

echo "Build complete!"
