#!/bin/sh
# Build plugin zip for upload to jyavani.com
# ZIP structure: plugin.json at root (WAJIB — bukan dalam folder)
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"
rm -f /tmp/jyavani-builder.zip
zip -r /tmp/jyavani-builder.zip plugin.json plugin.php admin/ icon.svg
echo "Created: /tmp/jyavani-builder.zip ($(ls -lh /tmp/jyavani-builder.zip | awk '{print $5}'))"
