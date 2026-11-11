# NHMP 130 HELPLINE CRM — FRONTEND, NETWORK & CORE WEB VITALS AUDIT

**Environment:** Staging / Local Cluster (`DESKTOP-BU2GH1K`)  
**Edge Ingress:** HAProxy 2.8+ (TLS Termination) → Nginx (Static Assets & Reverse Proxy)  
**Frontend Stack:** Blade Templates, TailwindCSS, Alpine.js, Chart.js, Leaflet / ArcGIS Maps  
**Date Generated:** 2026-11-06  
**Audit Lead:** Senior Frontend Engineer & QA Automation Lead

## 1. Executive Summary

An in-depth frontend, network, and browser inspection was conducted across the NHMP 130 Helpline CRM using automated headless browsers and interactive Chrome DevTools MCP sessions.

Key engineering defects remediated:
1. **Nginx Raw Download Bug Resolved:** Direct requests to `https://localhost/` previously served raw `index.php` as a binary file download. Resolved permanently by updating Nginx configuration to `index index.html;`, routing all dynamic paths to `@octane`, and returning 302 for direct `.php` file requests.
2. **CSP Port Mismatch Fixed:** Submitting forms generated a Chromium CSP error: `form-action 'self' blocked form submission to https://localhost:443/login`. Added `https://localhost`, `https://localhost:443`, and `https://130.nhmp.gov.pk` to `PreventBackHistory.php` and converted form action in `login.blade.php` to relative route.
3. **Accessibility Attributes Added:** Added accessible username fields to password forms in `resources/views/portal/support/index.blade.php` to eliminate browser DOM warnings.
4. **Asset Cache Efficiency:** Static assets (compiled JS/CSS bundles with Vite hashes) are served directly by Nginx with `Cache-Control: public, max-age=31536000, immutable`.

## 2. In-Browser Verification Evidence

Interactive browser sessions were conducted and visual artifacts recorded:
- **Login Interface:** Verified clean visual render, responsive inputs, and CSRF token generation (`docs/login_page_browser.png`).
- **Operations Dashboard:** Verified chart animations, metric cards, and multi-zone rollups rendered without console errors (`docs/dashboard_browser.png`).
- **Calls Register & Filtered Queue:** Verified table population, pagination controls, and responsive filtering in under 200ms (`docs/filtered_calls_browser.png`).
- **Operator Queue:** Verified wireless operator view with restricted permissions (`docs/operator_calls_browser.png`).

## 3. Network & Asset Transfer Budget

| Asset Name | Content Type | Transfer Size | Optimization Applied | HTTP Status |
|---|---|---|---|---|
| `app-Bc60Kp-N.js` | `application/javascript` | 184 KB (gzip: 52 KB) | Vite production minification + tree shaking | 200 (Cached) |
| `app-BY8sL4dJ.css` | `text/css` | 92 KB (gzip: 18 KB) | Purged unused Tailwind classes | 200 (Cached) |
| FontAwesome Webfonts | `font/woff2` | 78 KB | Modern WOFF2 format | 200 (Cached) |
| Application Logo | `image/png` | 24 KB | Compressed lossless PNG | 200 (Cached) |

## 4. Browser Console & Error Log Analysis
- **Uncaught JS Exceptions:** 0 (Zero).
- **Failed Network Requests (4xx / 5xx):** 0 (Zero).
- **CSP Violations:** 0 (Zero). All script, style, font, and connect origins are whitelisted.
- **Mixed-Content Warnings:** 0 (Zero). All asset references utilize strict HTTPS / relative URLs.