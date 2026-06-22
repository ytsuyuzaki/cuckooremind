#!/bin/bash

set -euo pipefail

if [ "$#" -ne 3 ]; then
    echo "Usage: smoke-test-upgrade.sh <new-archive> <new-version> <old-archive-url>" >&2
    exit 2
fi

NEW_ARCHIVE=$(realpath "$1")
NEW_VERSION=$2
OLD_URL=$3
INSTALL_DIR=$(mktemp -d)
INCOMING_DIR=$(mktemp -d)
OLD_ARCHIVE=$(mktemp --suffix=.zip)
trap 'rm -rf "$INSTALL_DIR" "$INCOMING_DIR" "$OLD_ARCHIVE"' EXIT

curl -fsSL --retry 3 -o "$OLD_ARCHIVE" "$OLD_URL"
unzip -q "$OLD_ARCHIVE" -d "$INSTALL_DIR"
unzip -q "$NEW_ARCHIVE" -d "$INCOMING_DIR"

ENV_HASH=$(sha256sum "$INSTALL_DIR/.env" | cut -d' ' -f1)
php -r '$db = new PDO("sqlite:".$argv[1]); $db->exec("CREATE TABLE update_probe (value TEXT NOT NULL)"); $db->exec("INSERT INTO update_probe VALUES (\"preserved\")");' "$INSTALL_DIR/storage/db.sqlite"
mkdir -p "$INSTALL_DIR/storage/app/public"
printf preserved > "$INSTALL_DIR/storage/app/public/update-probe.txt"

rsync -a --exclude='.env' --exclude='storage/' "$INCOMING_DIR/" "$INSTALL_DIR/"
(cd "$INSTALL_DIR" && php artisan migrate --force)

test "$ENV_HASH" = "$(sha256sum "$INSTALL_DIR/.env" | cut -d' ' -f1)"
test "$(cat "$INSTALL_DIR/storage/app/public/update-probe.txt")" = preserved
test "$(cat "$INSTALL_DIR/.version")" = "$NEW_VERSION"
php -r '$db = new PDO("sqlite:".$argv[1]); if ($db->query("SELECT value FROM update_probe")->fetchColumn() !== "preserved") exit(1);' "$INSTALL_DIR/storage/db.sqlite"
php "$INSTALL_DIR/artisan" route:list --path=system/updates >/dev/null
