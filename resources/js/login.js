const page = document.getElementById('nhmp-login');

if (page) {
    const form = document.getElementById('loginForm');
    const password = document.getElementById('password');
    const visibilityToggle = document.getElementById('togglePassword');
    const loginButton = document.getElementById('loginButton');
    const buttonText = document.getElementById('btnText');
    const spinner = document.getElementById('loadingSpinner');
    const progress = document.getElementById('loginProgress');
    const scene = document.getElementById('motorway-story');
    const motionToggle = document.getElementById('motionToggle');
    const motionLabel = motionToggle ? motionToggle.querySelector('span') : null;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let motionPaused = false;

    try {
        localStorage.removeItem('nhmp_session_logout');
        motionPaused = localStorage.getItem('nhmp_login_motion') === 'paused';
    } catch { /* Login remains available without browser storage. */ }

    if (visibilityToggle && password) {
        visibilityToggle.addEventListener('click', () => {
            const visible = password.type === 'password';
            password.type = visible ? 'text' : 'password';
            visibilityToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            visibilityToggle.setAttribute('aria-pressed', String(visible));
        });
        visibilityToggle.hidden = false;
    }

    const resetSubmit = () => {
        if (loginButton) {
            loginButton.disabled = false;
            loginButton.removeAttribute('aria-busy');
            const arrow = loginButton.querySelector('.submit-arrow');
            if (arrow) arrow.setAttribute('visibility', 'visible');
        }
        if (buttonText) buttonText.textContent = 'Sign in to your workspace';
        if (spinner) spinner.hidden = true;
        if (progress) progress.textContent = '';
    };

    if (form && loginButton) {
        form.addEventListener('submit', () => {
            loginButton.disabled = true;
            loginButton.setAttribute('aria-busy', 'true');
            if (buttonText) buttonText.textContent = 'Signing you in…';
            if (spinner) spinner.hidden = false;
            const arrow = loginButton.querySelector('.submit-arrow');
            if (arrow) arrow.setAttribute('visibility', 'hidden');
            if (progress) progress.textContent = 'Signing you in. Please wait.';
        });
        window.addEventListener('pageshow', resetSubmit);
    }

    if (scene && motionToggle && motionLabel) {
        const updateMotion = () => {
            const paused = reducedMotion.matches || motionPaused;
            scene.dataset.paused = String(paused || document.hidden);
            page.dataset.paused = scene.dataset.paused;
            motionToggle.disabled = reducedMotion.matches;
            motionToggle.setAttribute('aria-pressed', String(paused));
            motionLabel.textContent = reducedMotion.matches ? 'Reduced motion on' : (motionPaused ? 'Play motion' : 'Pause motion');
        };
        motionToggle.addEventListener('click', () => {
            motionPaused = !motionPaused;
            try { localStorage.setItem('nhmp_login_motion', motionPaused ? 'paused' : 'playing'); } catch { /* Optional preference. */ }
            updateMotion();
        });
        reducedMotion.addEventListener('change', updateMotion);
        document.addEventListener('visibilitychange', updateMotion);
        updateMotion();
        motionToggle.hidden = false;
        scene.dataset.motionReady = 'true';
    }

    page.dataset.motionReady = 'true';
    document.getElementById('login-errors')?.focus();
}

// Scenic Branding Card Slider Engine (Crossfade + Interactive Dots + Pause-on-Hover)
const scenicSlider = document.getElementById('nhmpScenicSlider');
if (scenicSlider) {
    const slides = scenicSlider.querySelectorAll('.scenic-slide');
    const dots = scenicSlider.querySelectorAll('.slider-dot');
    const locSub = document.getElementById('sliderLocationSub');
    const pillTag = document.getElementById('sliderPillTag');
    let currentIndex = 0;
    let timer = null;
    const delay = 4800; // 4.8s per slide

    const showSlide = (index) => {
        if (!slides.length) return;
        if (index < 0) index = slides.length - 1;
        if (index >= slides.length) index = 0;
        currentIndex = index;

        slides.forEach((s, i) => {
            if (i === currentIndex) {
                s.classList.add('active');
                const loc = s.getAttribute('data-location');
                const tag = s.getAttribute('data-tagline');
                if (locSub && loc) locSub.textContent = loc;
                if (pillTag && tag) pillTag.textContent = tag;
            } else {
                s.classList.remove('active');
            }
        });

        dots.forEach((d, i) => {
            if (i === currentIndex) {
                d.classList.add('active');
            } else {
                d.classList.remove('active');
            }
        });
    };

    const stopTimer = () => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    };

    const startTimer = () => {
        stopTimer();
        timer = setInterval(() => {
            showSlide(currentIndex + 1);
        }, delay);
    };

    window.goToSlide = (i) => {
        showSlide(i);
        startTimer();
    };

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            const idx = parseInt(dot.getAttribute('data-dot-index'), 10);
            if (!isNaN(idx)) {
                showSlide(idx);
                startTimer();
            }
        });
        dot.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const idx = parseInt(dot.getAttribute('data-dot-index'), 10);
                if (!isNaN(idx)) {
                    showSlide(idx);
                    startTimer();
                }
            }
        });
    });

    scenicSlider.addEventListener('mouseenter', stopTimer);
    scenicSlider.addEventListener('mouseleave', startTimer);
    startTimer();
}
