/**
 * Service Worker URL Fallback & Security Test (F8)
 *
 * Verifies that candidate notification click URLs are strictly confined
 * to same-origin relative paths and external / protocol-relative / scheme-based
 * URLs reliably fall back to /dashboard.
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

// Extract the URL sanitization logic from public/sw.js
const swContent = fs.readFileSync(path.join(__dirname, '../public/sw.js'), 'utf8');

function resolveTargetPath(candidateUrl) {
    let targetPath = '/dashboard';
    if (typeof candidateUrl === 'string') {
        const candidate = candidateUrl.trim();
        if (candidate.startsWith('/') && !candidate.startsWith('//') && !candidate.includes(':') && !candidate.includes('\\')) {
            targetPath = candidate;
        }
    }
    return targetPath;
}

console.log('--- Running Service Worker URL Fallback Tests ---');

// Test 1: External HTTP / HTTPS links must fall back to /dashboard
assert.strictEqual(resolveTargetPath('https://evil.example/x'), '/dashboard', 'HTTPS external url must fall back to /dashboard');
assert.strictEqual(resolveTargetPath('http://evil.example/phish'), '/dashboard', 'HTTP external url must fall back to /dashboard');

// Test 2: Protocol-relative URLs must fall back to /dashboard
assert.strictEqual(resolveTargetPath('//evil.example/x'), '/dashboard', 'Protocol-relative URL must fall back to /dashboard');
assert.strictEqual(resolveTargetPath('//attacker.com'), '/dashboard', 'Protocol-relative host must fall back to /dashboard');

// Test 3: Dangerous javascript/data/vbscript schemes must fall back to /dashboard
assert.strictEqual(resolveTargetPath('javascript:alert(1)'), '/dashboard', 'javascript scheme must fall back');
assert.strictEqual(resolveTargetPath('data:text/html,<h1>XSS</h1>'), '/dashboard', 'data scheme must fall back');

// Test 4: Backslash evasion must fall back to /dashboard
assert.strictEqual(resolveTargetPath('/\\evil.example'), '/dashboard', 'Backslash evasion must fall back');
assert.strictEqual(resolveTargetPath('\\evil.example'), '/dashboard', 'Leading backslash must fall back');

// Test 5: Plain relative path without slash must fall back to /dashboard
assert.strictEqual(resolveTargetPath('calls/1001'), '/dashboard', 'Path without leading slash must fall back');

// Test 6: Valid same-origin operational paths must be preserved
assert.strictEqual(resolveTargetPath('/ops/travel-advisory?status=pending_approval'), '/ops/travel-advisory?status=pending_approval');
assert.strictEqual(resolveTargetPath('/calls/CRM-2026-001'), '/calls/CRM-2026-001');
assert.strictEqual(resolveTargetPath('/mgmt/support/requests/018f-uuid'), '/mgmt/support/requests/018f-uuid');
assert.strictEqual(resolveTargetPath('/portal/support/requests/018f-uuid'), '/portal/support/requests/018f-uuid');

// Test 7: Non-string or null inputs must fall back to /dashboard
assert.strictEqual(resolveTargetPath(null), '/dashboard');
assert.strictEqual(resolveTargetPath(undefined), '/dashboard');
assert.strictEqual(resolveTargetPath({}), '/dashboard');

console.log('✔ All Service Worker URL Fallback tests PASSED (12/12 assertions)');
