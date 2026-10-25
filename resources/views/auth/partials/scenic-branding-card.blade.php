@php
    $slides = [
        [
            'image' => asset('images/login/slider-6.webp'),
            'location' => 'Petaro · Indus Highway N-55',
            'tagline' => 'Riverine Patrol · Jamshoro Sindh',
        ],
        [
            'image' => asset('images/login/slider-7.webp'),
            'location' => 'Lyari Expressway · Karachi',
            'tagline' => 'Urban Elevated Viaduct · Sindh',
        ],
        [
            'image' => asset('images/login/slider-8.webp'),
            'location' => 'Sukkur–Rohri · M-5 Motorway',
            'tagline' => 'Indus Basin Command · Sindh',
        ],
        [
            'image' => asset('images/login/slider-9.webp'),
            'location' => 'Bholari–Hyderabad · M-9 Motorway',
            'tagline' => 'Airbase Sector Patrol · Sindh',
        ],
        [
            'image' => asset('images/login/slider-10.webp'),
            'location' => 'Khairpur Mirs · National Highway N-5',
            'tagline' => 'Date Palm Oasis Patrol · Sindh',
        ],
        [
            'image' => asset('images/login/slider-1.webp'),
            'location' => 'Hazara Motorway · E-35',
            'tagline' => 'Viaduct Patrol · KPK',
        ],
        [
            'image' => asset('images/login/slider-2.webp'),
            'location' => 'M-2 Salt Range · Kallar Kahar',
            'tagline' => 'High-Speed Interceptor · Punjab',
        ],
        [
            'image' => asset('images/login/slider-3.webp'),
            'location' => 'Karakoram Highway · N-35',
            'tagline' => 'Passu Cones · Gilgit Baltistan',
        ],
        [
            'image' => asset('images/login/slider-4.webp'),
            'location' => 'Makran Coastal Highway · N-10',
            'tagline' => 'Coastal Patrol · Balochistan',
        ],
        [
            'image' => asset('images/login/slider-5.webp'),
            'location' => 'Swat Expressway · M-16',
            'tagline' => 'River Valley Command · Malakand',
        ],
    ];
@endphp

<div class="scenic-card" id="nhmpScenicSlider" aria-hidden="true">
    <!-- Image Slider Wrapper -->
    <div class="scenic-slider-wrapper">
        @foreach ($slides as $index => $slide)
            <div class="scenic-slide {{ $index === 0 ? 'active' : '' }}" 
                 data-slide-index="{{ $index }}"
                 data-location="{{ $slide['location'] }}"
                 data-tagline="{{ $slide['tagline'] }}">
                <img src="{{ $slide['image'] }}" 
                     alt="NHMP Tiger Patrol — {{ $slide['location'] }}" 
                     class="scenic-bg-img"
                     loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                     fetchpriority="{{ $index === 0 ? 'high' : 'low' }}"
                     decoding="async">
            </div>
        @endforeach
    </div>

    <!-- Subtle Ambient Dark Gradient Vignette for Readability -->
    <div class="scenic-vignette"></div>

    <!-- Top Floating Header Badge with Active Location Tracker -->
    <div class="scenic-top-bar">
        <div class="scenic-brand-badge">
            <img src="{{ asset('images/login/shield-logo-web.webp') }}" alt="" class="scenic-shield-icon">
            <div>
                <span class="scenic-brand-title">NHMP <strong>130</strong></span>
                <span class="scenic-brand-sub" id="sliderLocationSub">{{ $slides[0]['location'] }}</span>
            </div>
        </div>
        <div class="scenic-pill-tag">
            <span id="sliderPillTag">{{ $slides[0]['tagline'] }}</span>
        </div>
    </div>

    <!-- Bottom Floating Showcase Bar -->
    <div class="scenic-bottom-panel">
        <div class="scenic-features-row">
            <div class="scenic-feat-item">
                <svg viewBox="0 0 20 20" fill="none"><path d="M10 2l6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2z" stroke="#38bdf8" stroke-width="1.6"/><path d="M7 9l2 2 4-4" stroke="#38bdf8" stroke-width="1.6" stroke-linecap="round"/></svg>
                <span>Real-Time Alerts</span>
            </div>
            <div class="scenic-feat-item">
                <svg viewBox="0 0 20 20" fill="none"><path d="M5 2l3 4-2 2c1 3 3 5 6 6l2-2 4 3-1 3C9 19 1 11 2 3l3-1z" stroke="#10b981" stroke-width="1.6"/></svg>
                <span>Quick Assistance</span>
            </div>
            <div class="scenic-feat-item">
                <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#facc15" stroke-width="1.6"/><path d="M10 6v4l3 3" stroke="#facc15" stroke-width="1.6" stroke-linecap="round"/></svg>
                <span>Safer Journeys</span>
            </div>
        </div>

        <div class="scenic-footer-caption">
            <!-- 5-Slide Interactive Dot Indicators -->
            <div class="slider-dots" id="sliderDots" role="tablist">
                @foreach ($slides as $index => $slide)
                    <span class="slider-dot {{ $index === 0 ? 'active' : '' }}" 
                          data-dot-index="{{ $index }}"
                          title="{{ $slide['location'] }}"
                          onclick="goToSlide({{ $index }})"></span>
                @endforeach
            </div>
            <span class="scenic-helpline">Helpline: <strong>130</strong></span>
        </div>
    </div>

    <!-- Vanilla Javascript Slider Engine (Crossfade + Touch/Click + Pause-on-Hover) -->
    <script>
        (function() {
            var slider = document.getElementById('nhmpScenicSlider');
            if (!slider) return;

            var slides = slider.querySelectorAll('.scenic-slide');
            var dots = slider.querySelectorAll('.slider-dot');
            var locSub = document.getElementById('sliderLocationSub');
            var pillTag = document.getElementById('sliderPillTag');
            var currentIndex = 0;
            var timer = null;
            var delay = 4800; // 4.8s per slide

            function showSlide(index) {
                if (index < 0) index = slides.length - 1;
                if (index >= slides.length) index = 0;
                currentIndex = index;

                slides.forEach(function(s, i) {
                    if (i === currentIndex) {
                        s.classList.add('active');
                        var loc = s.getAttribute('data-location');
                        var tag = s.getAttribute('data-tagline');
                        if (locSub && loc) locSub.textContent = loc;
                        if (pillTag && tag) pillTag.textContent = tag;
                    } else {
                        s.classList.remove('active');
                    }
                });

                dots.forEach(function(d, i) {
                    if (i === currentIndex) {
                        d.classList.add('active');
                    } else {
                        d.classList.remove('active');
                    }
                });
            }

            function startTimer() {
                stopTimer();
                timer = setInterval(function() {
                    showSlide(currentIndex + 1);
                }, delay);
            }

            function stopTimer() {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            window.goToSlide = function(i) {
                showSlide(i);
                startTimer();
            };

            slider.addEventListener('mouseenter', stopTimer);
            slider.addEventListener('mouseleave', startTimer);
            startTimer();
        })();
    </script>
</div>
