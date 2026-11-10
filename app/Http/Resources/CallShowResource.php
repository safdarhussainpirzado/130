<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallShowResource extends JsonResource
{
    /**
     * Transform the resource into an array for the detailed Call Show endpoint.
     * Loads full authorized details, remarks, location metadata, relationships,
     * status transitions, and reminder history on demand.
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isPrivileged = $user && in_array($user->role?->name, ['super_admin', 'operation_admin', 'agent_supervisor']);

        $beat = $this->office;
        $sector = ($beat && $beat->type === 'beat') ? $beat->parent : ($beat && $beat->type === 'sector' ? $beat : null);
        $zone = $sector ? $sector->parent : ($beat && $beat->type === 'zone' ? $beat : null);

        return [
            'id' => $this->id,
            'call_number' => $this->call_number,
            'caller_name' => $this->caller_name,
            'caller_number' => $this->caller_number,
            'status' => $this->status,
            'priority' => (int) $this->priority,
            
            // Full details & location
            'details' => $this->details,
            'location_details' => $this->location_details,
            'caller_lat' => $this->caller_lat,
            'caller_lng' => $this->caller_lng,
            'carriageway_id' => $this->carriageway_id,
            'carriageway' => $this->carriageway ? [
                'id' => $this->carriageway->id,
                'road' => $this->carriageway->road,
                'road_name' => $this->carriageway->road_name,
                'road_short' => $this->carriageway->road_short,
            ] : null,
            'km_marker_text' => $this->km_marker_text,

            // Call Type & Sub Type
            'call_type' => $this->callType ? [
                'id' => $this->callType->id,
                'name' => $this->callType->name,
                'category' => $this->callType->category,
                'icon' => $this->callType->icon,
            ] : null,
            'call_type_id' => $this->call_type_id,
            'call_sub_type' => $this->callSubType ? [
                'id' => $this->callSubType->id,
                'name' => $this->callSubType->name,
                'icon' => $this->callSubType->icon,
                'priority' => $this->callSubType->priority,
            ] : null,
            'call_sub_type_id' => $this->call_sub_type_id,

            // Agent: Full name and ID. If privileged admin, username & designation included, but never passwords/tokens/MFA secrets
            'agent' => $this->agent ? [
                'id' => $this->agent->id,
                'full_name' => $this->agent->full_name,
                'name' => $this->agent->full_name,
                'username' => $isPrivileged ? $this->agent->username : null,
                'designation' => $this->agent->designation?->name,
            ] : null,
            'agent_id' => $this->agent_id,

            // Office hierarchy
            'office' => $beat ? [
                'id' => $beat->id,
                'name' => $beat->name,
                'type' => $beat->type,
                'beat' => $beat->name,
                'sector' => $sector?->name,
                'zone' => $zone?->name,
                'parent' => $sector ? [
                    'id' => $sector->id,
                    'name' => $sector->name,
                    'parent' => $zone ? [
                        'id' => $zone->id,
                        'name' => $zone->name,
                    ] : null,
                ] : null,
            ] : null,
            'office_id' => $this->office_id,
            'beat_id' => $this->office_id,

            // Vehicle
            'vehicle_no' => $this->vehicle_no,
            'vehicle_type' => $this->vehicleType ? [
                'id' => $this->vehicleType->id,
                'name' => $this->vehicleType->name,
                'color_hex' => $this->vehicleType->color_hex,
            ] : null,
            'vehicle_type_id' => $this->vehicle_type_id,

            // Patrol / Tiger Details
            'tiger_id' => $this->tiger_id,
            'patrol_officer_id' => $this->patrol_officer_id,

            // Operational Remarks
            'pending_remarks' => $this->pending_remarks,
            'inprogress_remarks' => $this->inprogress_remarks,
            'tiger_reached_remarks' => $this->tiger_reached_remarks,
            'completed_remarks' => $this->completed_remarks,
            'cancelled_remarks' => $this->cancelled_remarks,

            // Durations & SLA Timing
            'call_start_time' => $this->call_start_time ? $this->call_start_time->toISOString() : null,
            'call_pickup_time' => $this->call_pickup_time ? $this->call_pickup_time->toISOString() : null,
            'call_end_time' => $this->call_end_time ? $this->call_end_time->toISOString() : null,
            'wait_time_seconds' => $this->wait_time_seconds,
            'agent_call_duration' => $this->agent_call_duration,
            'response_time_sec' => $this->response_time_sec,
            'tiger_response_time_sec' => $this->tiger_response_time_sec,
            'assistance_duration_sec' => $this->assistance_duration_sec,
            'resolution_time_sec' => $this->resolution_time_sec,
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,

            // Reminders
            'is_reminder_call' => (bool) $this->is_reminder_call,
            'call_reminder_count' => (int) ($this->call_reminder_count ?? 0),
            'last_reminder_at' => $this->last_reminder_at ? $this->last_reminder_at->toISOString() : null,

            // Status transitions history
            'status_history' => $this->whenLoaded('statusHistory', function () {
                return $this->statusHistory->map(fn($sh) => [
                    'id' => $sh->id,
                    'old_status' => $sh->old_status,
                    'new_status' => $sh->new_status,
                    'remarks' => $sh->remarks,
                    'changed_by' => $sh->user?->full_name ?? $sh->changedBy?->full_name ?? ($sh->user?->username ?? ($sh->changedBy?->username ?? 'System')),
                    'created_at' => $sh->created_at ? $sh->created_at->toISOString() : null,
                ]);
            }),
        ];
    }
}
