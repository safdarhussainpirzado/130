#!/usr/bin/env python3
"""
NHMP 130 CRM — High-Speed Beat Operator Status Resolution Worker
Uses thread-local sessions to concurrently transition pending calls to 'completed' via POST /calls/{id}/status.
"""

import argparse
import re
import threading
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
import requests

TARGET_BASE = "http://127.0.0.1:8080"
thread_local = threading.local()

auth_cookies = {}
auth_headers = {}


def get_token(session: requests.Session, url: str) -> str:
    res = session.get(url, timeout=10)
    match = re.search(r'name="_token"\s+value="([^"]+)"', res.text)
    if match:
        return match.group(1)
    match2 = re.search(r'name="csrf-token"\s+content="([^"]+)"', res.text)
    return match2.group(1) if match2 else ""


def login_master(base_url: str):
    global auth_cookies, auth_headers
    s = requests.Session()
    token = get_token(s, f"{base_url}/login")
    res = s.post(
        f"{base_url}/login",
        data={"_token": token, "username": "nhmp_beat_01", "password": "TestPass123!"},
        allow_redirects=False,
        timeout=10,
    )
    if res.status_code not in (200, 302):
        raise RuntimeError(f"Login failed: HTTP {res.status_code}")

    csrf = get_token(s, f"{base_url}/calls")
    auth_cookies = s.cookies.get_dict()
    auth_headers = {
        "User-Agent": "NHMP-BeatOperator-Resolver",
        "X-Requested-With": "XMLHttpRequest",
        "Accept": "application/json",
        "X-CSRF-TOKEN": csrf,
    }


def get_thread_session() -> requests.Session:
    if not hasattr(thread_local, "session"):
        s = requests.Session()
        s.cookies.update(auth_cookies)
        s.headers.update(auth_headers)
        thread_local.session = s
    return thread_local.session


def complete_call(base_url: str, call_id: int) -> bool:
    s = get_thread_session()
    try:
        # Step 1: in_progress
        r1 = s.post(
            f"{base_url}/calls/{call_id}/status",
            data={
                "status": "in_progress",
                "tiger_id": "TIGER-14",
                "patrol_officer_id": "PO Tariq Mehmood",
                "remarks": "Dispatched patrol unit to incident scene",
            },
            timeout=8,
        )
        if r1.status_code not in (200, 302):
            return False

        # Step 2: completed
        r2 = s.post(
            f"{base_url}/calls/{call_id}/status",
            data={
                "status": "completed",
                "remarks": "Assistance completed safely. Highway lane clear.",
            },
            timeout=8,
        )
        return r2.status_code in (200, 302)
    except Exception:
        return False


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--target", default="http://127.0.0.1:8080")
    parser.add_argument("--start", type=int, default=1)
    parser.add_argument("--end", type=int, default=10001)
    parser.add_argument("--concurrency", type=int, default=60)
    args = parser.parse_args()

    print(f"Connecting to {args.target} as Beat Operator...")
    login_master(args.target)
    print("✓ Beat Wireless Operator master session initialized.")

    total = args.end - args.start + 1
    print(f"Executing status completion for {total:,} calls with {args.concurrency} concurrent threads...")
    t0 = time.time()
    completed_count = 0
    failed_count = 0

    with ThreadPoolExecutor(max_workers=args.concurrency) as executor:
        futures = {executor.submit(complete_call, args.target, cid): cid for cid in range(args.start, args.end + 1)}
        for f in as_completed(futures):
            if f.result():
                completed_count += 1
            else:
                failed_count += 1

            done = completed_count + failed_count
            if done % 1000 == 0 or done == total:
                elapsed = time.time() - t0
                rate = done / max(elapsed, 0.01)
                print(f"  [{done:,}/{total:,}] Completed: {completed_count:,} | Rate: {rate:.1f} calls/s ({rate*2:.1f} req/s)")

    elapsed = time.time() - t0
    print(f"\n✓ Completed {completed_count:,} calls in {elapsed:.2f}s ({completed_count/elapsed:.1f} calls/s)")


if __name__ == "__main__":
    main()
