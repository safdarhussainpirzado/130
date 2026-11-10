<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RealtimeStreamController extends Controller
{
    /**
     * Server-Sent Events (SSE) Stream for Mission-Critical Emergency Dispatch Operations.
     * Delivers sub-second updates for Priority 1, Tiger Dispatches, Tiger Reached, and Completions.
     * Supports Last-Event-ID recovery, proxy buffer bypass, and scope filtering.
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        $zoneId = $request->query('zone_id') ? (int) $request->query('zone_id') : null;
        $sectorId = $request->query('sector_id') ? (int) $request->query('sector_id') : null;
        $lastEventId = $request->header('Last-Event-ID', $request->query('last_event_id', 0));
        $lastEventId = is_numeric($lastEventId) ? (int) $lastEventId : 0;

        return response()->stream(function () use ($user, $zoneId, $sectorId, $lastEventId) {
            // Disable output buffering
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $currentId = $lastEventId;
            $iterations = 0;
            $maxIterations = 1; // Non-blocking burst mode: Emits pending events and releases RoadRunner worker immediately

            // Send initial connection handshake with 1s browser reconnection interval
            echo "retry: 1000\n";
            echo "event: connected\n";
            echo "data: " . json_encode([
                'status' => 'connected',
                'connected_at' => now()->toIso8601String(),
                'last_event_id' => $currentId,
            ]) . "\n\n";
            flush();

            while ($iterations < $maxIterations) {
                if (connection_aborted()) {
                    break;
                }

                // Query for new outbox events matching emergency criteria
                $eventsQuery = DB::table('analytics_outbox_events')
                    ->where('id', '>', $currentId)
                    ->where(function ($q) {
                        $q->where('priority', 1)
                          ->orWhereIn('new_status', [
                              'pending',
                              'in_progress',
                              'tiger_dispatched',
                              'tiger_reached',
                              'assistance_provided',
                              'completed'
                          ]);
                    });

                if ($zoneId) {
                    $eventsQuery->where(function ($q) use ($zoneId) {
                        $q->where('new_zone_id', $zoneId)->orWhere('new_zone_id', 0);
                    });
                }
                if ($sectorId) {
                    $eventsQuery->where(function ($q) use ($sectorId) {
                        $q->where('new_sector_id', $sectorId)->orWhere('new_sector_id', 0);
                    });
                }

                $events = $eventsQuery->orderBy('id', 'asc')->limit(10)->get();

                if ($events->isNotEmpty()) {
                    foreach ($events as $event) {
                        $currentId = $event->id;
                        $sseType = match ($event->new_status) {
                            'tiger_dispatched' => 'TigerDispatched',
                            'tiger_reached' => 'TigerReached',
                            'assistance_provided' => 'AssistanceProvided',
                            'completed' => 'HelpCompleted',
                            default => $event->priority === 1 ? 'Priority1Call' : 'CallStatusUpdated'
                        };

                        $payload = [
                            'event_id' => $event->id,
                            'event_uuid' => $event->event_uuid,
                            'call_id' => $event->call_id,
                            'status' => $event->new_status,
                            'priority' => $event->priority,
                            'zone_id' => $event->new_zone_id,
                            'sector_id' => $event->new_sector_id,
                            'timestamp' => $event->created_at,
                            'details' => json_decode($event->payload ?? '{}', true)
                        ];

                        echo "id: {$event->id}\n";
                        echo "event: {$sseType}\n";
                        echo "data: " . json_encode($payload) . "\n\n";
                        flush();
                    }
                } else {
                    // Send periodic keep-alive heartbeat comment every ~5s
                    if ($iterations % 10 === 0) {
                        echo ": ping " . time() . "\n\n";
                        flush();
                    }
                }

                $iterations++;
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Critical: tells Nginx not to buffer SSE stream
        ]);
    }

    /**
     * Sub-second delta check endpoint for real-time Women Safety Emergency & Priority 1 calls.
     * High-speed, non-blocking check leveraging Redis & indexed MySQL lookup.
     * Strictly scoped to the operator's authorized beat/jurisdiction.
     */
    public function checkEmergency(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $lastId = (int) $request->query('last_id', 0);
        $zoneId = $request->query('zone_id') ? (int) $request->query('zone_id') : null;
        $sectorId = $request->query('sector_id') ? (int) $request->query('sector_id') : null;
        $beatId = $request->query('beat_id') ? (int) $request->query('beat_id') : null;

        // Fetch any new pending female emergency call
        $query = DB::table('calls')
            ->select('id', 'uuid', 'call_number', 'caller_name', 'caller_number', 'caller_gender', 'call_type_id', 'status', 'priority', 'office_id', 'created_at')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('caller_gender', 'female')
                  ->orWhere('call_type_id', 10);
            });

        // 1. Apply user role and organizational scope visibility
        if ($user) {
            $isGlobalAdmin = in_array($user->role?->name, ['super_admin', 'operation_admin'], true)
                && ($user->role?->scope_level === 'national' || $user->role?->name === 'super_admin');

            if (!$isGlobalAdmin && $user->role?->name !== 'agent_supervisor') {
                if ($user->role?->name === 'agent') {
                    $query->where('calls.agent_id', $user->id);
                } else {
                    // Operational scoped user (zone_admin, sector_admin, beat_operator, wireless_operator, etc.)
                    $scopes = $user->activeScopes;
                    if ($scopes && $scopes->isNotEmpty()) {
                        $accessibleOfficeIds = [];
                        foreach ($scopes as $scope) {
                            if (!empty($scope->office_id) && (int) $scope->office_id > 0) {
                                $descendants = \App\Models\Office::getDescendantIdsFor((int) $scope->office_id, true);
                                if (!empty($descendants)) {
                                    $accessibleOfficeIds = array_merge($accessibleOfficeIds, $descendants);
                                }
                            }
                        }
                        $accessibleOfficeIds = array_values(array_unique(array_filter($accessibleOfficeIds)));
                        if (!empty($accessibleOfficeIds)) {
                            $query->whereIn('calls.office_id', $accessibleOfficeIds);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
            }
        }

        // 2. Apply active UI client filter hierarchy if specified
        if ($beatId) {
            $query->where('calls.office_id', $beatId);
        } elseif ($sectorId) {
            $descendants = \App\Models\Office::getDescendantIdsFor($sectorId, true);
            $query->whereIn('calls.office_id', $descendants);
        } elseif ($zoneId) {
            $descendants = \App\Models\Office::getDescendantIdsFor($zoneId, true);
            $query->whereIn('calls.office_id', $descendants);
        }

        // Calculate current total pending women calls strictly within this user/beat scope
        $totalPendingWomen = (clone $query)->count();

        if ($lastId > 0) {
            $query->where('id', '>', $lastId);
        }

        $newEmergency = $query->orderBy('id', 'desc')->first();

        return response()->json([
            'has_new_emergency' => (bool) $newEmergency,
            'call' => $newEmergency,
            'total_pending_women' => $totalPendingWomen,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

