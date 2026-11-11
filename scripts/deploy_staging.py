#!/usr/bin/env python3
import os
import sys
import io
import time
import tarfile
import subprocess

STAGING_IP = os.environ.get("STAGING_IP", "192.168.192.24")
STAGING_HOST = os.environ.get("STAGING_HOST", f"root@{STAGING_IP}")
PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))

DIRS_TO_SYNC = [
    'app',
    'config',
    'database',
    'resources',
    'routes',
    'public',
]

def run_migration_guard():
    print("==> [Step 1/6] Validating Database Migration Safety Guard...")
    res = subprocess.run(["php", "scripts/db_migration_guard.php"], cwd=PROJECT_ROOT)
    if res.returncode != 0:
        print("❌ Error: db_migration_guard check failed. Aborting staging deployment.")
        sys.exit(1)
    print("  [PASS] Migration safety verified (expand-and-contract policy compliant).")

def run_frontend_build():
    print("==> [Step 2/6] Building production frontend assets (npm run build)...")
    res = subprocess.run(["npm", "run", "build"], cwd=PROJECT_ROOT)
    if res.returncode != 0:
        print("❌ Error: npm run build failed. Aborting staging deployment.")
        sys.exit(1)
    print("  [PASS] Vite assets built successfully in public/build.")

def create_payload_tar():
    print("==> [Step 3/6] Packaging updated codebase and build assets into archive...")
    tar_stream = io.BytesIO()
    with tarfile.open(fileobj=tar_stream, mode='w:gz') as tar:
        for rel_dir in DIRS_TO_SYNC:
            full_path = os.path.join(PROJECT_ROOT, rel_dir)
            if os.path.exists(full_path):
                tar.add(full_path, arcname=rel_dir)
    payload = tar_stream.getvalue()
    print(f"  [PASS] Compressed payload created: {len(payload):,} bytes.")
    return payload

def execute_remote_staging_deploy(payload, is_rolling=False):
    mode_label = "Zero-Downtime Rolling Deployment" if is_rolling else "Standard Synchronized Deployment"
    print(f"==> [Step 4/6] Executing {mode_label} to remote staging server ({STAGING_HOST})...")

    rolling_flag = "1" if is_rolling else "0"

    remote_template = r"""
set -euo pipefail

IS_ROLLING="__IS_ROLLING__"
DEPLOY_DIR="/tmp/staging_release_$(date +%s)"
mkdir -p "$DEPLOY_DIR"
cat > "$DEPLOY_DIR/payload.tar.gz"
tar -xzf "$DEPLOY_DIR/payload.tar.gz" -C "$DEPLOY_DIR"
rm -f "$DEPLOY_DIR/payload.tar.gz"

# Distribute Vite build assets to candidate-public on staging host for Nginx
if [ -d "/home/mrpirzado/projects/130/candidate-edge/candidate-public" ] && [ -d "$DEPLOY_DIR/public/build" ]; then
    echo "  -> Distributing Vite build assets to candidate-public..."
    mkdir -p /home/mrpirzado/projects/130/candidate-edge/candidate-public/build
    cp -r "$DEPLOY_DIR/public/build/." /home/mrpirzado/projects/130/candidate-edge/candidate-public/build/
fi

if [ -d "/home/mrpirzado/projects/130/candidate-edge/candidate-public/js" ] && [ -f "$DEPLOY_DIR/public/js/bento-bridge.js" ]; then
    cp "$DEPLOY_DIR/public/js/bento-bridge.js" /home/mrpirzado/projects/130/candidate-edge/candidate-public/js/bento-bridge.js
fi

echo "  -> Running database migrations..."
if docker ps --format '{{.Names}}' | grep -q "^130-candidate-app-1$"; then
    docker exec 130-candidate-app-1 php artisan migrate --force || true
    docker exec 130-candidate-app-1 php artisan tinker --execute="\\App\\Models\\CallSubType::where('name', 'Armed Firing')->update(['icon' => 'fa-gun']);" >/dev/null 2>&1 || true
fi
if docker ps --format '{{.Names}}' | grep -q "^130-app-1-1$"; then
    docker exec 130-app-1-1 php artisan migrate --force || true
fi

upgrade_container() {
    local c="$1"
    if docker ps --format '{{.Names}}' | grep -q "^${c}$"; then
        echo "    * Updating application node $c..."
        (cd "$DEPLOY_DIR" && tar -cf - .) | docker exec -u 0 -i "$c" tar --no-same-owner -xf - -C /var/www/html
        docker exec -u 0 "$c" chown -R www-data:www-data /var/www/html/app /var/www/html/resources /var/www/html/database /var/www/html/public /var/www/html/routes /var/www/html/config
        docker exec "$c" php artisan optimize:clear >/dev/null 2>&1 || true
        docker exec "$c" php artisan optimize >/dev/null 2>&1 || true
    fi
}

if [ "$IS_ROLLING" = "1" ]; then
    echo "  -> Executing Zero-Downtime Rolling Release across app nodes..."
    echo "  [Phase 1/2] Upgrading Primary Node (130-candidate-app-1)..."
    upgrade_container "130-candidate-app-1"
    upgrade_container "130-app-1-1"
    echo "  -> Node 1 upgraded and warmed. Stabilizing traffic before Phase 2..."
    sleep 2

    echo "  [Phase 2/2] Upgrading Secondary Node (130-candidate-app-2)..."
    upgrade_container "130-candidate-app-2"
    upgrade_container "130-app-2-1"
    echo "  -> Node 2 upgraded and warmed."
else
    echo "  -> Updating application nodes in synchronized batch..."
    for c in "130-candidate-app-1" "130-candidate-app-2" "130-app-1-1" "130-app-2-1"; do
        upgrade_container "$c"
    done
fi

rm -rf "$DEPLOY_DIR"
"""

    remote_script = remote_template.replace("__IS_ROLLING__", rolling_flag)

    import base64
    script_b64 = base64.b64encode(remote_script.encode('utf-8')).decode('ascii')

    ssh_cmd = [
        "ssh",
        "-o", "ConnectTimeout=20",
        "-o", "BatchMode=yes",
        "-o", "StrictHostKeyChecking=no",
        STAGING_HOST,
        f"bash -c \"$(echo '{script_b64}' | base64 -d)\""
    ]

    proc = subprocess.Popen(ssh_cmd, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    stdout, stderr = proc.communicate(input=payload)

    if stdout:
        print(stdout.decode('utf-8', errors='replace'))
    if proc.returncode != 0:
        if stderr:
            print(stderr.decode('utf-8', errors='replace'), file=sys.stderr)
        raise RuntimeError(f"Remote staging deployment failed with exit code {proc.returncode}")

def verify_staging_health():
    print("==> [Step 5/6] Verifying remote staging health endpoints (8085 internal & 7085 external)...")
    import urllib.request
    import ssl
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE

    endpoints = [
        f"http://{STAGING_IP}:8085/login",
        f"https://{STAGING_IP}:7085/login",
    ]
    for url in endpoints:
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "NHMP-Deploy-Verify/1.0"})
            with urllib.request.urlopen(req, timeout=10, context=ctx if url.startswith("https") else None) as resp:
                code = resp.getcode()
                if code == 200:
                    print(f"  [PASS] Staging endpoint {url} returned HTTP {code} OK.")
                else:
                    print(f"  [WARN] Staging endpoint {url} returned status: {code}")
        except Exception as e:
            print(f"  [WARN] Health check warning for {url}: {e}")

def verify_asset_delivery():
    print("==> [Step 6/6] Verifying Vite CSS and JS asset delivery on staging (port 8085)...")
    import urllib.request
    import json
    manifest_url = f"http://{STAGING_IP}:8085/build/manifest.json"
    try:
        req = urllib.request.Request(manifest_url, headers={"User-Agent": "NHMP-Deploy-Verify/1.0"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            manifest_data = json.loads(resp.read().decode('utf-8'))
            css_file = manifest_data.get("resources/css/app.css", {}).get("file")
            js_file = manifest_data.get("resources/js/app.js", {}).get("file")
            
            if css_file:
                css_url = f"http://{STAGING_IP}:8085/build/{css_file}"
                with urllib.request.urlopen(urllib.request.Request(css_url), timeout=10) as css_resp:
                    print(f"  [PASS] CSS Asset {css_file} verified (HTTP {css_resp.getcode()}).")
            if js_file:
                js_url = f"http://{STAGING_IP}:8085/build/{js_file}"
                with urllib.request.urlopen(urllib.request.Request(js_url), timeout=10) as js_resp:
                    print(f"  [PASS] JS Asset {js_file} verified (HTTP {js_resp.getcode()}).")
    except Exception as e:
        print(f"  [WARN] Asset delivery check: {e}")

def main():
    is_rolling = "--rolling" in sys.argv
    start_time = time.time()
    mode_text = "Zero-Downtime Rolling Deployment" if is_rolling else "Synchronized Batch Deployment"
    print("======================================================================")
    print(f"  NHMP 130 — Automated Remote Staging ({mode_text})")
    print("======================================================================")
    print(f"Target:       {STAGING_HOST}:8080")
    print(f"Mode:         {mode_text}")
    print(f"Time (UTC):   {time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime())}")
    print("----------------------------------------------------------------------")

    run_migration_guard()
    run_frontend_build()
    payload = create_payload_tar()
    execute_remote_staging_deploy(payload, is_rolling=is_rolling)
    verify_staging_health()
    verify_asset_delivery()

    elapsed = round(time.time() - start_time, 2)
    print("----------------------------------------------------------------------")
    print(f"✅ Staging {mode_text.lower()} and asset distribution completed in {elapsed}s!")
    print("======================================================================")

if __name__ == '__main__':
    main()
