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
            Credential<br>Recovery
          </h1>
          <p class="text-indigo-100 text-lg mt-4 font-medium max-w-xs">
            Operational Protocol<br>Tiered Administrator Authorization.
          </p>
        </div>

        <div class="relative z-10 mt-10">
          <div class="flex items-center gap-3 text-white/90 text-sm font-medium">
            <div class="w-5 h-5 rounded-full bg-emerald-400/30 flex items-center justify-center">
              <i class="fa-solid fa-check text-emerald-200 text-xs"></i>
            </div>
            <span>Operator requests routed to Operation Managers</span>
          </div>
          <div class="flex items-center gap-3 text-white/90 text-sm font-medium mt-3">
            <div class="w-5 h-5 rounded-full bg-emerald-400/30 flex items-center justify-center">
              <i class="fa-solid fa-check text-emerald-200 text-xs"></i>
            </div>
            <span>Peer authorization between Operation Managers</span>
          </div>
          <div class="flex items-center gap-3 text-white/90 text-sm font-medium mt-3">
            <div class="w-5 h-5 rounded-full bg-emerald-400/30 flex items-center justify-center">
              <i class="fa-solid fa-check text-emerald-200 text-xs"></i>
            </div>
            <span>Super Admin recovery isolated to server CLI</span>
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
          <h2 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Request Password Reset</h2>
          <p class="text-slate-500 text-sm font-medium mt-1">
            Submit your official username or registered email. Your reset request will be routed to the authorized Operation Managers.
          </p>
        </div>

        <!-- Session Status: Operation Manager Peer Request -->
        @if (session('status_op_manager'))
          <div class="mb-6 p-5 rounded-2xl bg-indigo-50 border border-indigo-200 flex items-start gap-3.5 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-md shadow-indigo-600/20">
              <i class="fa-solid fa-users-gear text-base"></i>
            </div>
            <div class="flex-1">
              <h4 class="text-sm font-black text-indigo-900 tracking-tight">Operation Manager Peer Authorization Dispatched</h4>
              <p class="text-xs text-indigo-700 mt-1 font-semibold leading-relaxed">
                {{ session('status_op_manager') }}
              </p>
            </div>
          </div>
        @endif

        <!-- Session Status: Operator Request -->
        @if (session('status_operator'))
          <div class="mb-6 p-5 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-start gap-3.5 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-md shadow-emerald-600/20">
              <i class="fa-solid fa-clipboard-check text-base"></i>
            </div>
            <div class="flex-1">
              <h4 class="text-sm font-black text-emerald-900 tracking-tight">Request Forwarded to Operations Control</h4>
              <p class="text-xs text-emerald-700 mt-1 font-semibold leading-relaxed">
                {{ session('status_operator') }}
              </p>
            </div>
          </div>
        @endif

        <!-- Session Status: Info / Pending -->
        @if (session('status_info'))
          <div class="mb-6 p-5 rounded-2xl bg-amber-50 border border-amber-200 flex items-start gap-3.5 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-md shadow-amber-500/20">
              <i class="fa-solid fa-clock text-base"></i>
            </div>
            <div class="flex-1">
              <h4 class="text-sm font-black text-amber-900 tracking-tight">Pending Request Active</h4>
              <p class="text-xs text-amber-700 mt-1 font-semibold leading-relaxed">
                {{ session('status_info') }}
              </p>
            </div>
          </div>
        @endif

        <!-- Error Notification -->
        @if ($errors->any())
          <div class="mb-6 p-5 rounded-2xl bg-rose-50 border border-rose-200 flex items-start gap-3.5 shadow-sm">
            <div class="w-9 h-9 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-md shadow-rose-500/20">
              <i class="fa-solid fa-shield-halved text-base"></i>
            </div>
            <div class="flex-1">
              <h4 class="text-sm font-black text-rose-900 tracking-tight">Security / Policy Notice</h4>
              <p class="text-xs text-rose-700 mt-1 font-semibold leading-relaxed">
                @if ($errors->has('identifier'))
                  {{ $errors->first('identifier') }}
                @else
                  Please check the credentials entered or contact the Operations Control Room.
                @endif
              </p>
            </div>
          </div>
        @endif

        <!-- FORGOT PASSWORD FORM -->
        <form id="forgotPasswordForm" class="space-y-6" method="POST" action="{{ route('password.email') }}">
          @csrf

          <!-- USERNAME OR EMAIL FIELD -->
          <div class="input-floating-wrapper">
            <label for="identifier" class="block text-xs font-black tracking-widest uppercase text-slate-400 mb-2">Official Username or Email</label>
            <div class="relative group">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                <i class="fa-regular fa-user text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
              </div>
              <input id="identifier" 
                     name="identifier" 
                     type="text" 
                     value="{{ old('identifier') }}"
                     required 
                     autofocus 
                     autocomplete="username" 
                     placeholder="e.g. nhmp_agent or officer@nhmp.gov.pk"
                     class="block w-full pl-11 pr-4 py-3.5 rounded-xl border border-slate-200 bg-white/80 backdrop-blur-sm shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 text-slate-900 font-medium placeholder:text-slate-400 transition-all hover:bg-white outline-none">
            </div>
            <x-input-error :messages="$errors->get('identifier')" class="mt-2 text-rose-500 text-sm font-bold flex items-center gap-1" />
          </div>

          <!-- OPTIONAL CONTACT / DUTY NOTES -->
          <div class="input-floating-wrapper">
            <label for="notes" class="block text-xs font-black tracking-widest uppercase text-slate-400 mb-2">Duty Station / Contact Notes (Optional)</label>
            <div class="relative group">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                <i class="fa-regular fa-comment-dots text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
              </div>
              <input id="notes" 
                     name="notes" 
                     type="text" 
                     value="{{ old('notes') }}"
                     placeholder="e.g. Central Zone Beat 14 or Contact: 0300-XXXXXXX"
                     class="block w-full pl-11 pr-4 py-3.5 rounded-xl border border-slate-200 bg-white/80 backdrop-blur-sm shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 text-slate-900 font-medium placeholder:text-slate-400 transition-all hover:bg-white outline-none">
            </div>
          </div>

          <!-- SECURITY HIERARCHY NOTICE BADGE -->
          <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-200/70 space-y-2 text-slate-600 text-xs font-medium">
            <div class="flex items-center gap-2 font-black text-slate-700 text-xs uppercase tracking-wider">
              <i class="fa-solid fa-shield text-blue-600"></i>
              <span>Access Hierarchy & Authorization</span>
            </div>
            <ul class="space-y-1.5 pl-5 list-disc text-slate-500">
              <li><strong class="text-slate-700">Operators & Field Personnel:</strong> Reset requests are queued for authorization by on-duty Operation Managers.</li>
              <li><strong class="text-slate-700">Operation Managers:</strong> Subject to peer authorization by fellow Operation Managers.</li>
              <li><strong class="text-slate-700">Super Administrator:</strong> Excluded from web resets; root recovery is strictly restricted to server console / SSH.</li>
            </ul>
          </div>

          <!-- PRIMARY CTA -->
          <div class="pt-2">
            <button type="submit" id="submitBtn" class="btn-ripple w-full py-4 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-black tracking-widest uppercase shadow-xl shadow-blue-500/30 hover:shadow-blue-500/40 transition-all hover:-translate-y-0.5 active:scale-[0.98] flex items-center justify-center gap-2 text-base disabled:opacity-75 disabled:pointer-events-none">
              <span id="btnText">Submit Request to Operation Managers</span>
              <i id="btnIcon" class="fa-solid fa-paper-plane text-sm"></i>
              <span id="loadingSpinner" class="hidden"><i class="fa-solid fa-circle-notch fa-spin"></i></span>
            </button>
          </div>

          <!-- BACK TO LOGIN LINK -->
          <div class="text-center pt-2">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-blue-600 transition-colors py-2 px-4 rounded-xl hover:bg-slate-100/60">
              <i class="fa-solid fa-arrow-left text-xs"></i>
              <span>Back to Sign In</span>
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

      const form = document.getElementById('forgotPasswordForm');
      const submitBtn = document.getElementById('submitBtn');
      const btnText = document.getElementById('btnText');
      const btnIcon = document.getElementById('btnIcon');
      const spinner = document.getElementById('loadingSpinner');

      if (form && submitBtn) {
        form.addEventListener('submit', function() {
          submitBtn.disabled = true;
          if (btnText) btnText.textContent = 'Transmitting Request...';
          if (btnIcon) btnIcon.classList.add('hidden');
          if (spinner) spinner.classList.remove('hidden');
        });
      }
    })();
  </script>
</x-guest-layout>
