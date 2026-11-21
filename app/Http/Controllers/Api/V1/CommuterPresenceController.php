<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommuterPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/v1/commuter/presence (rate-limited) - latest position only, no history. */
class CommuterPresenceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $v = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180'],
            'heading' => ['nullable', 'numeric', 'between:0,360'], 'speed' => ['nullable', 'numeric', 'min:0', 'max:400'], 'sharing' => ['nullable', 'boolean'],
        ]);
        CommuterPresence::updateOrCreate(['user_id' => $request->user()->id], [
            'lat' => $v['lat'], 'lng' => $v['lng'], 'heading' => $v['heading'] ?? null, 'speed' => $v['speed'] ?? null,
            'sharing' => $v['sharing'] ?? true, 'last_seen_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
