#!/bin/sh
# Build plugin zip for upload to jyavani.com
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
NAME="jyavani-builder"
TMP="/tmp/$NAME"
rm -rf "$TMP"
mkdir -p "$TMP/admin/assets"
cp "$DIR/plugin.json" "$TMP/"
cp "$DIR/plugin.php" "$TMP/"
cp "$DIR/admin/index.php" "$TMP/admin/"
cp "$DIR/admin/assets/"*.css "$TMP/admin/assets/"
cp "$DIR/admin/assets/"*.js "$TMP/admin/assets/"
cd /tmp
rm -f "$NAME.zip"
zip -r "$NAME.zip" "$NAME/"
echo "Created: /tmp/$NAME.zip ($(du -h /tmp/$NAME.zip | cut -f1))"
