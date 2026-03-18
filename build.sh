#!/bin/bash
# Build script: packages the html/ directory into html.tar.gz
# Run from the rokuphp-arm64/ directory.

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "Building html.tar.gz..."
tar -czf html.tar.gz html/
echo "Done: $SCRIPT_DIR/html.tar.gz"
ls -lh html.tar.gz
