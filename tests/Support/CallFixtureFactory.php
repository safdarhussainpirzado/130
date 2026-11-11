<?php

namespace Tests\Support;

use App\Models\Call;
use Carbon\Carbon;

/**
 * Reusable Call Fixture Factory for NHMP 130 CRM automated testing.
 * Provides deterministic call records across statuses, coordinates, and categories.
 */
class CallFixtureFactory
{
    /**
     * Create an in-memory Call model with valid default attributes.
     */
    public static function make(array $attributes = []): Call
    {
        $now = Carbon::now();
        $defaults = [
            'id' => rand(1000, 9999),
            'call_number' => sprintf('CRM-%s-%05d', date('Y'), rand(1, 99999)),
            'caller_name' => 'Synthetic Citizen '.rand(1, 100),
            'caller_number' => '0300'.str_pad((string) rand(1000000, 9999999), 7, '0'),
            'call_type_id' => 1,
            'call_sub_type_id' => 1,
            'status' => 'pending',
            'priority' => 1,
            'caller_lat' => 33.6844,
            'caller_lng' => 73.0479,
            'vehicle_no' => 'ICT-ABC-'.rand(100, 999),
            'details' => 'Synthetic incident report on M-2 KM 350 for automated test validation.',
            'created_at' => $now,
            'call_start_time' => $now,
            'call_pickup_time' => $now->copy()->addSeconds(10),
            'wait_time_seconds' => 10,
            'agent_call_duration' => 180,
        ];

        $call = new Call(array_merge($defaults, $attributes));
        $id = $attributes['id'] ?? $defaults['id'] ?? null;
        if ($id !== null) {
            $call->id = $id;
        }

        return $call;
    }

    /**
     * Create a Call model with emergency priority and coordinates.
     */
    public static function makeEmergency(array $attributes = []): Call
    {
        return self::make(array_merge([
            'call_type_id' => 1,
            'priority' => 1,
            'status' => 'pending',
            'details' => 'CRITICAL EMERGENCY: Multi-vehicle collision on M-2 KM 280.',
        ], $attributes));
    }

    /**
     * Create a Call model with Urdu Unicode text.
     */
    public static function makeUrdu(array $attributes = []): Call
    {
        return self::make(array_merge([
            'caller_name' => 'محمد رضوان',
            'details' => 'موٹروے ایم ٹو کلومیٹر 350 پر گاڑی کا ٹائر پھٹ گیا ہے، فوری مدد درکار ہے۔',
        ], $attributes));
    }

    /**
     * Create a Call model with completed status.
     */
    public static function makeCompleted(array $attributes = []): Call
    {
        $now = Carbon::now();

        return self::make(array_merge([
            'status' => 'completed',
            'call_end_time' => $now,
            'agent_call_duration' => 300,
        ], $attributes));
    }
}
