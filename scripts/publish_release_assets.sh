#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

IMAGE_NAME="${1:-130-app-1:latest}"
HOST_PUBLIC_DIR="$PROJECT_ROOT/public"
TARGET_ASSETS_DIR="$HOST_PUBLIC_DIR/build/assets"

echo "=== EXPLICIT RELEASE ASSET PUBLICATION ==="
echo "Extracting release assets from image: $IMAGE_NAME..."

mkdir -p "$TARGET_ASSETS_DIR"

# 1. Create a temporary container from the newly built release image
TEMP_CID=$(docker create "$IMAGE_NAME")

TEMP_DIR="$PROJECT_ROOT/.release_asset_staging_$$"
mkdir -p "$TEMP_DIR"

cleanup() {
    docker rm -f "$TEMP_CID" >/dev/null 2>&1 || true
    rm -rf "$TEMP_DIR" >/dev/null 2>&1 || true
}
trap cleanup EXIT

# 2. Extract versioned assets into temporary folder
docker cp "$TEMP_CID:/var/www/html/public/build/assets/." "$TEMP_DIR/"

# 3. Copy non-destructively: add new hashes while strictly preserving older hashed assets
cp -rn "$TEMP_DIR"/* "$TARGET_ASSETS_DIR/" 2>/dev/null || cp -rf "$TEMP_DIR"/* "$TARGET_ASSETS_DIR/"
rm -rf "$TEMP_DIR"

# 4. Set safe read permissions for Nginx (www-data / uid 82)
find "$HOST_PUBLIC_DIR/build" -type d -exec chmod 755 {} + 2>/dev/null || true
find "$HOST_PUBLIC_DIR/build" -type f -exec chmod 644 {} + 2>/dev/null || true

echo "Asset publication complete. Verified contents in $TARGET_ASSETS_DIR:"
ls -la "$TARGET_ASSETS_DIR"
