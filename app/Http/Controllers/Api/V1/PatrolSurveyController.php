<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SurveyCategory;
use App\Models\SurveyLocation;
use App\Services\Humsafar\SurveyGeoEnricher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Patrol app: survey categories/services + create/list/show/update own survey locations. */
class PatrolSurveyController extends Controller
{
    public function categories(): JsonResponse
    {
        $cats = SurveyCategory::where('is_active', true)->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->where('is_active', true)])->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'icon' => $c->icon, 'services' => $c->services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'icon' => $s->icon])->values()]);

        return response()->json(['success' => true, 'data' => $cats]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = SurveyLocation::with('category:id,name')->where('surveyed_by', $request->user()->id)->latest('id');
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        $p = $q->paginate(min((int) $request->input('per_page', 20), 50));

        return response()->json(['success' => true, 'data' => $p->getCollection()->map(fn ($l) => $this->present($l)), 'meta' => ['page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->present($this->own($request, $uuid))]);
    }

    public function store(Request $request, SurveyGeoEnricher $geo): JsonResponse
    {
        $v = $this->validated($request);
        $loc = new SurveyLocation($this->attrs($v, $geo) + ['surveyed_by' => $request->user()->id]);
        $loc->save();
        $loc->services()->sync($v['service_ids'] ?? []);

        return response()->json(['success' => true, 'data' => $this->present($loc->fresh(['category', 'services']))], 201);
    }

    public function update(Request $request, string $uuid, SurveyGeoEnricher $geo): JsonResponse
    {
        $loc = $this->own($request, $uuid);
        if (in_array($loc->status, ['approved'], true)) {
            return response()->json(['success' => false, 'message' => 'Approved surveys can no longer be edited.'], 422);
        }
        $v = $this->validated($request);
        $loc->fill($this->attrs($v, $geo))->save();
        $loc->services()->sync($v['service_ids'] ?? []);

        return response()->json(['success' => true, 'data' => $this->present($loc->fresh(['category', 'services']))]);
    }

    private function own(Request $request, string $uuid): SurveyLocation
    {
        return SurveyLocation::with(['category', 'services'])->where('uuid', $uuid)->where('surveyed_by', $request->user()->id)->firstOrFail();
    }

    private function validated(Request $request): array
    {
        $is24 = $request->boolean('is_24h');

        return $request->validate([
            'category_id' => ['required', Rule::exists('survey_categories', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'direction' => ['nullable', Rule::in(['N', 'S', 'E', 'W', 'n', 's', 'e', 'w'])],
            'phone_numbers' => ['nullable', 'array', 'max:5'],
            'phone_numbers.*' => ['string', 'max:20'],
            'is_24h' => ['boolean'],
            'opens_at' => [$is24 ? 'nullable' : 'required', 'date_format:H:i'],
            'closes_at' => [$is24 ? 'nullable' : 'required', 'date_format:H:i'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [Rule::exists('survey_services', 'id')->where('is_active', true)],
            'submit' => ['boolean'],
        ]);
    }

    private function attrs(array $v, SurveyGeoEnricher $geo): array
    {
        $g = $geo->enrich((float) $v['lat'], (float) $v['lng'], $v['direction'] ?? null);
        $is24 = (bool) ($v['is_24h'] ?? false);

        return [
            'category_id' => $v['category_id'], 'name' => $v['name'], 'address' => $v['address'] ?? null,
            'lat' => $v['lat'], 'lng' => $v['lng'], 'phone_numbers' => $v['phone_numbers'] ?? [],
            'is_24h' => $is24, 'opens_at' => $is24 ? null : $v['opens_at'], 'closes_at' => $is24 ? null : $v['closes_at'],
            'office_id' => $g['office_id'], 'marker_id' => $g['marker_id'], 'km' => $g['km'], 'direction' => $g['direction'] ?? ($v['direction'] ?? null),
            'geo_status' => $g['geo_status'], 'status' => ! empty($v['submit']) ? 'submitted' : 'draft',
        ];
    }

    private function present(SurveyLocation $l): array
    {
        return [
            'uuid' => $l->uuid, 'name' => $l->name, 'category' => $l->category?->name, 'status' => $l->status, 'geo_status' => $l->geo_status,
            'lat' => $l->lat, 'lng' => $l->lng, 'km' => $l->km, 'direction' => $l->direction, 'is_24h' => $l->is_24h,
            'opens_at' => $l->opens_at, 'closes_at' => $l->closes_at, 'phone_numbers' => $l->phone_numbers,
            'services' => $l->relationLoaded('services') ? $l->services->pluck('name') : [], 'review_note' => $l->review_note,
        ];
    }
}
