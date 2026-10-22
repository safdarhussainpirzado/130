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
    const motionLabel = motionToggle.querySelector('span');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let motionPaused = false;

    try {
        localStorage.removeItem('nhmp_session_logout');
        motionPaused = localStorage.getItem('nhmp_login_motion') === 'paused';
    } catch { /* Login remains available without browser storage. */ }

    visibilityToggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        visibilityToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        visibilityToggle.setAttribute('aria-pressed', String(visible));
    });

    const resetSubmit = () => {
        loginButton.disabled = false;
        loginButton.removeAttribute('aria-busy');
        buttonText.textContent = 'Sign in to your workspace';
        spinner.hidden = true;
        loginButton.querySelector('.submit-arrow').setAttribute('visibility', 'visible');
        progress.textContent = '';
    };

    form.addEventListener('submit', () => {
        loginButton.disabled = true;
        loginButton.setAttribute('aria-busy', 'true');
        buttonText.textContent = 'Signing you in…';
        spinner.hidden = false;
        loginButton.querySelector('.submit-arrow').setAttribute('visibility', 'hidden');
        progress.textContent = 'Signing you in. Please wait.';
    });
    window.addEventListener('pageshow', resetSubmit);

    const updateMotion = () => {
        const paused = reducedMotion.matches || motionPaused;
        scene.dataset.paused = String(paused || document.hidden);
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
    visibilityToggle.hidden = false;
    motionToggle.hidden = false;
    scene.dataset.motionReady = 'true';
    document.getElementById('login-errors')?.focus();
}
