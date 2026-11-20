const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SCREENSHOT_DIR = '/home/mrpirzado/projects/130-push/docs/evidence/push-notifications';
if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NHMP 130 Collapsed Sidebar Tooltip Verification</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
        margin: 0;
        background: #f8fafc;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        display: flex;
        height: 100vh;
        overflow: hidden;
    }

    .sidebar-wrap {
        width: 68px;
        background: #ffffff;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        position: relative;
        box-shadow: 2px 0 20px rgba(0, 0, 0, 0.04);
    }

    .sidebar-inner {
        width: 100%;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .nav-item {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.625rem 0.5rem;
        border-radius: 0.75rem;
        color: #475569;
        text-decoration: none;
        margin-bottom: 4px;
        transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        cursor: pointer;
        position: relative;
    }

    .nav-item:hover, .nav-item:focus-visible {
        background: #f1f5f9;
        color: #1e293b;
        outline: 2px solid #3b82f6;
    }

    .nav-item.active {
        background: #eff6ff;
        color: #2563eb;
    }

    .nav-icon {
        font-size: 1.15rem;
        transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .nav-item:hover .nav-icon, .nav-item:focus-visible .nav-icon {
        transform: scale(1.18);
    }

    .nav-label {
        display: none;
    }

    /* Suppress in-DOM clipped tooltip */
    .sidebar-wrap.collapsed .nav-tooltip {
        display: none !important;
    }
</style>
</head>
<body>

    <aside class="sidebar-wrap collapsed">
        <div class="sidebar-inner">
            <div class="flex items-center justify-center h-[64px] border-b border-slate-100">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-md">
                    <i class="fa-solid fa-shield-halved text-sm"></i>
                </div>
            </div>

            <nav class="flex-1 py-4 px-2 space-y-1 overflow-y-auto">
                <a id="nav-dashboard" href="#" tabindex="0" class="nav-item active">
                    <span class="nav-icon text-blue-600"><i class="fa-solid fa-chart-line"></i></span>
                    <span class="nav-label">Dashboard</span>
                    <span class="nav-tooltip">Dashboard Overview</span>
                </a>

                <a id="nav-calls" href="#" tabindex="0" class="nav-item">
                    <span class="nav-icon text-emerald-600"><i class="fa-solid fa-plus-circle"></i></span>
                    <span class="nav-label">Add Call</span>
                    <span class="nav-tooltip">Add New Help</span>
                </a>

                <a id="nav-pending" href="#" tabindex="0" class="nav-item">
                    <span class="nav-icon text-rose-500"><i class="fa-solid fa-bell-concierge"></i></span>
                    <span class="nav-label">Pending Helps</span>
                    <span class="nav-badge px-1.5 py-0.5 rounded text-[10px] font-black bg-rose-500 text-white shadow-sm ml-auto">3</span>
                    <span class="nav-tooltip">Pending Helps (3 Pending)</span>
                </a>

                <a id="nav-support" href="#" tabindex="0" class="nav-item">
                    <span class="nav-icon text-blue-600"><i class="fa-solid fa-headset"></i></span>
                    <span class="nav-label">Support Center</span>
                    <span id="support-pending-badge" class="px-1.5 py-0.5 rounded text-[10px] font-black bg-rose-500 text-white shadow-sm ml-auto">5</span>
                    <span class="nav-tooltip">Support Center (5 Pending)</span>
                </a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 p-8 bg-slate-50">
        <h1 class="text-2xl font-black text-slate-800">NHMP 130 Control Grid</h1>
        <p class="text-sm text-slate-500 mt-1">Hover or press Tab to test zero-clip floating tooltips.</p>
    </main>

    <!-- Premium Floating Tooltip Singleton -->
    <div id="sidebar-floating-tooltip" 
         class="fixed pointer-events-none transition-all duration-150 z-[99999] hidden opacity-0 scale-95 origin-left"
         role="tooltip"
         aria-hidden="true"
         style="will-change: transform, opacity; top: 0; left: 0;">
        <div class="relative flex items-center gap-2.5 px-3 py-1.5 rounded-lg shadow-2xl backdrop-blur-md bg-slate-900/95 border border-slate-700/60 text-white">
            <div class="absolute -left-1.5 top-1/2 -translate-y-1/2 w-3 h-3 bg-slate-900/95 border-l border-b border-slate-700/60 rotate-45 pointer-events-none"></div>
            <span id="floating-tooltip-title" class="font-bold text-xs tracking-wide text-slate-100 whitespace-nowrap"></span>
            <span id="floating-tooltip-badge" class="hidden px-1.5 py-0.5 rounded text-[10px] font-black bg-rose-500 text-white shadow-sm leading-none"></span>
        </div>
    </div>

    <script>
        (function() {
            const tooltip = document.getElementById('sidebar-floating-tooltip');
            const titleEl = document.getElementById('floating-tooltip-title');
            const badgeEl = document.getElementById('floating-tooltip-badge');
            if (!tooltip || !titleEl) return;

            let activeTarget = null;
            let hideTimeout = null;

            const isCoarsePointer = () => window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

            function hideTooltip() {
                activeTarget = null;
                tooltip.classList.remove('opacity-100', 'scale-100');
                tooltip.classList.add('opacity-0', 'scale-95');
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(() => {
                    if (!activeTarget) {
                        tooltip.classList.add('hidden');
                        tooltip.setAttribute('aria-hidden', 'true');
                    }
                }, 150);
            }

            function showTooltip(el) {
                if (isCoarsePointer()) return;

                const sidebar = document.querySelector('.sidebar-wrap');
                if (!sidebar || !sidebar.classList.contains('collapsed')) {
                    hideTooltip();
                    return;
                }

                const tooltipTextEl = el.querySelector('.nav-tooltip');
                const labelEl = el.querySelector('.nav-label');
                let text = tooltipTextEl ? tooltipTextEl.textContent.trim() : (labelEl ? labelEl.textContent.trim() : '');
                if (!text) return;

                let badgeText = '';
                const badgeSpan = el.querySelector('.nav-badge, #support-pending-badge, span[class*="bg-rose"]');
                if (badgeSpan && !badgeSpan.classList.contains('hidden') && badgeSpan.offsetParent !== null) {
                    badgeText = badgeSpan.textContent.trim();
                }

                text = text.replace(/\\s*\\(\\d+\\s*Pending\\)/i, '');

                titleEl.textContent = text;
                if (badgeText && badgeText !== '!') {
                    badgeEl.textContent = badgeText;
                    badgeEl.classList.remove('hidden');
                } else {
                    badgeEl.classList.add('hidden');
                }

                activeTarget = el;
                clearTimeout(hideTimeout);
                tooltip.classList.remove('hidden');
                tooltip.setAttribute('aria-hidden', 'false');

                const rect = el.getBoundingClientRect();
                const left = rect.right + 12;
                const top = rect.top + (rect.height / 2);

                tooltip.style.left = \`\${left}px\`;
                tooltip.style.top = \`\${top}px\`;
                tooltip.style.transform = 'translateY(-50%)';

                requestAnimationFrame(() => {
                    tooltip.classList.remove('opacity-0', 'scale-95');
                    tooltip.classList.add('opacity-100', 'scale-100');
                });
            }

            document.addEventListener('mouseover', function(e) {
                const navItem = e.target.closest('.sidebar-wrap.collapsed .nav-item');
                if (navItem) {
                    showTooltip(navItem);
                } else if (!e.target.closest('#sidebar-floating-tooltip')) {
                    if (activeTarget) hideTooltip();
                }
            });

            document.addEventListener('mouseout', function(e) {
                const navItem = e.target.closest('.sidebar-wrap.collapsed .nav-item');
                if (navItem && !navItem.contains(e.relatedTarget)) {
                    hideTooltip();
                }
            });

            document.addEventListener('focusin', function(e) {
                const navItem = e.target.closest('.sidebar-wrap.collapsed .nav-item');
                if (navItem) {
                    showTooltip(navItem);
                }
            });

            document.addEventListener('focusout', function(e) {
                const navItem = e.target.closest('.sidebar-wrap.collapsed .nav-item');
                if (navItem && !navItem.contains(e.relatedTarget)) {
                    hideTooltip();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') hideTooltip();
            });

            window.addEventListener('scroll', hideTooltip, { passive: true, capture: true });
            window.addEventListener('resize', hideTooltip, { passive: true });
        })();
    </script>
</body>
</html>
`;

async function runBrowserTest() {
    console.log('--- Starting Playwright UI Verification ---');
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage({
        viewport: { width: 1280, height: 720 },
        deviceScaleFactor: 2
    });

    await page.setContent(htmlContent, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(300);

    // TEST 1: Initial state (Hidden)
    const initialHidden = await page.$eval('#sidebar-floating-tooltip', el => el.classList.contains('hidden'));
    console.log('1. Initial tooltip hidden assertion:', initialHidden ? 'PASS' : 'FAIL');
    if (!initialHidden) throw new Error('Tooltip was not initially hidden');

    // TEST 2: Mouse hover on #nav-support
    console.log('2. Testing mouse hover on #nav-support...');
    await page.hover('#nav-support');
    await page.waitForTimeout(200);

    const supportTooltipVisible = await page.$eval('#sidebar-floating-tooltip', el => 
        !el.classList.contains('hidden') && el.classList.contains('opacity-100')
    );
    const titleText = await page.$eval('#floating-tooltip-title', el => el.textContent.trim());
    const badgeText = await page.$eval('#floating-tooltip-badge', el => el.textContent.trim());

    console.log('   Tooltip Visible on Hover:', supportTooltipVisible ? 'PASS' : 'FAIL');
    console.log('   Tooltip Title:', titleText, titleText === 'Support Center' ? '(MATCH)' : '(FAIL)');
    console.log('   Tooltip Badge:', badgeText, badgeText === '5' ? '(MATCH)' : '(FAIL)');
    if (!supportTooltipVisible || titleText !== 'Support Center' || badgeText !== '5') {
        throw new Error('Support tooltip assertion failed');
    }

    // Capture screenshot of tooltip
    const screenshotPath = path.join(SCREENSHOT_DIR, 'sidebar_collapsed_premium_tooltip.png');
    await page.screenshot({ path: screenshotPath });
    console.log('   Screenshot saved to:', screenshotPath);

    // TEST 3: Mouseout to body
    console.log('3. Testing mouseout to main area...');
    await page.hover('h1');
    await page.waitForTimeout(300);
    const hiddenAfterLeave = await page.$eval('#sidebar-floating-tooltip', el => 
        el.classList.contains('opacity-0') || el.classList.contains('hidden')
    );
    console.log('   Tooltip hides after mouseout:', hiddenAfterLeave ? 'PASS' : 'FAIL');
    if (!hiddenAfterLeave) throw new Error('Tooltip did not hide on mouseout');

    // TEST 4: Keyboard Navigation (Tab focus)
    console.log('4. Testing keyboard focus (Tab key)...');
    await page.keyboard.press('Tab'); // focuses #nav-dashboard
    await page.waitForTimeout(200);
    const dashboardTitle = await page.$eval('#floating-tooltip-title', el => el.textContent.trim());
    console.log('   Keyboard Tab focus Title:', dashboardTitle, dashboardTitle === 'Dashboard Overview' ? '(MATCH)' : '(FAIL)');
    if (dashboardTitle !== 'Dashboard Overview') throw new Error('Keyboard Tab focus failed');

    // TEST 5: Escape key dismissal
    console.log('5. Testing Escape key dismissal...');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);
    const hiddenOnEscape = await page.$eval('#sidebar-floating-tooltip', el => 
        el.classList.contains('opacity-0') || el.classList.contains('hidden')
    );
    console.log('   Tooltip hides on Escape key:', hiddenOnEscape ? 'PASS' : 'FAIL');
    if (!hiddenOnEscape) throw new Error('Escape key dismissal failed');

    await browser.close();
    console.log('--- ALL 5 BROWSER TOOLTIP TESTS PASSED (100%) ---');
}

runBrowserTest().catch(err => {
    console.error('Test execution error:', err);
    process.exit(1);
});
