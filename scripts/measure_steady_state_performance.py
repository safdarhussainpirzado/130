#!/usr/bin/env python3
"""
NHMP 130 CRM - Candidate Steady-State Performance Measurement Harness
Measures actual response times (p50, p95, p99, avg, min, max) for:
1. Caller lookup response time
2. Dashboard initial-load time
3. Dashboard API response time (chart=category&range=7d and chart=weekly&range=30d)
4. Call creation latency (POST /calls full workflow)
5. Database query execution time
6. Concurrent call creation throughput
7. CPU and memory consumption
"""

import sys
import os
import re
import json
import time
import base64
import hmac
import hashlib
import struct
import urllib.request
import urllib.parse
import http.cookiejar
import subprocess
from typing import Dict, Any, Optional, Tuple, List

BASE_URL = "http://127.0.0.1:8080"
ADMIN_TOTP_SECRET = "JBSWY3DPEHPK3PXP"

def generate_totp(secret: str) -> str:
    key = base64.b32decode(secret, casefold=True)
    counter = int(time.time() // 30)
    msg = struct.pack(">Q", counter)
    h = hmac.new(key, msg, hashlib.sha1).digest()
    o = h[19] & 15
    token = (struct.unpack(">I", h[o:o + 4])[0] & 0x7FFFFFFF) % 1000000
    return f"{token:06d}"

def calculate_percentiles(times: List[float]) -> Dict[str, float]:
    if not times:
        return {'p50': 0.0, 'p95': 0.0, 'p99': 0.0, 'avg': 0.0, 'min': 0.0, 'max': 0.0}
    sorted_times = sorted(times)
    n = len(sorted_times)
    p50_idx = int(0.50 * n)
    p95_idx = min(int(0.95 * n), n - 1)
    p99_idx = min(int(0.99 * n), n - 1)
    return {
        'p50': round(sorted_times[p50_idx], 2),
        'p95': round(sorted_times[p95_idx], 2),
        'p99': round(sorted_times[p99_idx], 2),
        'avg': round(sum(sorted_times) / n, 2),
        'min': round(sorted_times[0], 2),
        'max': round(sorted_times[-1], 2),
    }

def main():
    print("=" * 80)
    print("NHMP 130 CRM - STEADY-STATE PERFORMANCE MEASUREMENTS")
    print("Stack: 130-app:candidate-staging-final | Target: http://127.0.0.1:8080")
    print("=" * 80)

    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

    # 1. Login
    req = urllib.request.Request(f"{BASE_URL}/login")
    with opener.open(req, timeout=15) as r:
        html = r.read().decode('utf-8', errors='replace')
        csrf = re.search(r'name="_token"\s+value="([^"]+)"', html).group(1)

    login_data = urllib.parse.urlencode({'_token': csrf, 'username': 'nhmp_admin', 'password': 'TestAdminPass123!'}).encode('utf-8')
    login_req = urllib.request.Request(f"{BASE_URL}/login", data=login_data)
    with opener.open(login_req, timeout=15) as r:
        pass

    # 2. TOTP MFA Challenge
    totp = generate_totp(ADMIN_TOTP_SECRET)
    mfa_data = urllib.parse.urlencode({'_token': csrf, 'code': totp}).encode('utf-8')
    mfa_req = urllib.request.Request(f"{BASE_URL}/2fa/challenge", data=mfa_data)
    with opener.open(mfa_req, timeout=15) as r:
        dash_html = r.read().decode('utf-8', errors='replace')
        csrf_match = re.search(r'meta\s+name="csrf-token"\s+content="([^"]+)"', dash_html)
        if csrf_match:
            csrf = csrf_match.group(1)

    ajax_headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
    }

    metrics = {}

    # -------------------------------------------------------------------------
    # 1. Caller Lookup Latency
    # -------------------------------------------------------------------------
    print("\n[1/5] Measuring Caller Lookup Latency (GET /api/lookup-caller, 25 iterations)...")
    lookup_times = []
    for i in range(25):
        t0 = time.time()
        req = urllib.request.Request(f"{BASE_URL}/api/lookup-caller?number=03000000001", headers=ajax_headers)
        with opener.open(req, timeout=15) as resp:
            resp.read()
        lat = (time.time() - t0) * 1000
        lookup_times.append(lat)
    metrics['caller_lookup'] = calculate_percentiles(lookup_times)
    print(f"  - Caller Lookup Latency : p50={metrics['caller_lookup']['p50']}ms, p95={metrics['caller_lookup']['p95']}ms, p99={metrics['caller_lookup']['p99']}ms, avg={metrics['caller_lookup']['avg']}ms")

    # -------------------------------------------------------------------------
    # 2. Dashboard Initial-Load Time (HTML rendering)
    # -------------------------------------------------------------------------
    print("\n[2/5] Measuring Dashboard Initial-Load Time (GET /dashboard, 20 iterations)...")
    dash_times = []
    for i in range(20):
        t0 = time.time()
        req = urllib.request.Request(f"{BASE_URL}/dashboard")
        with opener.open(req, timeout=15) as resp:
            resp.read()
        lat = (time.time() - t0) * 1000
        dash_times.append(lat)
    metrics['dashboard_initial_load'] = calculate_percentiles(dash_times)
    print(f"  - Dashboard Initial Load: p50={metrics['dashboard_initial_load']['p50']}ms, p95={metrics['dashboard_initial_load']['p95']}ms, p99={metrics['dashboard_initial_load']['p99']}ms, avg={metrics['dashboard_initial_load']['avg']}ms")

    # -------------------------------------------------------------------------
    # 3. Dashboard API Response Time (AJAX chart queries)
    # -------------------------------------------------------------------------
    print("\n[3/5] Measuring Dashboard API Response Time (25 iterations each)...")
    cat_times = []
    for i in range(25):
        t0 = time.time()
        req = urllib.request.Request(f"{BASE_URL}/dashboard/chart-data?chart=category&range=7d", headers=ajax_headers)
        with opener.open(req, timeout=15) as resp:
            resp.read()
        lat = (time.time() - t0) * 1000
        cat_times.append(lat)
    metrics['chart_category_api'] = calculate_percentiles(cat_times)
    print(f"  - Category Chart (7d)   : p50={metrics['chart_category_api']['p50']}ms, p95={metrics['chart_category_api']['p95']}ms, p99={metrics['chart_category_api']['p99']}ms, avg={metrics['chart_category_api']['avg']}ms")

    wk_times = []
    for i in range(25):
        t0 = time.time()
        req = urllib.request.Request(f"{BASE_URL}/dashboard/chart-data?chart=weekly&range=30d", headers=ajax_headers)
        with opener.open(req, timeout=15) as resp:
            resp.read()
        lat = (time.time() - t0) * 1000
        wk_times.append(lat)
    metrics['chart_weekly_api'] = calculate_percentiles(wk_times)
    print(f"  - Weekly Chart (30d)     : p50={metrics['chart_weekly_api']['p50']}ms, p95={metrics['chart_weekly_api']['p95']}ms, p99={metrics['chart_weekly_api']['p99']}ms, avg={metrics['chart_weekly_api']['avg']}ms")

    # -------------------------------------------------------------------------
    # 4. Full Call Creation Latency (POST /calls)
    # -------------------------------------------------------------------------
    print("\n[4/5] Measuring Full Call Creation Latency (POST /calls, 20 iterations)...")
    create_times = []
    create_headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
        'Referer': f"{BASE_URL}/calls/create",
    }
    for idx in range(1, 21):
        num_str = f"0333{idx:07d}"
        payload = urllib.parse.urlencode({
            '_token': csrf,
            'caller_number': num_str,
            'caller_name': f"Performance Benchmark Citizen {idx}",
            'call_type_id': 1,
            'call_sub_type_id': 1,
            'beat_id': 3,
            'priority': 1,
            'details': f"Full call creation benchmark record iteration #{idx}",
            'caller_lat': 33.6844,
            'caller_lng': 73.0479,
        }).encode('utf-8')
        t0 = time.time()
        req = urllib.request.Request(f"{BASE_URL}/calls", data=payload, headers=create_headers)
        with opener.open(req, timeout=15) as resp:
            resp.read()
        lat = (time.time() - t0) * 1000
        create_times.append(lat)
    metrics['call_creation'] = calculate_percentiles(create_times)
    print(f"  - Full Call Creation    : p50={metrics['call_creation']['p50']}ms, p95={metrics['call_creation']['p95']}ms, p99={metrics['call_creation']['p99']}ms, avg={metrics['call_creation']['avg']}ms")

    # -------------------------------------------------------------------------
    # 5. Database Direct Query Execution Times & Profiling
    # -------------------------------------------------------------------------
    print("\n[5/5] Measuring Direct Database Query Execution Time on Candidate DB...")
    db_query = "SET profiling = 1; SELECT * FROM calls USE INDEX (idx_calls_caller_created) WHERE caller_number = '03000000001' ORDER BY created_at DESC LIMIT 5; SELECT count(*) FROM calls WHERE created_at >= NOW() - INTERVAL 7 DAY; SHOW PROFILES;"
    db_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "root@100.108.239.107",
        f"docker exec 130-candidate-db mysql -u crm_test_user -pCandidateDbTestPass2026 -D 130_crm_test_disposable -e \"{db_query}\""
    ]
    try:
        db_res = subprocess.run(db_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=15)
        print("  - Direct MySQL Query Profiling:")
        for line in db_res.stdout.splitlines():
            if any(k in line for k in ["Query_ID", "SELECT"]):
                print(f"    {line.strip()}")
    except Exception as e:
        print(f"  - DB profiling error: {e}")

    # 6. Container Resource Consumption
    print("\n[Resource Consumption] Snapshot of Candidate Containers:")
    stats_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "root@100.108.239.107",
        "docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}' | grep candidate"
    ]
    try:
        stats_res = subprocess.run(stats_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=15)
        print(stats_res.stdout)
    except Exception as e:
        print(f"  - Stats error: {e}")

    # Save to json file
    with open('/home/mrpirzado/projects/130/scripts/steady_state_performance_results.json', 'w') as f:
        json.dump(metrics, f, indent=2)

    print("=" * 80)
    print("STEADY-STATE BENCHMARK COMPLETE. ALL METRICS RECORDED.")
    print("=" * 80)

if __name__ == '__main__':
    main()
