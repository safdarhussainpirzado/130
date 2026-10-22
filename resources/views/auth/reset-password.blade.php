<x-guest-layout>
  <style>
    /* bento elevation */
    .bento-card {
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    
    /* micro-interactions with ripple effect */
    .btn-ripple {
      position: relative;
      overflow: hidden;
      transform: translate3d(0,0,0);
    }
    .btn-ripple:after {
      content: "";
      display: block;
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      pointer-events: none;
      background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
      background-repeat: no-repeat;
      background-position: 50%;
      transform: scale(10,10);
      opacity: 0;
      transition: transform .3s, opacity .5s;
    }
    .btn-ripple:active:after {
      transform: scale(0,0);
      opacity: 0.3;
      transition: 0s;
    }
    
    /* page entrance animation */
    .fade-slide-in {
      animation: fadeSlide 0.6s cubic-bezier(0.2, 0.9, 0.4, 1) forwards;
    }
    @keyframes fadeSlide {
      0% { opacity: 0; transform: translateY(16px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    
    /* glow effects for glass panels */
    .glow-shadow {
      box-shadow: 0 20px 40px -12px rgba(37, 99, 235, 0.15), 0 4px 18px -6px rgba(0, 0, 0, 0.05);
    }
    .glow-shadow-lg {
      box-shadow: 0 25px 50px -12px rgba(37, 99, 235, 0.25);
    }
    
    .input-floating-wrapper input:focus::placeholder {
      opacity: 0.5;
    }
  </style>

  <!-- MAIN BENTO GRID CONTAINER -->
  <div class="w-full max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row gap-6 lg:gap-8 items-stretch">

      <!-- LEFT BRANDING PANEL -->
      <div class="w-full md:w-[40%] relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-600 via-indigo-600 to-indigo-800 glow-shadow-lg p-8 flex flex-col justify-between bento-card border border-white/30 fade-slide-in" style="animation-delay: 0s;">
        <!-- abstract animated shapes -->
        <div class="absolute -top-20 -right-20 w-64 h-64 bg-blue-400/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-10 left-0 w-40 h-40 bg-indigo-300/20 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 right-10 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div>
        
        <div class="relative z-10">
          <!-- NHMP Logo / emblem -->
          <div class="flex items-center gap-3 mb-8">
            <div class="w-14 h-14 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-xl border border-white/40">
              <i class="fa-solid fa-shield-halved text-white text-2xl"></i>
            </div>
            <span class="text-white font-black tracking-tight text-xl">NHMP 130</span>
          </div>
          <h1 class="text-4xl md:text-5xl font-extrabold text-white leading-tight tracking-tight drop-shadow-lg">
            Set New<br>Password
          </h1>
          <p class="text-indigo-100 text-lg mt-4 font-medium max-w-xs">
            Mandatory Security Policy<br>Enforce compliant credentials.
          </p>
        </div>

        <div class="relative z-10 mt-10">
          <div class="flex items-center gap-3 text-white/90 text-sm font-medium">
            <div class="w-5 h-5 rounded-full bg-emerald-400/30 flex items-center justify-center">
              <i class="fa-solid fa-check text-emerald-200 text-xs"></i>
            </div>
            <span>Minimum 12 characters length</span>
          </div>
          <div class="flex items-center gap-3 text-white/90 text-sm font-medium mt-3">
            <div class="w-5 h-5 rounded-full bg-emerald-400/30 flex items-center justify-center">
              <i class="fa-solid fa-check text-emerald-200 text-xs"></i>
            </div>
            <span>Uppercase, lowercase & numeric digits</span>
          </div>
          <div class="flex items-center gap-3 text-white/90 text-sm font-medium mt-3">
            <div class="w-5 h-5 rounded-full bg-emerald-400/30 flex items-center justify-center">
              <i class="fa-solid fa-check text-emerald-200 text-xs"></i>
            </div>
            <span>Special symbols required</span>
          </div>
          <!-- subtle animated line -->
          <div class="mt-8 h-px w-20 bg-gradient-to-r from-white/50 to-transparent"></div>
          <p class="text-white/70 text-xs mt-4 uppercase tracking-widest font-bold">NHMP · since 2025</p>
        </div>
      </div>

      <!-- RIGHT FORM CARD -->
      <div class="w-full md:w-[60%] bg-white/75 backdrop-blur-xl rounded-3xl glow-shadow border border-white/60 p-6 md:p-8 lg:p-10 bento-card fade-slide-in" style="animation-delay: 0.1s;">
        
        <!-- Header -->
        <div class="mb-6 border-l-4 border-indigo-500 pl-4">
          <h2 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Configure New Password</h2>
          <p class="text-slate-500 text-sm font-medium mt-1">
            Choose a strong, complex password to replace your previous credentials.
          </p>
        </div>

        <!-- Errors Alert -->
        @if ($errors->any())
          <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200/80 flex items-start gap-3 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-md shadow-rose-500/20">
              <i class="fa-solid fa-triangle-exclamation text-base"></i>
            </div>
            <div class="flex-1">
              <h4 class="text-sm font-black text-rose-900 tracking-tight">Password Reset Error</h4>
              <p class="text-xs text-rose-700 mt-0.5 font-semibold leading-relaxed">
                Please check the requirements below and ensure your passwords match.
              </p>
            </div>
          </div>
        @endif

        <!-- RESET PASSWORD FORM -->
        <form id="resetPasswordForm" class="space-y-6" method="POST" action="{{ route('password.store') }}">
          @csrf

          <!-- Password Reset Token -->
          <input type="hidden" name="token" value="{{ $request->route('token') }}">

          <!-- EMAIL FIELD -->
          <div class="input-floating-wrapper">
            <label for="email" class="block text-xs font-black tracking-widest uppercase text-slate-400 mb-2">Registered Email</label>
            <div class="relative group">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                <i class="fa-regular fa-envelope text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
              </div>
              <input id="email" 
                     name="email" 
                     type="email" 
                     value="{{ old('email', $request->email) }}" 
                     required 
                     autofocus 
                     autocomplete="username" 
                     class="block w-full pl-11 pr-4 py-3.5 rounded-xl border border-slate-200 bg-white/80 backdrop-blur-sm shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 text-slate-900 font-medium placeholder:text-slate-400 transition-all hover:bg-white outline-none">
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-500 text-sm font-bold flex items-center gap-1" />
          </div>

          <!-- NEW PASSWORD FIELD -->
          <div class="input-floating-wrapper">
            <label for="password" class="block text-xs font-black tracking-widest uppercase text-slate-400 mb-2">New Password</label>
            <div class="relative group">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                <i class="fa-solid fa-lock text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
              </div>
              <input id="password" 
                     name="password" 
                     type="password" 
                     required 
                     autocomplete="new-password" 
                     placeholder="••••••••••••"
                     class="block w-full pl-11 pr-12 py-3.5 rounded-xl border border-slate-200 bg-white/80 backdrop-blur-sm shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 text-slate-900 font-medium tracking-wider placeholder:tracking-normal transition-all hover:bg-white outline-none">
              <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors z-10" aria-label="Toggle password visibility">
                <i class="fa-regular fa-eye-slash text-lg"></i>
              </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-rose-500 text-sm font-bold flex items-center gap-1" />
          </div>

          <!-- CONFIRM PASSWORD FIELD -->
          <div class="input-floating-wrapper">
            <label for="password_confirmation" class="block text-xs font-black tracking-widest uppercase text-slate-400 mb-2">Confirm New Password</label>
            <div class="relative group">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                <i class="fa-solid fa-shield-halved text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
              </div>
              <input id="password_confirmation" 
                     name="password_confirmation" 
                     type="password" 
                     required 
                     autocomplete="new-password" 
                     placeholder="••••••••••••"
                     class="block w-full pl-11 pr-12 py-3.5 rounded-xl border border-slate-200 bg-white/80 backdrop-blur-sm shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 text-slate-900 font-medium tracking-wider placeholder:tracking-normal transition-all hover:bg-white outline-none">
              <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors z-10" aria-label="Toggle confirm password visibility">
                <i class="fa-regular fa-eye-slash text-lg"></i>
              </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-rose-500 text-sm font-bold flex items-center gap-1" />
          </div>

          <!-- PASSWORD POLICY BADGE -->
          <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/70">
            <p class="text-xs font-black text-slate-700 uppercase tracking-widest mb-2 flex items-center gap-2">
              <i class="fa-solid fa-shield text-blue-600"></i>
              <span>Mandatory Security Requirements</span>
            </p>
            <ul class="text-xs text-slate-600 space-y-1.5 font-medium">
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500 text-[10px]"></i>
                <span>Minimum 12 characters in length</span>
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500 text-[10px]"></i>
                <span>Must include both Uppercase and Lowercase letters</span>
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500 text-[10px]"></i>
                <span>Must include at least one Number (0-9)</span>
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500 text-[10px]"></i>
                <span>Must include at least one Symbol (!@#$%^&*...)</span>
              </li>
            </ul>
          </div>

          <!-- PRIMARY CTA -->
          <div class="pt-2">
            <button type="submit" id="submitBtn" class="btn-ripple w-full py-4 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-black tracking-widest uppercase shadow-xl shadow-blue-500/30 hover:shadow-blue-500/40 transition-all hover:-translate-y-0.5 active:scale-[0.98] flex items-center justify-center gap-2 text-base disabled:opacity-75 disabled:pointer-events-none">
              <span id="btnText">Update Password</span>
              <i id="btnIcon" class="fa-solid fa-check text-sm"></i>
              <span id="loadingSpinner" class="hidden"><i class="fa-solid fa-circle-notch fa-spin"></i></span>
            </button>
          </div>

          <!-- BACK TO LOGIN LINK -->
          <div class="text-center pt-2">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-blue-600 transition-colors py-2 px-4 rounded-xl hover:bg-slate-100/60">
              <i class="fa-solid fa-arrow-left text-xs"></i>
              <span>Cancel and Return to Sign In</span>
            </a>
          </div>
        </form>
        
        <!-- subtle footer -->
        <p class="text-[11px] text-slate-400 text-center mt-6 uppercase tracking-wider font-bold">NHMP 130 • Carriageway Emergency CRM</p>
      </div>
    </div>
  </div>

  <script>
    (function() {
      "use strict";

      function setupPasswordToggle(inputId, buttonId) {
        const input = document.getElementById(inputId);
        const btn = document.getElementById(buttonId);
        if (!input || !btn) return;
        const icon = btn.querySelector('i');
        btn.addEventListener('click', function() {
          const isPassword = input.getAttribute('type') === 'password';
          input.setAttribute('type', isPassword ? 'text' : 'password');
          if (icon) {
            icon.classList.toggle('fa-eye-slash', !isPassword);
            icon.classList.toggle('fa-eye', isPassword);
          }
        });
      }

      setupPasswordToggle('password', 'togglePassword');
      setupPasswordToggle('password_confirmation', 'toggleConfirmPassword');

      const form = document.getElementById('resetPasswordForm');
      const submitBtn = document.getElementById('submitBtn');
      const btnText = document.getElementById('btnText');
      const btnIcon = document.getElementById('btnIcon');
      const spinner = document.getElementById('loadingSpinner');

      if (form && submitBtn) {
        form.addEventListener('submit', function() {
          submitBtn.disabled = true;
          if (btnText) btnText.textContent = 'Updating Credentials...';
          if (btnIcon) btnIcon.classList.add('hidden');
          if (spinner) spinner.classList.remove('hidden');
        });
      }
    })();
  </script>
</x-guest-layout>
