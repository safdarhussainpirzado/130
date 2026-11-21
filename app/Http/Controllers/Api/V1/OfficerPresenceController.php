<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OfficerAvailabilityLog;
use App\Models\OfficerPresence;
use App\Services\Humsafar\HumsafarAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Patrol heartbeat (every 60 s; online = seen within 3 min) and own availability status. */
class OfficerPresenceController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $v = $request->validate(['lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'], 'app_version' => ['nullable', 'string', 'max:24']]);
        $p = OfficerPresence::updateOrCreate(['user_id' => $request->user()->id], [
            'last_seen_at' => now(), 'last_lat' => $v['lat'] ?? null, 'last_lng' => $v['lng'] ?? null, 'app_version' => $v['app_version'] ?? null,
        ]);

        return response()->json(['success' => true, 'online' => true, 'availability' => $p->availability, 'server_time' => now()->toIso8601String()]);
    }

    public function status(Request $request): JsonResponse
    {
        $p = OfficerPresence::where('user_id', $request->user()->id)->first();

        return response()->json(['success' => true, 'online' => (bool) $p?->isOnline(), 'availability' => $p?->availability ?? 'available', 'last_seen_at' => $p?->last_seen_at]);
    }

    public function setAvailability(Request $request): JsonResponse
    {
        $v = $request->validate(['status' => ['required', Rule::in(OfficerPresence::AVAILABILITY)], 'reason' => ['nullable', 'string', 'max:255']]);

        return $this->switchTo($request, $v['status'], $v['reason'] ?? null);
    }

    public function returnToDuty(Request $request): JsonResponse
    {
        return $this->switchTo($request, 'available', null);
    }

    private function switchTo(Request $request, string $status, ?string $reason): JsonResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($user, $status, $reason) {
            $now = now();
            $open = OfficerAvailabilityLog::where('user_id', $user->id)->whereNull('ended_at')->latest('id')->first();
            $open?->update(['ended_at' => $now, 'duration_sec' => max(0, (int) $open->started_at->diffInSeconds($now))]);
            OfficerAvailabilityLog::create(['user_id' => $user->id, 'status' => $status, 'reason' => $reason, 'started_at' => $now]);
            OfficerPresence::updateOrCreate(['user_id' => $user->id], ['availability' => $status, 'last_seen_at' => $now]);
        });
        HumsafarAudit::log($user, 'officer_availability_changed', 'officer_availability_log', $user->id, ['status' => $status, 'reason' => $reason]);

        return response()->json(['success' => true, 'availability' => $status]);
    }
}
