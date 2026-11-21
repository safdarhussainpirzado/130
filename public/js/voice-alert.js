/**
 * NHMP 130 CRM - 24/7 Voice & Acoustic Alarm Alerts (VOICE-1).
 *
 * Provides:
 * 1. WebAudio synthesized dual-tone high-volume emergency siren.
 * 2. SpeechSynthesis spoken dispatch voice alert (English & Urdu context).
 * 3. Continuous alert loop (up to 30s) until acknowledged by clicking acknowledgment banner.
 * 4. Zero native alerts/confirms: uses pure DOM notifications with accessible Dismiss actions.
 */
(function (window) {
    'use strict';

    var audioCtx = null;
    var alarmInterval = null;
    var alarmTimeout = null;
    var currentSpeech = null;
    var bannerEl = null;

    var PREF_KEY_VOICE = 'nhmp_voice_enabled'; // shared with the header speaker button
    var PREF_KEY_ALARM = 'nhmp_alarm_alerts_enabled';
    var PREF_KEY_MIN_PRIORITY = 'nhmp_min_alert_priority'; // 1 (P1 only) to 5 (All)

    function getAudioContext() {
        if (!audioCtx) {
            var AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume().catch(function () {});
        }
        return audioCtx;
    }

    function isVoiceEnabled() {
        var val = localStorage.getItem(PREF_KEY_VOICE);
        return val === null ? true : val === 'true';
    }

    function isAlarmEnabled() {
        var val = localStorage.getItem(PREF_KEY_ALARM);
        return val === null ? true : val === 'true';
    }

    function getMinPriorityThreshold() {
        return 5; // Priority filtering removed (owner decision): every alert sounds
    }

    function playTone(freq1, freq2, durationSec) {
        try {
            var ctx = getAudioContext();
            if (!ctx) return;

            var osc = ctx.createOscillator();
            var gain = ctx.createGain();

            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(freq1, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(freq2, ctx.currentTime + durationSec);

            gain.gain.setValueAtTime(0.35, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + durationSec);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start();
            osc.stop(ctx.currentTime + durationSec);
        } catch (e) {
            console.warn('[VoiceAlert] WebAudio error:', e);
        }
    }

    function playSirenCycle() {
        playTone(880, 440, 0.45);
        setTimeout(function () {
            playTone(660, 880, 0.45);
        }, 500);
    }

    function showAcknowledgeBanner(title, message) {
        if (bannerEl) {
            bannerEl.remove();
            bannerEl = null;
        }

        bannerEl = document.createElement('div');
        bannerEl.id = 'nhmp-voice-alarm-banner';
        bannerEl.style.cssText = [
            'position: fixed',
            'top: 1rem',
            'right: 1rem',
            'z-index: 999999',
            'background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%)',
            'color: #ffffff',
            'border-radius: 1rem',
            'padding: 1.25rem 1.5rem',
            'box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2)',
            'max-width: 26rem',
            'width: calc(100vw - 2rem)',
            'display: flex',
            'align-items: center',
            'gap: 1rem',
            'animation: nhmp-pulse-alarm 1.5s infinite'
        ].join(';');

        var contentDiv = document.createElement('div');
        contentDiv.style.flex = '1';

        var titleDiv = document.createElement('div');
        titleDiv.style.fontWeight = '900';
        titleDiv.style.fontSize = '0.95rem';
        titleDiv.style.letterSpacing = '0.025em';
        titleDiv.style.textTransform = 'uppercase';
        titleDiv.innerText = title || 'EMERGENCY DISPATCH ALERT';

        var descDiv = document.createElement('div');
        descDiv.style.fontSize = '0.8rem';
        descDiv.style.marginTop = '0.25rem';
        descDiv.style.opacity = '0.95';
        descDiv.innerText = message || 'Immediate attention required.';

        contentDiv.appendChild(titleDiv);
        contentDiv.appendChild(descDiv);

        var ackBtn = document.createElement('button');
        ackBtn.type = 'button';
        ackBtn.style.cssText = [
            'background: #ffffff',
            'color: #991b1b',
            'font-weight: 800',
            'font-size: 0.75rem',
            'text-transform: uppercase',
            'padding: 0.5rem 1rem',
            'border-radius: 0.75rem',
            'border: none',
            'cursor: pointer',
            'box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            'white-space: nowrap'
        ].join(';');
        ackBtn.innerText = 'Acknowledge';
        ackBtn.onclick = function () {
            window.NHMPVoiceAlert.stopAlarm();
        };

        bannerEl.appendChild(contentDiv);
        bannerEl.appendChild(ackBtn);
        document.body.appendChild(bannerEl);
    }

    window.NHMPVoiceAlert = {
        unlockAudio: function () {
            var ctx = getAudioContext();
            if (ctx && ctx.state === 'suspended') {
                ctx.resume();
            }
            if ('speechSynthesis' in window) {
                window.speechSynthesis.resume();
            }
        },

        triggerAlert: function (opts) {
            opts = opts || {};
            // De-duplicate: the same alert (id) must sound once per browser even with many tabs / poll + push.
            var id = opts.id || opts.call_id || null;
            if (id) {
                try {
                    var k = 'nhmp_va_seen_' + id, now = Date.now(), prev = parseInt(localStorage.getItem(k) || '0', 10);
                    if (prev && now - prev < 120000) { return; }
                    localStorage.setItem(k, String(now));
                } catch (e) {}
            }
            var priority = opts.priority ? parseInt(opts.priority, 10) : 3;
            var minP = getMinPriorityThreshold();

            // Mute if priority is below configured user threshold (e.g. minP=2 ignores P3..P5)
            if (priority > minP) {
                return;
            }

            var title = opts.title || 'Incoming Emergency Request';
            var speakText = opts.speak_text || opts.message || 'Priority alert pending assistance';

            this.stopAlarm();
            showAcknowledgeBanner(title, speakText);

            // 1. Play Acoustic Siren
            if (isAlarmEnabled()) {
                playSirenCycle();
                alarmInterval = setInterval(playSirenCycle, 1100);
            }

            // 2. Play Spoken Speech Alert
            if (isVoiceEnabled() && 'speechSynthesis' in window) {
                try {
                    window.speechSynthesis.cancel();
                    var utterance = new SpeechSynthesisUtterance(speakText);
                    utterance.rate = 0.95;
                    utterance.pitch = 1.0;
                    utterance.volume = 1.0;
                    utterance.lang = 'en-US';

                    var voices = window.speechSynthesis.getVoices();
                    for (var i = 0; i < voices.length; i++) {
                        if (voices[i].lang.indexOf('en') === 0 && voices[i].name.indexOf('Natural') !== -1) {
                            utterance.voice = voices[i];
                            break;
                        }
                    }

                    currentSpeech = utterance;
                    window.speechSynthesis.speak(utterance);
                } catch (e) {
                    console.warn('[VoiceAlert] Speech error:', e);
                }
            }

            // Automatic silence timeout after 30 seconds
            alarmTimeout = setTimeout(function () {
                window.NHMPVoiceAlert.stopAlarm();
            }, 30000);
        },

        stopAlarm: function () {
            if (alarmInterval) {
                clearInterval(alarmInterval);
                alarmInterval = null;
            }
            if (alarmTimeout) {
                clearTimeout(alarmTimeout);
                alarmTimeout = null;
            }
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
            if (bannerEl) {
                bannerEl.remove();
                bannerEl = null;
            }
        },

        setPreferences: function (voice, alarm, minPriority) {
            if (typeof voice === 'boolean') localStorage.setItem(PREF_KEY_VOICE, voice ? 'true' : 'false');
            if (typeof alarm === 'boolean') localStorage.setItem(PREF_KEY_ALARM, alarm ? 'true' : 'false');
        },

        getPreferences: function () {
            return {
                voice: isVoiceEnabled(),
                alarm: isAlarmEnabled(),
                minPriority: getMinPriorityThreshold()
            };
        }
    };


    // ---- Settings modal (styled, no native dialogs) ----
    function openSettings() {
        if (document.getElementById('nhmp-va-settings')) return;
        var p = window.NHMPVoiceAlert.getPreferences();
        var wrap = document.createElement('div');
        wrap.id = 'nhmp-va-settings';
        wrap.style.cssText = 'position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.35)';
        wrap.innerHTML = '<div style="background:#fff;border-radius:22px;width:min(420px,92vw);padding:22px;box-shadow:0 30px 70px rgba(15,23,42,.35);font-family:inherit">'
          + '<div style="font-weight:900;font-size:15px;color:#0f172a;margin-bottom:4px">Alert sound settings</div>'
          + '<div style="font-size:12px;color:#64748b;margin-bottom:14px">Applies to this browser. Alerts run 24 hours; nothing here depends on duty hours.</div>'
          + '<label style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;font-weight:700;font-size:13px;color:#334155">Spoken voice<input id="va-voice" type="checkbox" style="width:20px;height:20px"></label>'
          + '<label style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;font-weight:700;font-size:13px;color:#334155">Alarm siren<input id="va-alarm" type="checkbox" style="width:20px;height:20px"></label>'
          + '<div style="display:flex;gap:10px;margin-top:14px"><button id="va-test" type="button" style="flex:1;height:42px;border-radius:12px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:800;font-size:12px;cursor:pointer">Test alert</button><button id="va-save" type="button" style="flex:1;height:42px;border-radius:12px;border:0;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;font-weight:800;font-size:12px;cursor:pointer">Save</button></div></div>';
        document.body.appendChild(wrap);
        wrap.querySelector('#va-voice').checked = p.voice;
        wrap.querySelector('#va-alarm').checked = p.alarm;
        function save() { window.NHMPVoiceAlert.setPreferences(wrap.querySelector('#va-voice').checked, wrap.querySelector('#va-alarm').checked); }
        wrap.querySelector('#va-test').onclick = function () { save(); window.NHMPVoiceAlert.unlockAudio(); window.NHMPVoiceAlert.triggerAlert({ title: 'Test alert', speak_text: 'This is a test of the N H M P one thirty alert system.', priority: 1 }); };
        wrap.querySelector('#va-save').onclick = function () { save(); window.NHMPVoiceAlert.stopAlarm(); wrap.remove(); if (window.showSuccess) window.showSuccess('Alert settings saved.', 'Alert Settings'); };
        wrap.addEventListener('click', function (e) { if (e.target === wrap) { wrap.remove(); } });
    }
    window.NHMPVoiceAlert.openSettings = openSettings;

    // ---- "Enable sound" pill: browsers block audio until the page has had one click/keypress ----
    function showEnablePill() {
        if (document.getElementById('nhmp-va-pill')) return;
        var ua = navigator.userActivation;
        if (ua && ua.hasBeenActive) return;
        if (!isVoiceEnabled() && !isAlarmEnabled()) return;
        var b = document.createElement('button');
        b.id = 'nhmp-va-pill'; b.type = 'button';
        b.style.cssText = 'position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:2147482000;border:0;cursor:pointer;border-radius:999px;padding:11px 18px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;font-weight:800;font-size:12px;letter-spacing:.04em;box-shadow:0 14px 30px rgba(185,28,28,.45)';
        b.innerHTML = '&#128266; Click once to enable voice &amp; alarm alerts';
        function go() { window.NHMPVoiceAlert.unlockAudio(); try { if ('speechSynthesis' in window && isVoiceEnabled()) { var u = new SpeechSynthesisUtterance('Voice alerts enabled.'); u.volume = 1; window.speechSynthesis.speak(u); } } catch (e) {} b.remove(); }
        b.onclick = go;
        ['click', 'keydown', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, function () { var x = document.getElementById('nhmp-va-pill'); if (x) x.remove(); }, { once: true, passive: true }); });
        document.body.appendChild(b);
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { setTimeout(showEnablePill, 1200); }); } else { setTimeout(showEnablePill, 1200); }

    // Unlock audio context on first user click anywhere in the window
    document.addEventListener('click', function () {
        window.NHMPVoiceAlert.unlockAudio();
    }, { once: false, passive: true });

    // Listen for Service Worker postMessage events when tab is open in background
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'TRIGGER_VOICE_ALARM') {
                window.NHMPVoiceAlert.triggerAlert(event.data.payload);
            }
        });
    }

})(window);
