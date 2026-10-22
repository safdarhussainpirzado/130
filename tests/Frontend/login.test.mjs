import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../../resources/js/login.js', import.meta.url), 'utf8');

class Element extends EventTarget {
    constructor(properties = {}) {
        super();
        this.attributes = new Map();
        this.children = new Map();
        this.dataset = {};
        this.disabled = false;
        this.hidden = false;
        this.textContent = '';
        Object.assign(this, properties);
    }

    setAttribute(name, value) { this.attributes.set(name, String(value)); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    removeAttribute(name) { this.attributes.delete(name); }
    querySelector(selector) { return this.children.get(selector) ?? null; }
    click() { if (!this.disabled && !this.hidden) this.dispatchEvent(new Event('click')); }
}

function createBrowser({ reducedMotion = false, paused = false, storageUnavailable = false, hasErrors = false } = {}) {
    const elements = new Map([
        ['nhmp-login', new Element()],
        ['loginForm', new Element()],
        ['password', new Element({ type: 'password' })],
        ['togglePassword', new Element({ hidden: true })],
        ['loginButton', new Element()],
        ['btnText', new Element({ textContent: 'Sign in to your workspace' })],
        ['loadingSpinner', new Element({ hidden: true })],
        ['loginProgress', new Element()],
        ['motorway-story', new Element()],
        ['motionToggle', new Element({ hidden: true })],
    ]);
    const document = new EventTarget();
    document.hidden = false;
    document.activeElement = null;
    document.getElementById = (id) => elements.get(id) ?? null;
    if (hasErrors) {
        const errors = new Element();
        errors.focus = () => { document.activeElement = errors; };
        elements.set('login-errors', errors);
    }
    const motionLabel = new Element({ textContent: 'Pause motion' });
    elements.get('motionToggle').children.set('span', motionLabel);
    elements.get('togglePassword').setAttribute('aria-label', 'Show password');
    elements.get('togglePassword').setAttribute('aria-pressed', 'false');
    const arrow = new Element();
    elements.get('loginButton').children.set('.submit-arrow', arrow);

    const media = new EventTarget();
    media.matches = reducedMotion;
    const window = new EventTarget();
    window.matchMedia = () => media;
    const stored = new Map([['nhmp_session_logout', 'true']]);
    if (paused) stored.set('nhmp_login_motion', 'paused');
    const localStorage = Object.fromEntries(['getItem', 'setItem', 'removeItem'].map((method) => [method, (key, value) => {
        if (storageUnavailable) throw new Error('Storage is unavailable');
        if (method === 'getItem') return stored.get(key) ?? null;
        if (method === 'setItem') stored.set(key, String(value));
        if (method === 'removeItem') stored.delete(key);
    }]));

    vm.runInNewContext(source, { document, window, localStorage }, { filename: 'resources/js/login.js' });
    return {
        elements, document, window, stored, motionLabel, arrow,
        setReducedMotion(value) { media.matches = value; media.dispatchEvent(new Event('change')); },
        setHidden(value) { document.hidden = value; document.dispatchEvent(new Event('visibilitychange')); },
    };
}

test('enhancements become available only after initialization', () => {
    const { elements, stored } = createBrowser();
    assert.equal(elements.get('togglePassword').hidden, false);
    assert.equal(elements.get('motionToggle').hidden, false);
    assert.equal(elements.get('motorway-story').dataset.motionReady, 'true');
    assert.equal(elements.get('motorway-story').dataset.paused, 'false');
    assert.equal(stored.has('nhmp_session_logout'), false);
});

test('a reduced-motion preference initially pauses the scene and prevents playing it', () => {
    const { elements, motionLabel, stored } = createBrowser({ reducedMotion: true });
    const toggle = elements.get('motionToggle');
    assert.equal(elements.get('motorway-story').dataset.paused, 'true');
    assert.equal(toggle.disabled, true);
    assert.equal(toggle.getAttribute('aria-pressed'), 'true');
    assert.equal(motionLabel.textContent, 'Reduced motion on');
    toggle.click();
    assert.equal(elements.get('motorway-story').dataset.paused, 'true');
    assert.equal(stored.has('nhmp_login_motion'), false);
});

test('changing the system motion preference updates the scene without reloading', () => {
    const { elements, motionLabel, setReducedMotion } = createBrowser();
    const toggle = elements.get('motionToggle');
    setReducedMotion(true);
    assert.equal(elements.get('motorway-story').dataset.paused, 'true');
    assert.equal(toggle.disabled, true);
    assert.equal(motionLabel.textContent, 'Reduced motion on');
    setReducedMotion(false);
    assert.equal(elements.get('motorway-story').dataset.paused, 'false');
    assert.equal(toggle.disabled, false);
    assert.equal(toggle.getAttribute('aria-pressed'), 'false');
    assert.equal(motionLabel.textContent, 'Pause motion');
});

test('manual pause persists and survives system-preference and visibility changes', () => {
    const { elements, motionLabel, stored, setHidden, setReducedMotion } = createBrowser();
    const toggle = elements.get('motionToggle');
    toggle.click();
    assert.equal(stored.get('nhmp_login_motion'), 'paused');
    assert.equal(toggle.getAttribute('aria-pressed'), 'true');
    assert.equal(motionLabel.textContent, 'Play motion');
    setReducedMotion(true);
    setReducedMotion(false);
    setHidden(true);
    setHidden(false);
    assert.equal(elements.get('motorway-story').dataset.paused, 'true');
    toggle.click();
    assert.equal(stored.get('nhmp_login_motion'), 'playing');
    assert.equal(elements.get('motorway-story').dataset.paused, 'false');
    assert.equal(motionLabel.textContent, 'Pause motion');
});

test('a saved pause is restored, and an otherwise running scene pauses in a hidden tab', () => {
    const saved = createBrowser({ paused: true });
    assert.equal(saved.elements.get('motorway-story').dataset.paused, 'true');
    assert.equal(saved.motionLabel.textContent, 'Play motion');
    const running = createBrowser();
    running.setHidden(true);
    assert.equal(running.elements.get('motorway-story').dataset.paused, 'true');
    running.setHidden(false);
    assert.equal(running.elements.get('motorway-story').dataset.paused, 'false');
});

test('unavailable storage leaves password and motion controls usable', () => {
    const { elements } = createBrowser({ storageUnavailable: true });
    elements.get('motionToggle').click();
    assert.equal(elements.get('motorway-story').dataset.paused, 'true');
    elements.get('motionToggle').click();
    assert.equal(elements.get('motorway-story').dataset.paused, 'false');
    elements.get('togglePassword').click();
    assert.equal(elements.get('password').type, 'text');
});

test('password visibility follows its accessible name and pressed state', () => {
    const { elements } = createBrowser();
    const toggle = elements.get('togglePassword');
    toggle.click();
    assert.equal(elements.get('password').type, 'text');
    assert.equal(toggle.getAttribute('aria-label'), 'Hide password');
    assert.equal(toggle.getAttribute('aria-pressed'), 'true');
    toggle.click();
    assert.equal(elements.get('password').type, 'password');
    assert.equal(toggle.getAttribute('aria-label'), 'Show password');
    assert.equal(toggle.getAttribute('aria-pressed'), 'false');
});

test('submission retains native navigation and announces progress; pageshow restores the button', () => {
    const { elements, window, arrow } = createBrowser();
    const button = elements.get('loginButton');
    const allowed = elements.get('loginForm').dispatchEvent(new Event('submit', { cancelable: true }));
    assert.equal(allowed, true);
    assert.equal(button.disabled, true);
    assert.equal(button.getAttribute('aria-busy'), 'true');
    assert.equal(elements.get('btnText').textContent, 'Signing you in…');
    assert.equal(elements.get('loadingSpinner').hidden, false);
    assert.equal(arrow.getAttribute('visibility'), 'hidden');
    assert.equal(elements.get('loginProgress').textContent, 'Signing you in. Please wait.');
    window.dispatchEvent(new Event('pageshow'));
    assert.equal(button.disabled, false);
    assert.equal(button.getAttribute('aria-busy'), null);
    assert.equal(elements.get('btnText').textContent, 'Sign in to your workspace');
    assert.equal(elements.get('loadingSpinner').hidden, true);
    assert.equal(arrow.getAttribute('visibility'), 'visible');
    assert.equal(elements.get('loginProgress').textContent, '');
});

test('server-rendered errors receive focus when the login page initializes', () => {
    const { document, elements } = createBrowser({ hasErrors: true });
    assert.equal(document.activeElement, elements.get('login-errors'));
});

test('loading the script outside the login page has no side effects', () => {
    vm.runInNewContext(source, { document: { getElementById: () => null } });
});
