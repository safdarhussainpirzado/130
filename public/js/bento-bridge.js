/**
 * Bento Bridge — High-Performance SPA Navigation Engine for NHMP 130 CRM
 * 
 * Manages persistent application shell, partial page swaps via X-PJAX,
 * comprehensive component lifecycle destruction, and browser history synchronization.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Record persistent shell mount timestamp
    window.__SHELL_MOUNT_TIME__ = window.__SHELL_MOUNT_TIME__ || Date.now();
    window.__PJAX_NAVIGATION_COUNT__ = window.__PJAX_NAVIGATION_COUNT__ || 0;

    // Seed initial browser history state so popstate back to initial landing page works smoothly
    if (!window.history.state || !window.history.state.url) {
        window.history.replaceState({ url: window.location.href }, '', window.location.href);
    }

    const mainArea = document.querySelector('main');
    const headerArea = document.querySelector('header');
    const sidebar = document.querySelector('aside');

    if (!mainArea) return;

    // Loading Progress Bar
    const getProgressBar = () => {
        let bar = document.getElementById('bento-nav-progress');
        if (bar) return bar;
        bar = document.createElement('div');
        bar.id = 'bento-nav-progress';
        bar.className = 'fixed top-0 left-0 h-1 bg-gradient-to-r from-blue-600 via-indigo-500 to-emerald-500 z-[10000] transition-all duration-200 opacity-0 pointer-events-none';
        bar.style.width = '0%';
        document.body.appendChild(bar);
        return bar;
    };

    const progressBar = getProgressBar();
    let progressTimer1 = null;
    let progressTimer2 = null;

    const startProgress = () => {
        clearTimeout(progressTimer1);
        clearTimeout(progressTimer2);
        window.dispatchEvent(new CustomEvent('bento:page-loading'));
        progressBar.style.transition = 'width 0.2s ease, opacity 0.1s ease';
        progressBar.style.width = '0%';
        progressBar.style.opacity = '1';
        progressTimer1 = setTimeout(() => progressBar.style.width = '45%', 30);
        progressTimer2 = setTimeout(() => progressBar.style.width = '75%', 180);
    };

    const endProgress = () => {
        clearTimeout(progressTimer1);
        clearTimeout(progressTimer2);
        progressBar.style.width = '100%';
        setTimeout(() => {
            progressBar.style.opacity = '0';
            setTimeout(() => { progressBar.style.width = '0%'; }, 200);
        }, 150);
    };

    // Clean Component Lifecycle Destruction
    const destroyCurrentPageLifecycle = () => {
        // 1. Cleanly destroy Leaflet map instances
        if (window._geospatialMarkerMap) {
            try {
                window._geospatialMarkerMap.off();
                window._geospatialMarkerMap.remove();
            } catch (e) {
                console.warn('Bento Bridge: Map cleanup warning', e);
            }
            window._geospatialMarkerMap = null;
        }

        if (window._geospatialLandmarkMap) {
            try {
                window._geospatialLandmarkMap.off();
                window._geospatialLandmarkMap.remove();
            } catch (e) {}
            window._geospatialLandmarkMap = null;
        }

        if (window._ccMap) {
            try {
                window._ccMap.off();
                window._ccMap.remove();
            } catch (e) {
                console.warn('Bento Bridge: Incident map cleanup warning', e);
            }
            window._ccMap = null;
        }

        // Clean any lingering Leaflet container IDs in mainArea
        if (mainArea) {
            mainArea.querySelectorAll('.leaflet-container, [id*="map"]').forEach(el => {
                if (el._leaflet_id) {
                    el._leaflet_id = null;
                }
            });
        }

        // 2. Destroy Chart.js instances to prevent memory leaks and zombie tooltips
        if (window.Chart && typeof window.Chart.getChart === 'function' && mainArea) {
            mainArea.querySelectorAll('canvas').forEach(canvas => {
                try {
                    const chart = window.Chart.getChart(canvas);
                    if (chart) chart.destroy();
                } catch (e) {}
            });
        }

        if (window._dashboardCharts) {
            Object.keys(window._dashboardCharts).forEach(key => {
                try {
                    if (window._dashboardCharts[key] && typeof window._dashboardCharts[key].destroy === 'function') {
                        window._dashboardCharts[key].destroy();
                    }
                } catch(e) {}
                window._dashboardCharts[key] = null;
            });
        }

        // 3. Cleanly destroy existing Alpine reactive tree
        if (window.Alpine && typeof window.Alpine.destroyTree === 'function' && mainArea) {
            try {
                window.Alpine.destroyTree(mainArea);
            } catch (e) {
                // Ignore destroy errors on uninitialized subtrees
            }
        }
    };

    // In-flight request controller
    let activeNavController = null;

    /**
     * Core Page Loading Logic
     */
    const loadPage = async (url, options = { pushState: true, method: 'GET', body: null }) => {
        // Abort previous in-flight navigation if user clicked another link rapidly
        if (activeNavController) {
            activeNavController.abort();
        }
        activeNavController = new AbortController();
        const signal = activeNavController.signal;

        startProgress();

        try {
            const fetchOptions = {
                method: options.method || 'GET',
                signal: signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-PJAX': 'true',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            };

            if (options.body) {
                fetchOptions.body = options.body;
            }

            const response = await fetch(url, fetchOptions);

            // Handle security redirects/403s / login redirects
            if (response.status === 401 || response.status === 419) {
                window.location.href = '/login';
                return;
            }

            if (response.status === 403) {
                const text = await response.text();
                if (text.includes('MFA_REQUIRED') || text.includes('2fa')) {
                    window.location.href = '/2fa';
                    return;
                }
            }

            if (response.url && (response.url.includes('/login') || response.url.includes('/2fa'))) {
                window.location.href = response.url;
                return;
            }

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('text/html')) {
                // Non-HTML content (e.g. file download or JSON): fallback to full browser handling
                window.location.href = url;
                return;
            }

            if (!response.ok && response.status !== 422) {
                throw new Error(`HTTP Error ${response.status}`);
            }

            const finalUrl = response.url || url;
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // 1. Lifecycle Cleanup
            destroyCurrentPageLifecycle();

            // 2. Extract and transfer JSON data scripts FIRST before DOM replacement
            const jsonScripts = Array.from(doc.querySelectorAll('script[type="application/json"]'));
            jsonScripts.forEach(s => {
                if (s.id) {
                    const existing = document.getElementById(s.id);
                    if (existing) existing.remove();
                }
                const newScript = document.createElement('script');
                if (s.id) newScript.id = s.id;
                newScript.type = 'application/json';
                newScript.textContent = s.textContent;
                document.body.appendChild(newScript);
            });

            // Extract inline scripts before stripping them from main
            const inlineScripts = Array.from(doc.querySelectorAll('script:not([type="application/json"])'));

            // 3. Swap Main Content Container
            const newMain = doc.querySelector('main');
            if (newMain && mainArea) {
                // Remove scripts from newMain so they don't linger as dead inert tags
                newMain.querySelectorAll('script').forEach(s => s.remove());
                mainArea.innerHTML = newMain.innerHTML;
                mainArea.scrollTop = 0;
            } else if (!newMain && doc.body) {
                // Fallback: If partial rendered directly without <main> wrapper
                doc.body.querySelectorAll('script').forEach(s => s.remove());
                mainArea.innerHTML = doc.body.innerHTML;
                mainArea.scrollTop = 0;
            }

            // 4. Update Page Title and Header
            const meta = doc.getElementById('pjax-meta');
            const metaTitle = meta?.dataset?.title || doc.title;
            const metaPageTitle = meta?.dataset?.pageTitle || metaTitle;
            const headerTitleEl = headerArea?.querySelector('h2');
            if (metaPageTitle && headerTitleEl) {
                headerTitleEl.textContent = metaPageTitle;
            } else {
                const newHeaderH2 = doc.querySelector('header h2');
                if (newHeaderH2 && headerTitleEl) {
                    headerTitleEl.textContent = newHeaderH2.textContent;
                }
            }

            if (metaTitle) {
                document.title = metaTitle;
            } else if (doc.title) {
                document.title = doc.title;
            }

            // 5. Synchronize Sidebar Active State (Single-active guarantee)
            if (sidebar) {
                const targetUrl = new URL(finalUrl, window.location.origin);
                const targetPath = targetUrl.pathname;
                const targetQuery = targetUrl.search;
                const navLinks = Array.from(sidebar.querySelectorAll('a.nav-item'));

                let bestLink = null;
                let bestScore = -1;

                navLinks.forEach(link => {
                    link.classList.remove('active');
                    const linkUrl = new URL(link.href, window.location.origin);
                    const linkPath = linkUrl.pathname;
                    const linkQuery = linkUrl.search;

                    if (linkQuery && linkPath === targetPath && linkQuery === targetQuery) {
                        if (bestScore < 1000) { bestScore = 1000; bestLink = link; }
                    } else if (linkPath === targetPath) {
                        if (bestScore < 500) { bestScore = 500; bestLink = link; }
                    } else if ((targetPath === '/' || targetPath === '/dashboard') && (linkPath === '/' || linkPath === '/dashboard')) {
                        if (bestScore < 400) { bestScore = 400; bestLink = link; }
                    } else if (linkPath !== '/' && linkPath !== '/dashboard' && targetPath.startsWith(linkPath + '/')) {
                        const score = linkPath.length;
                        if (score > bestScore && bestScore < 500) {
                            bestScore = score;
                            bestLink = link;
                        }
                    }
                });

                if (bestLink) {
                    bestLink.classList.add('active');
                }
            }

            // 6. Update Browser History State
            if (options.pushState) {
                window.history.pushState({ url: finalUrl }, '', finalUrl);
            }

            // 7. Execute Inline Execution Scripts
            inlineScripts.forEach(s => {
                if (s.getAttribute('src')) return;
                const code = s.innerHTML.trim();
                if (!code) return;

                const newScript = document.createElement('script');
                const type = s.getAttribute('type');
                if (type) newScript.setAttribute('type', type);
                newScript.appendChild(document.createTextNode(code));
                document.body.appendChild(newScript);
                document.body.removeChild(newScript);
            });

            // 8. Dispatch Alpine events & Initialize Alpine Reactive Tree for New Page Content
            try {
                document.dispatchEvent(new Event('alpine:init'));
                window.dispatchEvent(new Event('alpine:init'));
            } catch (e) {}

            if (window.Alpine) {
                setTimeout(() => {
                    try {
                        window.Alpine.initTree(mainArea);
                    } catch (alpineErr) {
                        console.warn('Bento Bridge: Alpine init error', alpineErr);
                    }
                }, 0);
            }

            // 9. Focus Management
            const autoFocus = mainArea.querySelector('[autofocus]');
            if (autoFocus) {
                setTimeout(() => autoFocus.focus(), 80);
            }

            // Increment navigation counter
            window.__PJAX_NAVIGATION_COUNT__ = (window.__PJAX_NAVIGATION_COUNT__ || 0) + 1;

            window.dispatchEvent(new CustomEvent('bento:page-loaded', { 
                detail: { url: finalUrl, count: window.__PJAX_NAVIGATION_COUNT__ } 
            }));

        } catch (error) {
            if (error.name === 'AbortError') {
                return; // Suppressed intentional abort
            }
            console.error('Bento Bridge Navigation Fallback Triggered:', error);
            // Safe fallback: Full document navigation if PJAX fails
            if (options.pushState && options.method === 'GET') {
                window.location.href = url;
            }
        } finally {
            endProgress();
            activeNavController = null;
        }
    };

    // Expose programmatic navigation
    window.bentoNavigate = (url) => loadPage(url, { pushState: true });

    // Intercept Eligible Internal Navigation Links
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link || e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        const href = link.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return;
        }

        // Must be same-origin
        if (link.origin !== window.location.origin) return;

        // Exclusions: logout, downloads, full-page targets, modals
        if (link.hasAttribute('data-no-pjax') || link.hasAttribute('download') || link.getAttribute('target') === '_blank') return;
        if (href.includes('logout') || link.innerText.toLowerCase().includes('log out')) return;

        const lowerHref = href.toLowerCase();
        if (lowerHref.endsWith('.csv') || lowerHref.endsWith('.pdf') || lowerHref.endsWith('.xlsx') || lowerHref.endsWith('.zip') || lowerHref.includes('/export')) {
            return; // Allow native browser download
        }

        e.preventDefault();
        loadPage(link.href);
    });

    // Intercept ONLY forms explicitly requesting PJAX
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form.hasAttribute('data-pjax')) return; // Opt-in only to protect Alpine forms & modals

        const actionUrl = new URL(form.action || window.location.href, window.location.origin);
        if (actionUrl.origin !== window.location.origin) return;

        e.preventDefault();
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const formData = new FormData(form);

        loadPage(form.action, {
            pushState: true,
            method: method,
            body: method === 'GET' ? null : formData
        });
    });

    // Handle Browser Back / Forward History Navigation
    window.addEventListener('popstate', (e) => {
        const targetUrl = e.state?.url || window.location.href;
        loadPage(targetUrl, { pushState: false });
    });
});
