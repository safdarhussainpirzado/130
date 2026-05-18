<div x-data="reportFilters" class="bg-white rounded-[2rem] shadow-xl border border-slate-100 p-8 mb-8">
    <form @submit.prevent="fetchReport" action="{{ url()->current() }}" method="GET" id="filterForm" class="space-y-8">
        <!-- ── Hierarchy & Personnel ─────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Zone -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Zone</label>
                <select name="zone_id" x-model="selectedZone" @change="updateSectors()" class="w-full bg-slate-50 border-none rounded-2xl p-4 text-sm font-bold text-navy-900 focus:ring-2 focus:ring-emerald-500/20 transition-all">
                    <option value="">All Zones</option>
                    <template x-for="zone in zones" :key="zone.id">
                        <option :value="zone.id" x-text="zone.name" :selected="selectedZone == zone.id"></option>
                    </template>
                </select>
            </div>

            <!-- Sector -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Sector</label>
                <select name="sector_id" x-model="selectedSector" @change="updateBeats()" :disabled="!selectedZone" class="w-full bg-slate-50 border-none rounded-2xl p-4 text-sm font-bold text-navy-900 focus:ring-2 focus:ring-emerald-500/20 transition-all disabled:opacity-50">
                    <option value="">All Sectors</option>
                    <template x-for="sector in filteredSectors" :key="sector.id">
                        <option :value="sector.id" x-text="sector.name" :selected="selectedSector == sector.id"></option>
                    </template>
                </select>
            </div>

            <!-- Beat -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Beat</label>
                <select name="beat_id" x-model="selectedBeat" :disabled="!selectedSector" class="w-full bg-slate-50 border-none rounded-2xl p-4 text-sm font-bold text-navy-900 focus:ring-2 focus:ring-emerald-500/20 transition-all disabled:opacity-50">
                    <option value="">All Beats</option>
                    <template x-for="beat in filteredBeats" :key="beat.id">
                        <option :value="beat.id" x-text="beat.name" :selected="selectedBeat == beat.id"></option>
                    </template>
                </select>
            </div>

            <!-- Agent -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Agent</label>
                <select name="agent_id" class="w-full bg-slate-50 border-none rounded-2xl p-4 text-sm font-bold text-navy-900 focus:ring-2 focus:ring-emerald-500/20 transition-all">
                    <option value="">All Agents</option>
                    @foreach($agents ?? [] as $agent)
                        <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->username }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- ── Temporal Controls ───────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end border-t border-slate-50 pt-8">
            <!-- Date Range -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Date Range</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="date_from" value="{{ request('date_from', now()->toDateString()) }}" class="flex-1 bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold text-navy-900">
                    <span class="text-slate-300 font-black">to</span>
                    <input type="date" name="date_to" value="{{ request('date_to', now()->toDateString()) }}" class="flex-1 bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold text-navy-900">
                </div>
            </div>

            <!-- Time Range -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Time Range</label>
                <div class="flex items-center gap-2">
                    <input type="time" name="time_from" value="{{ request('time_from') }}" class="flex-1 bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold text-navy-900">
                    <span class="text-slate-300 font-black">to</span>
                    <input type="time" name="time_to" value="{{ request('time_to') }}" class="flex-1 bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold text-navy-900">
                </div>
            </div>

            <!-- Shift Shortcuts -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Quick Shifts</label>
                <div class="flex gap-2">
                    <button type="button" @click="setShift('06:00', '14:00')" class="flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-tighter bg-gradient-to-b from-emerald-400 to-emerald-500 text-white border-b-4 border-emerald-700 hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-md shadow-emerald-500/30">Morning</button>
                    <button type="button" @click="setShift('14:00', '20:00')" class="flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-tighter bg-gradient-to-b from-amber-400 to-amber-500 text-white border-b-4 border-amber-700 hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-md shadow-amber-500/30">Evening</button>
                    <button type="button" @click="setShift('20:00', '06:00')" class="flex-1 py-3 rounded-xl text-[10px] font-black uppercase tracking-tighter bg-gradient-to-b from-indigo-400 to-indigo-500 text-white border-b-4 border-indigo-700 hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-md shadow-indigo-500/30">Night</button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button type="submit" :disabled="loading" class="flex-1 bg-gradient-to-b from-blue-500 to-blue-600 border-b-4 border-blue-800 text-white rounded-2xl py-4 font-black text-xs uppercase tracking-widest hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-lg shadow-blue-600/30 flex justify-center items-center gap-2 disabled:opacity-75 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-spinner fa-spin" x-show="loading"></i>
                    <span x-text="loading ? 'Generating...' : 'Generate Report'"></span>
                </button>
                <button type="button" @click="window.location.href=window.location.pathname" class="w-14 bg-gradient-to-b from-slate-200 to-slate-300 border-b-4 border-slate-400 text-slate-600 rounded-2xl flex items-center justify-center hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-md">
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>
        </div>

        <!-- ── Column Visibility ───────────────────────────────────── -->
        <div class="border-t border-slate-50 pt-8">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block mb-4">Visible Columns</label>
            <div class="flex flex-wrap gap-3">
                <template x-for="(label, col) in availableColumns" :key="col">
                    <label class="relative flex items-center group cursor-pointer">
                        <input type="checkbox" :name="'cols['+col+']'" value="1" x-model="visibleColumns[col]" class="sr-only">
                        <div class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border border-slate-100"
                             :class="visibleColumns[col] ? 'bg-emerald-50 border-emerald-200 text-emerald-700 shadow-sm' : 'bg-white text-slate-400 opacity-60 hover:opacity-100'">
                            <span x-text="label"></span>
                        </div>
                    </label>
                </template>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('reportFilters', () => ({
            zones: @json($offices['zones'] ?? []),
            sectors: @json($offices['sectors'] ?? []),
            beats: @json($offices['beats'] ?? []),
            selectedZone: '{{ request('zone_id') }}',
            selectedSector: '{{ request('sector_id') }}',
            selectedBeat: '{{ request('beat_id') }}',
            filteredSectors: [],
            filteredBeats: [],
            availableColumns: @json($availableColumns ?? []),
            visibleColumns: @json($visibleColumns ?? []),
            loading: false,

            init() {
                this.updateSectors(true);
                this.updateBeats(true);
            },

            updateSectors(initial = false) {
                this.filteredSectors = this.sectors.filter(s => s.parent_id == this.selectedZone);
                if (!initial) {
                    this.selectedSector = '';
                    this.selectedBeat = '';
                    this.filteredBeats = [];
                }
            },

            updateBeats(initial = false) {
                this.filteredBeats = this.beats.filter(b => b.parent_id == this.selectedSector);
                if (!initial) this.selectedBeat = '';
            },

            setShift(from, to) {
                document.getElementsByName('time_from')[0].value = from;
                document.getElementsByName('time_to')[0].value = to;
            },

            async fetchReport(event) {
                this.loading = true;
                const form = event.target;
                const url = new URL(form.action);
                const formData = new FormData(form);
                const searchParams = new URLSearchParams();
                
                for (const pair of formData.entries()) {
                    if (pair[1]) searchParams.append(pair[0], pair[1]);
                }

                url.search = searchParams.toString();
                
                // Update history
                window.history.pushState({}, '', url);

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const html = await response.text();
                    
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    const newContent = doc.getElementById('report-content');
                    const currentContent = document.getElementById('report-content');
                    
                    if (newContent && currentContent) {
                        currentContent.innerHTML = newContent.innerHTML;
                    }
                    
                    // Also update export links to have the new parameters
                    const exportExcel = document.getElementById('export-excel');
                    if (exportExcel) {
                        const excelUrl = new URL(url);
                        excelUrl.searchParams.set('export', 'excel');
                        exportExcel.href = excelUrl.toString();
                    }
                    const exportPdf = document.getElementById('export-pdf');
                    if (exportPdf) {
                        const pdfUrl = new URL(url);
                        pdfUrl.searchParams.set('export', 'pdf');
                        exportPdf.href = pdfUrl.toString();
                    }

                } catch (error) {
                    console.error('Error fetching report:', error);
                } finally {
                    this.loading = false;
                }
            }
        }));
    });
</script>
