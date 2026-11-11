<?php

namespace App\Http\Controllers;

use App\Models\GeospatialLandmark;
use App\Models\Office;
use Illuminate\Http\Request;

class GeospatialLandmarkController extends Controller
{
    /**
     * Display a listing of geospatial landmarks (Caller Location Helper).
     * Used by Helpline 130 agents to identify commuter locations and interchanges.
     */
    public function index(Request $request)
    {
        $perPage  = min(max((int) $request->input('per_page', 15), 10), 100);
        $sortBy   = in_array($request->input('sort_by'), ['km_numeric', 'road_name', 'location_name']) ? $request->input('sort_by') : 'km_numeric';
        $sortDir  = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = GeospatialLandmark::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('location_name', 'LIKE', "%{$search}%")
                  ->orWhere('road_name',   'LIKE', "%{$search}%")
                  ->orWhere('beat_name',   'LIKE', "%{$search}%")
                  ->orWhere('zone_name',   'LIKE', "%{$search}%")
                  ->orWhere('nearby_cities', 'LIKE', "%{$search}%");
            });
        }

        if ($zoneId = $request->input('zone_id')) {
            $query->where('zone_name', 'LIKE', "%{$zoneId}%");
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where(function($q) {
                    $q->whereNull('agent_prompt')->orWhere('agent_prompt', 'NOT LIKE', '[INACTIVE]%');
                });
            } elseif ($status === 'inactive') {
                $query->where('agent_prompt', 'LIKE', '[INACTIVE]%');
            } elseif ($status === 'motorway') {
                $query->where('road_name', 'LIKE', 'M-%');
            } elseif ($status === 'highway') {
                $query->where('road_name', 'LIKE', 'N-%');
            }
        }

        $landmarks = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $zones = GeospatialLandmark::select('zone_name')->distinct()->whereNotNull('zone_name')->pluck('zone_name');

        // Single-pass conditional aggregation for KPI cards
        $statsRaw = GeospatialLandmark::selectRaw('
            COUNT(*) as total_markers,
            SUM(CASE WHEN agent_prompt IS NULL OR agent_prompt NOT LIKE "[INACTIVE]%" THEN 1 ELSE 0 END) as active_markers,
            SUM(CASE WHEN agent_prompt LIKE "[INACTIVE]%" THEN 1 ELSE 0 END) as inactive_markers,
            SUM(CASE WHEN road_name LIKE "M-%" THEN 1 ELSE 0 END) as motorways,
            SUM(CASE WHEN road_name LIKE "N-%" THEN 1 ELSE 0 END) as highways
        ')->first();

        $stats = [
            'total_markers'     => (int) ($statsRaw->total_markers ?? 0),
            'active_markers'    => (int) ($statsRaw->active_markers ?? 0),
            'inactive_markers'  => (int) ($statsRaw->inactive_markers ?? 0),
            'motorways'         => (int) ($statsRaw->motorways ?? 0),
            'highways'          => (int) ($statsRaw->highways ?? 0),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'items'      => $landmarks->items(),
                'pagination' => [
                    'current_page' => $landmarks->currentPage(),
                    'last_page'    => $landmarks->lastPage(),
                    'total'        => $landmarks->total(),
                    'per_page'     => $landmarks->perPage(),
                ],
                'stats' => $stats,
            ]);
        }

        $beats = Office::where('type', 'beat')->select(['id', 'name', 'parent_id'])->get();

        return view('operations.geospatial-landmarks.index', [
            'geospatialMarkers' => $landmarks,
            'zones'             => $zones,
            'beats'             => $beats,
            'stats'             => $stats,
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user() || (!$request->user()->can('geography.geospatial.manage') && $request->user()->role?->name !== 'super_admin')) {
            abort(403, 'Unauthorized. Registering geospatial landmarks requires elevated permissions.');
        }

        $validated = $request->validate([
            'road_name'      => 'required|string|max:100',
            'location_name'  => 'required|string|max:255',
            'km_marker'      => 'nullable|string|max:20',
            'beat_id'        => 'nullable|exists:offices,id',
            'zone_id'        => 'nullable|exists:offices,id',
            'sector_id'      => 'nullable|exists:offices,id',
            'nearby_cities'  => 'nullable|string|max:255',
            'fuel_station'   => 'nullable|string|max:100',
            'contact_numbers'=> 'nullable|string|max:255',
        ]);

        $km = $validated['km_marker'] ?? null;

        $beatOffice   = $validated['beat_id']   ? Office::find($validated['beat_id'])   : null;
        $sectorOffice = $validated['sector_id'] ? Office::find($validated['sector_id']) : null;
        $zoneOffice   = $validated['zone_id']   ? Office::find($validated['zone_id'])   : null;

        $marker = GeospatialLandmark::create([
            'office_id'       => $validated['beat_id'] ?? 1,
            'road_name'       => $validated['road_name'],
            'location_name'   => $validated['location_name'],
            'km_marker'       => $km,
            'km_numeric'      => is_numeric($km) ? (float)$km : null,
            'beat_name'       => $beatOffice?->name,
            'sector_name'     => $sectorOffice?->name,
            'zone_name'       => $zoneOffice?->name,
            'nearby_cities'   => $validated['nearby_cities'] ?? null,
            'fuel_station'    => $validated['fuel_station']  ?? null,
            'contact_numbers' => $validated['contact_numbers'] ?? null,
            'agent_prompt'    => 'Contextual alert: Operative should verify if caller is near ' . $validated['location_name'] . ($km ? " (KM {$km})" : ''),
        ]);

        return response()->json(['success' => true, 'marker' => $marker]);
    }

    public function update(Request $request, GeospatialLandmark $geospatialLandmark)
    {
        if (!$request->user() || (!$request->user()->can('geography.geospatial.manage') && $request->user()->role?->name !== 'super_admin')) {
            abort(403, 'Unauthorized. Modifying geospatial landmarks requires elevated permissions.');
        }

        $validated = $request->validate([
            'road_name'      => 'required|string|max:100',
            'location_name'  => 'required|string|max:255',
            'km_marker'      => 'nullable|string|max:20',
            'beat_id'        => 'nullable|exists:offices,id',
            'zone_id'        => 'nullable|exists:offices,id',
            'sector_id'      => 'nullable|exists:offices,id',
            'nearby_cities'  => 'nullable|string|max:255',
            'fuel_station'   => 'nullable|string|max:100',
            'contact_numbers'=> 'nullable|string|max:255',
        ]);

        $km = $validated['km_marker'] ?? null;

        $beatOffice   = $validated['beat_id']   ? Office::find($validated['beat_id'])   : null;
        $sectorOffice = $validated['sector_id'] ? Office::find($validated['sector_id']) : null;
        $zoneOffice   = $validated['zone_id']   ? Office::find($validated['zone_id'])   : null;

        $geospatialLandmark->update([
            'office_id'       => $validated['beat_id'] ?? $geospatialLandmark->office_id,
            'road_name'       => $validated['road_name'],
            'location_name'   => $validated['location_name'],
            'km_marker'       => $km,
            'km_numeric'      => is_numeric($km) ? (float)$km : null,
            'beat_name'       => $beatOffice?->name   ?? $geospatialLandmark->beat_name,
            'sector_name'     => $sectorOffice?->name ?? $geospatialLandmark->sector_name,
            'zone_name'       => $zoneOffice?->name   ?? $geospatialLandmark->zone_name,
            'nearby_cities'   => $validated['nearby_cities']   ?? null,
            'fuel_station'    => $validated['fuel_station']     ?? null,
            'contact_numbers' => $validated['contact_numbers']  ?? null,
            'agent_prompt'    => 'Contextual alert: Operative should verify if caller is near ' . $validated['location_name'] . ($km ? " (KM {$km})" : ''),
        ]);

        return response()->json(['success' => true, 'marker' => $geospatialLandmark->fresh()]);
    }

    public function destroy(Request $request, GeospatialLandmark $geospatialLandmark)
    {
        if (!$request->user() || (!$request->user()->can('geography.geospatial.manage') && $request->user()->role?->name !== 'super_admin')) {
            abort(403, 'Unauthorized. Deleting geospatial landmarks requires elevated permissions.');
        }

        $geospatialLandmark->delete();
        return response()->json(['success' => true]);
    }

    public function toggleStatus(Request $request, GeospatialLandmark $geospatialLandmark)
    {
        if (!$request->user() || (!$request->user()->can('geography.geospatial.manage') && $request->user()->role?->name !== 'super_admin')) {
            abort(403, 'Unauthorized. Modifying geospatial landmark status requires elevated permissions.');
        }

        $isCurrentlyActive = !str_starts_with($geospatialLandmark->agent_prompt ?? '', '[INACTIVE]');
        
        if ($isCurrentlyActive) {
            $newPrompt = '[INACTIVE] ' . ltrim(str_replace('[INACTIVE] ', '', $geospatialLandmark->agent_prompt ?? ''));
            $newStatus = 'inactive';
        } else {
            $newPrompt = ltrim(str_replace('[INACTIVE] ', '', $geospatialLandmark->agent_prompt ?? ''));
            $newStatus = 'active';
        }

        $geospatialLandmark->update(['agent_prompt' => $newPrompt]);

        return response()->json([
            'success' => true,
            'status'  => $newStatus,
        ]);
    }
}
