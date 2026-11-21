/* Browser test for the Desktop Alerts enrollment modal (public/js/webpush-client.js + notification.js).
 * Run: NODE_PATH=/home/mrpirzado/projects/130/node_modules node tests/push_enroll_modal_test.cjs
 * Uses a local static server; push subscribe + server endpoints are mocked. */
const { chromium } = require('playwright');
const http = require('http'); const fs = require('fs'); const path = require('path');
const ROOT = path.resolve(__dirname, '..', 'public');
const OUT = path.resolve(__dirname, '..', 'docs', 'evidence', 'push-notifications');
const PUB = 'BHhZCaBcuoTt-VFDNZq3VptCL-fjbtI0RH1ab6116oPwEOP0s8gPHzdWO27F7lZssXkH44atZYjPO0T80mTeMrA';
const page_html = `<!doctype html><html><head><meta charset="utf-8"><meta name="csrf-token" content="t">
<style>body{font-family:system-ui;background:#f1f5f9;margin:0}header{padding:14px;background:#fff;display:flex;justify-content:flex-end}</style></head><body>
<header><button id="btn-desktop-alerts-toggle" type="button" data-state="unsubscribed" style="height:40px;padding:0 12px;border:2px solid #e2e8f0;border-radius:16px;background:#f8fafc;font-weight:700">
<span id="desktop-alerts-status-dot" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#94a3b8"></span>
<span id="desktop-alerts-status-text">Desktop Alerts</span></button></header>
<script src="/js/notification.js"></script><script src="/js/webpush-client.js"></script></body></html>`;
const srv = http.createServer((req, res) => {
  const u = req.url.split('?')[0];
  if (u === '/') { res.setHeader('content-type', 'text/html'); return res.end(page_html); }
  if (u === '/web-push/vapid-public-key') { res.setHeader('content-type', 'application/json'); return res.end(JSON.stringify({ publicKey: PUB })); }
  if (u === '/web-push/subscribe' || u === '/web-push/unsubscribe') { res.setHeader('content-type', 'application/json'); return res.end('{"success":true}'); }
  const f = path.join(ROOT, u);
  if (f.startsWith(ROOT) && fs.existsSync(f) && fs.statSync(f).isFile()) { res.setHeader('content-type', f.endsWith('.js') ? 'text/javascript' : 'application/octet-stream'); return res.end(fs.readFileSync(f)); }
  res.statusCode = 404; res.end('nf');
});
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ' + m); } else { fail++; console.log('  FAIL ' + m); } };
(async () => {
  await new Promise(r => srv.listen(0, '127.0.0.1', r)); const base = 'http://localhost:' + srv.address().port;
  const browser = await chromium.launch({ executablePath: process.env.CHROME_BIN || undefined });
  async function newPage(perm, subscribeBehaviour) {
    const ctx = await browser.newContext({ viewport: { width: 1100, height: 700 } });
    if (perm === 'granted') await ctx.grantPermissions(['notifications'], { origin: base });
    const page = await ctx.newPage(); const dialogs = []; page.on('dialog', d => { dialogs.push(d.message()); d.dismiss(); });
    await page.addInitScript((mode) => {
      const fakeSub = { endpoint: 'https://fcm.googleapis.com/fcm/send/fake', toJSON() { return { endpoint: this.endpoint, keys: { p256dh: 'p', auth: 'a' } }; }, async unsubscribe() { return true; } };
      window.__subState = null;
      PushManager.prototype.subscribe = async function () { if (mode === 'denied') { const e = new Error('denied'); e.name = 'NotAllowedError'; throw e; } window.__subState = fakeSub; return fakeSub; };
      PushManager.prototype.getSubscription = async function () { return window.__subState; };
      if (mode === 'denied') PushManager.prototype.permissionState = async function () { return window.__denied ? 'denied' : 'prompt'; };
    }, subscribeBehaviour);
    await page.goto(base + '/'); await page.waitForFunction(() => window.WebPushClient && window.WebPushClient.swRegistration, null, { timeout: 15000 }).catch(() => {});
    return { page, dialogs };
  }
  console.log('Test 1: native Notification API is preserved and toast helpers still work');
  { const { page } = await newPage('granted', 'ok');
    const r = await page.evaluate(() => ({ perm: typeof Notification.requestPermission, native: typeof Notification === 'function', toast: typeof Notification.success }));
    ok(r.perm === 'function' && r.native, 'Notification.requestPermission is a function'); ok(r.toast === 'function', 'Notification.success (legacy toast API) still exists'); }
  console.log('Test 2: click opens the premium confirmation modal (no native alert)');
  const t = await newPage('granted', 'ok'); const { page, dialogs } = t;
  await page.click('#btn-desktop-alerts-toggle'); await page.waitForSelector('.nhmp-pm-card', { timeout: 5000 });
  ok(/Turn on desktop alerts/.test(await page.textContent('.nhmp-pm-title')), 'confirmation modal shown'); await page.screenshot({ path: path.join(OUT, 'enroll-modal-confirm.png') });
  console.log('Test 3: Enable -> subscribed, toast shown, success modal, button state updated');
  await page.click('.nhmp-pm-btn.primary'); await page.waitForSelector('#notification-container *', { timeout: 6000 }).catch(() => {});
  await page.waitForFunction(() => document.getElementById('btn-desktop-alerts-toggle').dataset.state === 'subscribed', null, { timeout: 8000 }).catch(() => {});
  ok(await page.getAttribute('#btn-desktop-alerts-toggle', 'data-state') === 'subscribed', 'state is subscribed');
  ok(await page.locator('#notification-container').count() > 0 && (await page.textContent('#notification-container')).toLowerCase().includes('alerts enabled'), 'CRM toast (notification.js) shown');
  await page.waitForSelector('.nhmp-pm-card', { timeout: 3000 }).catch(() => {}); await page.screenshot({ path: path.join(OUT, 'enroll-modal-success.png') });
  ok(dialogs.length === 0, 'no native alert() dialogs fired');
  console.log('Test 4: Disable confirmation modal + toast');
  await page.waitForSelector('.nhmp-pm-card', { state: 'detached', timeout: 6000 }).catch(() => {});
  await page.click('#btn-desktop-alerts-toggle'); await page.waitForSelector('.nhmp-pm-title');
  ok(/Turn off desktop alerts/.test(await page.textContent('.nhmp-pm-title')), 'disable confirmation shown'); await page.screenshot({ path: path.join(OUT, 'enroll-modal-disable.png') });
  await page.click('.nhmp-pm-btn.danger'); await page.waitForFunction(() => document.getElementById('btn-desktop-alerts-toggle').dataset.state === 'unsubscribed', null, { timeout: 6000 }).catch(() => {});
  ok(await page.getAttribute('#btn-desktop-alerts-toggle', 'data-state') === 'unsubscribed', 'state back to unsubscribed');
  console.log('Test 5: permission denied -> blocked modal with steps (no native alert)');
  { const d = await newPage('denied', 'denied'); await d.page.evaluate(() => { window.__denied = false; });
    await d.page.click('#btn-desktop-alerts-toggle'); await d.page.waitForSelector('.nhmp-pm-btn.primary'); await d.page.evaluate(() => { window.__denied = true; });
    await d.page.click('.nhmp-pm-btn.primary'); await d.page.waitForFunction(() => /blocked in your browser/i.test((document.querySelector('.nhmp-pm-title') || {}).textContent || ''), null, { timeout: 6000 }).catch(() => {});
    ok(/blocked in your browser/i.test(await d.page.textContent('.nhmp-pm-title')), 'blocked modal with unblock steps'); await d.page.screenshot({ path: path.join(OUT, 'enroll-modal-blocked.png') });
    ok(d.dialogs.length === 0, 'no native alert() dialogs fired'); }
  console.log('Test 6: Escape closes the modal; keyboard focus is inside the modal');
  { const e = await newPage('granted', 'ok'); await e.page.click('#btn-desktop-alerts-toggle'); await e.page.waitForSelector('.nhmp-pm-card');
    ok(await e.page.evaluate(() => !!document.activeElement.closest('.nhmp-pm-card')), 'focus moved into modal'); await e.page.keyboard.press('Escape');
    await e.page.waitForSelector('.nhmp-pm-overlay', { state: 'detached', timeout: 3000 }).catch(() => {}); ok(await e.page.locator('.nhmp-pm-overlay').count() === 0, 'Escape closes modal'); }
  await browser.close(); srv.close(); console.log(`\nRESULT: ${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
