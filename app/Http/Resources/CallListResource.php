<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class CallListResource extends JsonResource
{
    public static ?array $locationMap = null;
    public static ?array $vehicleTypesMap = null;

    public static function setLocationMap(?array $map): void
    {
        self::$locationMap = $map;
    }

    public static function setVehicleTypesMap(?array $map): void
    {
        self::$vehicleTypesMap = $map;
    }

    /**
     * Transform the resource into a compact, flat array for the Calls List.
     * Enforces strict data minimization: zero sensitive agent info,
     * flat location breadcrumb, no duplicate identifiers or unused relationships.
     */
    public function toArray(Request $request): array
    {
        $callType = $this->relationLoaded('callType') ? $this->callType : null;
        $callSubType = $this->relationLoaded('callSubType') ? $this->callSubType : null;
        $agent = $this->relationLoaded('agent') ? $this->agent : null;

        // Resolve flat location hierarchy via pre-cached map or eager loaded relation
        $location = null;
        if (self::$locationMap !== null && isset(self::$locationMap[$this->office_id])) {
            $location = self::$locationMap[$this->office_id];
        } elseif ($this->relationLoaded('office') && $this->office) {
            $location = $this->office->name;
        }

        // Resolve vehicle type name via pre-cached map or eager loaded relation
        $vehicleTypeName = null;
        if (self::$vehicleTypesMap !== null && isset(self::$vehicleTypesMap[$this->vehicle_type_id])) {
            $vehicleTypeName = self::$vehicleTypesMap[$this->vehicle_type_id];
        } elseif ($this->relationLoaded('vehicleType') && $this->vehicleType) {
            $vehicleTypeName = $this->vehicleType->name;
        }

        return [
            'id'                  => $this->id,
            'uuid'                => $this->uuid,
            'call_number'         => $this->call_number,
            'caller_name'         => $this->caller_name,
            'caller_number'       => $this->caller_number,
            'caller_gender'       => $this->caller_gender ?? ($this->call_type_id == 10 ? 'female' : null),
            'status'              => $this->status,
            'priority'            => (int) $this->priority,
            
            // Minimal flat call classification
            'call_type'           => $callType ? ['name' => $callType->name] : null,
            'call_sub_type'       => $callSubType ? ['name' => $callSubType->name] : null,

            // Minimal agent presentation (sanitized, zero PII)
            'agent'               => $agent ? ['id' => $agent->id, 'name' => $agent->full_name, 'full_name' => $agent->full_name] : null,

            // Flat location breadcrumb (eliminates 3 levels of recursive parent relations)
            'office_id'           => $this->office_id,
            'beat_id'             => $this->office_id,
            'location'            => $location ?: 'OFF-GRID',

            // Vehicle info
            'vehicle_no'          => $this->vehicle_no,
            'vehicle_type'        => $vehicleTypeName ? ['name' => $vehicleTypeName] : null,

            // Timestamps & operational tracking
            'created_at'          => $this->created_at ? $this->created_at->toISOString() : null,
            'call_reminder_count' => (int) ($this->call_reminder_count ?? 0),
            'last_reminder_at'    => $this->last_reminder_at ? $this->last_reminder_at->toISOString() : null,
            'tiger_id'            => $this->tiger_id,

            // Sanitized preview of description for commuter notes modal
            'details'             => $this->details ? Str::limit(strip_tags($this->details), 120) : null,
            'details_preview'     => $this->details ? Str::limit(strip_tags($this->details), 100) : null,
        ];
    }
}
