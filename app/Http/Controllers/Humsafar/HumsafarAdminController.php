<?php

namespace App\Http\Controllers\Humsafar;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CommuterPresence;
use App\Models\HelpFeedback;
use App\Models\OfficerPresence;
use App\Models\SurveyCategory;
use App\Models\SurveyLocation;
use App\Models\SurveyService;
use App\Models\User;
use App\Services\Humsafar\HumsafarAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Humsafar admin pages (staging only). Permission-gated in routes/web.php. */
class HumsafarAdminController extends Controller
{
    // ---- Survey locations ------------------------------------------------------------
    public function surveys(Request $request): View
    {
        $q = SurveyLocation::with(['category:id,name', 'office:id,name,parent_id', 'surveyor:id,full_name'])->latest('id');
        foreach (['category_id', 'status', 'geo_status', 'office_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('direction')) {
            $q->where('direction', strtoupper($request->string('direction')));
        }
        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->date('date_to'));
        }
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('address', 'like', "%{$s}%"));
        }

        return view('humsafar.surveys', [
            'locations' => $q->paginate(20)->withQueryString(),
            'categories' => SurveyCategory::orderBy('sort_order')->get(['id', 'name']),
            'counts' => SurveyLocation::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'filters' => $request->only(['category_id', 'status', 'geo_status', 'direction', 'date_from', 'date_to', 'search']),
        ]);
    }

    public function reviewSurvey(Request $request, SurveyLocation $location): RedirectResponse
    {
        $v = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:255']]);
        if ($v['decision'] === 'reject' && empty($v['note'])) {
            return back()->withErrors(['note' => 'A reason is required to reject a survey.']);
        }
        $location->update([
            'status' => $v['decision'] === 'approve' ? 'approved' : 'rejected', 'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(), 'review_note' => $v['note'] ?? null,
        ]);
        HumsafarAudit::log($request->user(), 'survey_'.$v['decision'], 'survey_locations', $location->id, ['note' => $v['note'] ?? null]);

        return back()->with('success', 'Survey '.($v['decision'] === 'approve' ? 'approved' : 'rejected').'.');
    }

    // ---- Categories & services -------------------------------------------------------
    public function catalog(): View
    {
        return view('humsafar.catalog', ['categories' => SurveyCategory::with(['services' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('sort_order')->get()]);
    }

    public function saveCategory(Request $request, ?SurveyCategory $category = null): RedirectResponse
    {
        $v = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:survey_categories,name'.($category ? ','.$category->id : '')], 'icon' => ['nullable', 'string', 'max:60'], 'description' => ['nullable', 'string', 'max:255'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'is_active' => ['nullable', 'boolean']]);
        $v['is_active'] = $request->boolean('is_active', true);
        $category ? $category->update($v) : SurveyCategory::create($v + ['sort_order' => $v['sort_order'] ?? 99]);

        return back()->with('success', 'Category saved.');
    }

    public function saveService(Request $request, ?SurveyService $service = null): RedirectResponse
    {
        $v = $request->validate(['category_id' => ['required', 'exists:survey_categories,id'], 'name' => ['required', 'string', 'max:120'], 'icon' => ['nullable', 'string', 'max:60'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        $v['is_active'] = $request->boolean('is_active', true);
        $service ? $service->update($v) : SurveyService::updateOrCreate(['category_id' => $v['category_id'], 'name' => $v['name']], $v + ['sort_order' => $v['sort_order'] ?? 99]);

        return back()->with('success', 'Service saved.');
    }

    public function toggle(Request $request, string $type, int $id): RedirectResponse
    {
        $m = $type === 'category' ? SurveyCategory::findOrFail($id) : SurveyService::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);

        return back()->with('success', ucfirst($type).($m->is_active ? ' activated.' : ' deactivated.'));
    }

    // ---- Online officers -------------------------------------------------------------
    public function officers(Request $request): View
    {
        return view('humsafar.officers', $this->officerData($request));
    }

    public function officersData(Request $request): JsonResponse
    {
        return response()->json($this->officerData($request, true));
    }

    private function officerData(Request $request, bool $json = false): array
    {
        $cut = now()->subSeconds(OfficerPresence::ONLINE_WINDOW_SECONDS);
        $q = OfficerPresence::with('user:id,full_name,username')->orderByDesc('last_seen_at');
        if ($request->input('online') === '1') {
            $q->where('last_seen_at', '>=', $cut);
        } elseif ($request->input('online') === '0') {
            $q->where(fn ($w) => $w->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $cut));
        }
        if ($request->filled('availability')) {
            $q->where('availability', $request->input('availability'));
        }
        $rows = $q->limit(300)->get()->map(fn ($p) => [
            'name' => $p->user?->full_name, 'username' => $p->user?->username, 'online' => $p->isOnline(), 'availability' => $p->availability,
            'last_seen' => $p->last_seen_at?->diffForHumans(), 'lat' => $p->last_lat, 'lng' => $p->last_lng,
        ]);
        $all = OfficerPresence::selectRaw('count(*) total, sum(last_seen_at >= ?) online', [$cut])->first();
        $summary = ['total' => (int) $all->total, 'online' => (int) $all->online, 'available' => OfficerPresence::where('last_seen_at', '>=', $cut)->where('availability', 'available')->count()];

        return $json ? ['rows' => $rows, 'summary' => $summary] : ['rows' => $rows, 'summary' => $summary, 'availabilities' => OfficerPresence::AVAILABILITY, 'filters' => $request->only(['online', 'availability'])];
    }

    // ---- Feedback --------------------------------------------------------------------
    public function feedback(): View
    {
        $per = HelpFeedback::select('officer_id', DB::raw('count(*) n'), DB::raw('round(avg(overall),2) avg_overall'), DB::raw('round(avg(timeliness),2) avg_t'), DB::raw('round(avg(behaviour),2) avg_b'), DB::raw('round(avg(helpfulness),2) avg_h'))
            ->groupBy('officer_id')->with('officer:id,full_name')->orderByDesc('avg_overall')->get();

        return view('humsafar.feedback', ['perOfficer' => $per, 'recent' => HelpFeedback::with(['officer:id,full_name', 'call:id,call_number'])->latest('submitted_at')->limit(30)->get()]);
    }

    // ---- Commuters -------------------------------------------------------------------
    public function commuters(Request $request): View
    {
        $q = User::whereHas('commuterProfile')->with('commuterProfile:id,user_id,is_cnic_verified,gender')->orderByDesc('id');
        if ($s = trim((string) $request->input('search'))) {
            $mob = phone_blind_index($s);
            $tokens = function_exists('name_search_tokens') ? name_search_tokens($s) : [];
            $email = email_blind_index($s);
            $q->where(function ($w) use ($mob, $tokens, $email) {
                if ($mob) {
                    $w->orWhere('mobile_no_bidx', $mob);
                }
                if ($email) {
                    $w->orWhere('email_bidx', $email);
                }
                if ($tokens) {
                    $w->orWhereHas('searchTokens', fn ($sq) => $sq->whereIn('token_bidx', $tokens));
                }
            });
        }
        $p = $q->paginate(20)->withQueryString();
        $p->getCollection()->transform(function ($u) {
            $m = (string) $u->mobile_no;
            $u->masked_mobile = $m !== '' ? substr($m, 0, 4).str_repeat('*', max(0, strlen($m) - 7)).substr($m, -3) : '-';

            return $u;
        });

        return view('humsafar.commuters', ['commuters' => $p, 'search' => $request->input('search'), 'canReveal' => $request->user()->hasPermission('humsafar.commuters.reveal')]);
    }

    public function revealMobile(Request $request, User $user): JsonResponse
    {
        abort_unless($user->commuterProfile()->exists(), 404);
        HumsafarAudit::log($request->user(), 'commuter_mobile_revealed', 'users', $user->id);

        return response()->json(['mobile' => (string) $user->mobile_no]);
    }

    public function monitor(): View
    {
        HumsafarAudit::log(request()->user(), 'commuter_monitor_viewed', 'commuter_presence', null);

        return view('humsafar.monitor');
    }

    public function monitorData(Request $request): JsonResponse
    {
        $cut = now()->subSeconds(CommuterPresence::ONLINE_WINDOW_SECONDS);
        $live = CommuterPresence::with('user:id,full_name')->where('sharing', true)->where('last_seen_at', '>=', $cut)->whereNotNull('lat')->limit(500)->get()
            ->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user?->full_name, 'lat' => $p->lat, 'lng' => $p->lng, 'speed' => $p->speed, 'seen' => $p->last_seen_at->diffForHumans()]);
        $registered = \App\Models\CommuterProfile::count();

        return response()->json(['registered' => $registered, 'online' => CommuterPresence::where('last_seen_at', '>=', $cut)->count(), 'sharing' => $live->count(), 'commuters' => $live]);
    }
}
