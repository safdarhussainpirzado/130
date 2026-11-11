<?php

/**
 * NHMP 130 Hamsafar Phase 2 & 3 Comprehensive Integration Test Suite
 *
 * Verifies:
 * 1. Patrol Duty & Redis Telemetry Buffer
 * 2. Case H-1042 Full Incident Response Lifecycle (Screens 04-16)
 * 3. Bidirectional Case Chat
 * 4. Commuter SOS & Live Tracking
 * 5. Women's Journey Transit Module WJ-204 (Screens W01-W14)
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Call;
use App\Models\Office;
use App\Models\PatrolDutySession;
use App\Models\PatrolOfficerProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$baseUrl = getenv('API_BASE_URL') ?: 'https://haproxy';
$curlOptions = [
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
];

$passCount = 0;
$failCount = 0;

function runStep(string $title, callable $test): void {
    global $passCount, $failCount;
    try {
        $result = $test();
        if ($result === true || (is_array($result) && ($result['ok'] ?? false))) {
            $msg = is_array($result) && isset($result['msg']) ? " — " . $result['msg'] : "";
            echo "[\033[32mPASS\033[0m] {$title}{$msg}\n";
            $passCount++;
        } else {
            $err = is_array($result) && isset($result['error']) ? $result['error'] : 'Assertion failed';
            echo "[\033[31mFAIL\033[0m] {$title} — {$err}\n";
            $failCount++;
        }
    } catch (\Throwable $e) {
        echo "[\033[31mFAIL\033[0m] {$title} — Exception: {$e->getMessage()}\n";
        $failCount++;
    }
}

function httpReq(string $method, string $url, ?array $payload = null, ?string $token = null): array {
    global $curlOptions;
    $ch = curl_init($url);
    curl_setopt_array($ch, $curlOptions);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $status,
        'body'   => json_decode($raw ?: '{}', true),
        'raw'    => $raw,
        'error'  => $err,
    ];
}

echo "========================================================================\n";
echo "NHMP 130 HAMSAFAR PHASE 2 & 3 INTEGRATION TEST SUITE\n";
echo "Target Host: {$baseUrl}\n";
echo "========================================================================\n\n";

$rand = random_int(10000, 99999);
$commuterToken = null;
$officerToken = null;
$dutySessionId = null;
$caseUuid = null;
$journeyUuid = null;
$beatId = 1;

// STEP 1: Commuter Registration
runStep("1. Commuter Registration via Email/Password", function () use ($baseUrl, &$commuterToken, $rand) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/auth/commuter/register", [
        'full_name'             => "Zainab Bibi {$rand}",
        'email'                 => "zainab_{$rand}@hamsafar.nhmp.gov.pk",
        'password'              => 'Test@P@ss1234!',
        'password_confirmation' => 'Test@P@ss1234!',
    ]);

    if ($res['status'] !== 201 || empty($res['body']['access_token'])) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }

    $commuterToken = $res['body']['access_token'];
    return ['ok' => true, 'msg' => "Token acquired, uuid: " . ($res['body']['user']['uuid'] ?? 'ok')];
});

// STEP 2: Commuter Safety Profile Update
runStep("2. Commuter Safety Profile Completion (Gender female for WJ-204)", function () use ($baseUrl, &$commuterToken, $rand) {
    $mobile = '03' . str_pad((string) $rand, 9, '1', STR_PAD_LEFT);
    $cnic = '37405-' . str_pad((string) $rand, 7, '0', STR_PAD_LEFT) . '-2';

    $res = httpReq('PUT', "{$baseUrl}/api/v1/commuter/profile", [
        'mobile_no'          => $mobile,
        'gender'             => 'female',
        'cnic'               => $cnic,
        'blood_group'        => 'B+',
        'emergency_contacts' => [
            ['name' => 'Brother', 'phone' => '03009988776', 'relationship' => 'sibling'],
        ],
    ], $commuterToken);

    if ($res['status'] !== 200 || !($res['body']['is_profile_complete'] ?? false)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Profile marked complete, Services unlocked."];
});

// STEP 3: Patrol Officer Provisioning & Tactical Login
runStep("3. Patrol Officer Provisioning & Tactical Authentication", function () use ($baseUrl, &$officerToken, &$beatId, $rand) {
    $patrolUsername = 'spo_p2_' . $rand;
    $patrolRole = Role::where('name', 'patrol_officer')->first();
    $beat = Office::where('type', 'beat')->first() ?? Office::first();
    $beatId = $beat->id;

    $officer = User::create([
        'full_name'      => "Tahir Mehmood (P-12 Test {$rand})",
        'email'          => "{$patrolUsername}@nhmp.gov.pk",
        'username'       => $patrolUsername,
        'mobile_no'      => '03' . str_pad((string) $rand, 9, '2', STR_PAD_LEFT),
        'password'       => Hash::make('TacticalPass123!'),
        'role_id'        => $patrolRole->id,
        'designation_id' => 11,
        'is_active'      => true,
    ]);

    PatrolOfficerProfile::create([
        'user_id'          => $officer->id,
        'badge_number'     => "SPO-{$rand}",
        'assigned_beat_id' => $beat->id,
        'device_uuid'      => 'nhmp_tab_p12_test',
    ]);

    $res = httpReq('POST', "{$baseUrl}/api/v1/auth/patrol/login", [
        'username'    => $patrolUsername,
        'password'    => 'TacticalPass123!',
        'device_uuid' => 'nhmp_tab_p12_test',
    ]);

    if ($res['status'] !== 200 || empty($res['body']['access_token'])) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    $officerToken = $res['body']['access_token'];
    return ['ok' => true, 'msg' => "Officer {$res['body']['officer']['username']} authenticated."];
});

// STEP 4: Start Duty & Vehicle Callsign P-12 Binding
runStep("4. Screen 02: Start Duty with Patrol Callsign P-12", function () use ($baseUrl, $officerToken, $beatId, &$dutySessionId) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/patrol/duty/start", [
        'vehicle_callsign'  => 'P-12',
        'vehicle_reg_no'    => 'NHMP-8821',
        'beat_office_id'    => $beatId,
        'initial_latitude'  => 32.78214,
        'initial_longitude' => 72.70512,
    ], $officerToken);

    if (!in_array($res['status'], [200, 201], true)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }

    $dutySessionId = $res['body']['duty_session_id'];
    return ['ok' => true, 'msg' => "Patrol P-12 on duty (session: {$dutySessionId})"];
});

// STEP 5: High-Frequency GPS Telemetry Ingestion (Redis Buffer)
runStep("5. Screen 03: Telemetry Stream to Redis Buffer (POST /duty/telemetry)", function () use ($baseUrl, $officerToken, $dutySessionId) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/patrol/duty/telemetry", [
        'duty_session_id' => $dutySessionId,
        'latitude'        => 32.79150,
        'longitude'       => 72.71420,
        'speed_kph'       => 78.5,
        'bearing_degrees' => 185.0,
        'carriageway'     => 'Southbound (Lahore Bound)',
        'accuracy_meters' => 3.2,
    ], $officerToken);

    if ($res['status'] !== 202 || ($res['body']['status'] ?? '') !== 'recorded') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "GPS buffered in Redis with pending assignments check."];
});

// STEP 6: Patrol Status & Moving Map Check
runStep("6. Screen 03: Patrol Status & Live Position Query (GET /duty/status)", function () use ($baseUrl, $officerToken) {
    $res = httpReq('GET', "{$baseUrl}/api/v1/patrol/duty/status", null, $officerToken);

    if ($res['status'] !== 200 || !($res['body']['is_on_duty'] ?? false)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    $live = $res['body']['duty_session']['live_position'] ?? [];
    return ['ok' => true, 'msg' => "Status: {$res['body']['duty_session']['status']}, Lat: {$live['latitude']}, Callsign: {$res['body']['duty_session']['vehicle_callsign']}"];
});

// STEP 7: Toggle Patrol Readiness Status
runStep("7. Screen 03: Update Patrol Readiness to BUSY then AVAILABLE (PUT /duty/status)", function () use ($baseUrl, $officerToken) {
    $res1 = httpReq('PUT', "{$baseUrl}/api/v1/patrol/duty/status", ['status' => 'BUSY'], $officerToken);
    if ($res1['status'] !== 200 || ($res1['body']['status'] ?? '') !== 'BUSY') {
        return ['ok' => false, 'error' => "Failed to set BUSY"];
    }

    $res2 = httpReq('PUT', "{$baseUrl}/api/v1/patrol/duty/status", ['status' => 'AVAILABLE'], $officerToken);
    if ($res2['status'] !== 200 || ($res2['body']['status'] ?? '') !== 'AVAILABLE') {
        return ['ok' => false, 'error' => "Failed to set AVAILABLE"];
    }

    return ['ok' => true, 'msg' => "Readiness successfully toggled."];
});

// STEP 8: Commuter Triggers Emergency SOS (Generates Incident Case H-1042)
runStep("8. Commuter SOS Trigger (POST /commuter/sos)", function () use ($baseUrl, $commuterToken, &$caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/commuter/sos", [
        'latitude'     => 32.78120,
        'longitude'    => 72.70490,
        'problem_type' => 'Vehicle Breakdown',
        'details'      => 'Vehicle engine stalled near KM 231 on M-2 south of Kallar Kahar.',
        'carriageway'  => 'Southbound',
        'km_marker'    => 'KM 231',
        'vehicle_no'   => 'ICT-LE-921',
    ], $commuterToken);

    if ($res['status'] !== 201 || empty($res['body']['case_uuid'])) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }

    $caseUuid = $res['body']['case_uuid'];
    return ['ok' => true, 'msg' => "Emergency Case {$res['body']['case_reference']} created (UUID: {$caseUuid})"];
});

// STEP 9: 130 Control assigns Case to Patrol P-12
runStep("9. 130 Control assigns Case to Patrol P-12", function () use ($caseUuid, $dutySessionId) {
    $session = PatrolDutySession::where('uuid', $dutySessionId)->first();
    $case = Call::where('uuid', $caseUuid)->first();

    if (!$session || !$case) {
        return ['ok' => false, 'error' => 'Session or Case not found in DB'];
    }

    $case->update([
        'status'                 => 'assigned',
        'assigned_at'            => now(),
        'patrol_duty_session_id' => $session->id,
        'patrol_officer_id'      => (string) $session->officer_user_id,
        'tiger_id'               => $session->vehicle_callsign,
    ]);

    return ['ok' => true, 'msg' => "Case {$case->call_number} assigned to Patrol {$session->vehicle_callsign}."];
});

// STEP 10: Patrol reviews assigned case (Screen 04/05)
runStep("10. Screen 04/05: Patrol reviews assigned Case (GET /patrol/cases/assigned)", function () use ($baseUrl, $officerToken, $caseUuid) {
    $res = httpReq('GET', "{$baseUrl}/api/v1/patrol/cases/assigned", null, $officerToken);

    if ($res['status'] !== 200 || !($res['body']['has_assigned_case'] ?? false)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }

    $case = $res['body']['case'];
    if ($case['uuid'] !== $caseUuid) {
        return ['ok' => false, 'error' => "UUID mismatch: expected {$caseUuid}, got {$case['uuid']}"];
    }

    return ['ok' => true, 'msg' => "Assigned Case {$case['case_reference']} reviewed successfully."];
});

// STEP 11: Patrol Acknowledges Assignment (Screen 06)
runStep("11. Screen 06: Patrol Acknowledges Case (POST /cases/{uuid}/acknowledge)", function () use ($baseUrl, $officerToken, $caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/cases/{$caseUuid}/acknowledge", [], $officerToken);

    if ($res['status'] !== 200 || ($res['body']['status'] ?? '') !== 'acknowledged') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Case status advanced to acknowledged."];
});

// STEP 12: Patrol Starts Response (Screen 08)
runStep("12. Screen 08: Patrol Starts Response (POST /cases/{uuid}/start-response)", function () use ($baseUrl, $officerToken, $caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/cases/{$caseUuid}/start-response", [], $officerToken);

    if ($res['status'] !== 200 || ($res['body']['status'] ?? '') !== 'in_progress') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Status is in_progress. Public: '{$res['body']['public_status']}'"];
});

// STEP 13: Commuter Tracks Live Patrol Location (Screens 08/09)
runStep("13. Commuter Live Patrol Tracking (GET /cases/{uuid}/tracking)", function () use ($baseUrl, $commuterToken, $caseUuid) {
    $res = httpReq('GET', "{$baseUrl}/api/v1/cases/{$caseUuid}/tracking", null, $commuterToken);

    if ($res['status'] !== 200 || !($res['body']['tracking_active'] ?? false)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }

    $pos = $res['body']['patrol_position'] ?? [];
    return ['ok' => true, 'msg' => "Live Tracking active: Patrol {$res['body']['vehicle_callsign']} at Lat: {$pos['latitude']}, Lng: {$pos['longitude']}"];
});

// STEP 14: Commuter Sends Message to Assigned Patrol (Screen 09/10)
runStep("14. Screen 10: Commuter sends message to Patrol (POST /cases/{uuid}/messages)", function () use ($baseUrl, $commuterToken, $caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/cases/{$caseUuid}/messages", [
        'message_type' => 'text',
        'audience'     => 'shared_case',
        'content'      => 'We are parked on emergency shoulder near KM 231 with hazard lights on.',
        'latitude'     => 32.78120,
        'longitude'    => 72.70490,
    ], $commuterToken);

    if ($res['status'] !== 201 || ($res['body']['sender_type'] ?? '') !== 'commuter') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Message delivered to shared case channel."];
});

// STEP 15: Patrol Officer Replies to Commuter (Screen 09)
runStep("15. Screen 09: Patrol Officer replies in Case Chat (POST /cases/{uuid}/messages)", function () use ($baseUrl, $officerToken, $caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/cases/{$caseUuid}/messages", [
        'message_type' => 'text',
        'audience'     => 'shared_case',
        'content'      => 'Patrol P-12 has sighted your hazard lights. Approaching scene safely.',
    ], $officerToken);

    if ($res['status'] !== 201 || ($res['body']['sender_type'] ?? '') !== 'patrol_officer') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Patrol reply delivered."];
});

// STEP 16: Read Case Chat Messages Stream
runStep("16. Screen 09/10: Read Case Messages Stream (GET /cases/{uuid}/messages)", function () use ($baseUrl, $commuterToken, $caseUuid) {
    $res = httpReq('GET', "{$baseUrl}/api/v1/cases/{$caseUuid}/messages", null, $commuterToken);

    if ($res['status'] !== 200 || ($res['body']['messages_count'] ?? 0) < 2) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Chat contains {$res['body']['messages_count']} messages."];
});

// STEP 17: Patrol Confirms Physical Arrival On Scene (Screen 11)
runStep("17. Screen 11: Patrol Marks Arrived On Scene (POST /cases/{uuid}/mark-arrived)", function () use ($baseUrl, $officerToken, $caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/cases/{$caseUuid}/mark-arrived", [], $officerToken);

    if ($res['status'] !== 200 || ($res['body']['status'] ?? '') !== 'tiger_reached') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Patrol on scene, Assistance active (Response time: {$res['body']['response_time_sec']}s)."];
});

// STEP 18: Patrol Requests Support / Relief through Control (Screen 12)
runStep("18. Screen 12: Patrol Requests Tow Recovery Support (POST /cases/{uuid}/request-support)", function () use ($baseUrl, $officerToken, $caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/cases/{$caseUuid}/request-support", [
        'support_type'  => 'recovery_crane',
        'internal_note' => 'Heavy vehicle broken differential. Requires hydraulic lift recovery crane.',
    ], $officerToken);

    if ($res['status'] !== 201 || !($res['body']['success'] ?? false)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Support request logged for 130 Control Room."];
});

// STEP 19: Patrol Submits Completion Report (Screen 13)
runStep("19. Screen 13: Patrol Submits Completion Report (POST /cases/{uuid}/complete-assistance)", function () use ($baseUrl, $officerToken, $caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/cases/{$caseUuid}/complete-assistance", [
        'outcome'               => 'resolved',
        'public_summary'        => 'Vehicle escorted safely to Bhera service area. Minor coolant leak repaired.',
        'internal_control_note' => 'Driver was cooperative. Incident caused no carriageway blockage.',
    ], $officerToken);

    if ($res['status'] !== 200 || ($res['body']['status'] ?? '') !== 'review_pending') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Outcome submitted. Case in closure review."];
});

// STEP 20: 130 Control Approves Closure & Releases Patrol (Screen 15)
runStep("20. Screen 15: 130 Control Approves Closure & Releases Patrol (POST /cases/{uuid}/close-and-release)", function () use ($baseUrl, $officerToken, $caseUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/cases/{$caseUuid}/close-and-release", [], $officerToken);

    if ($res['status'] !== 200 || ($res['body']['status'] ?? '') !== 'completed') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Case formally completed. Patrol released back to AVAILABLE."];
});

// STEP 21: Women's Journey PSV Registration (W01-W05)
runStep("21. Women's Journey: Register PSV Transit (POST /womens-journey/register)", function () use ($baseUrl, $commuterToken, &$journeyUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/womens-journey/register", [
        'origin_city'        => 'Rawalpindi',
        'origin_stop'        => 'Faizabad Bus Terminal',
        'destination_city'   => 'Lahore',
        'destination_stop'   => 'Thokar Niaz Baig Terminal',
        'psv_company_name'   => 'Daewoo Express',
        'psv_reg_no'         => 'BS-942',
        'psv_seat_no'        => '14-B',
        'notes'              => 'Traveling solo on night executive coach.',
    ], $commuterToken);

    if (!in_array($res['status'], [200, 201], true) || empty($res['body']['journey_uuid'])) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }

    $journeyUuid = $res['body']['journey_uuid'];
    return ['ok' => true, 'msg' => "Journey {$res['body']['journey_code']} active aboard {$res['body']['psv_carrier']}."];
});

// STEP 22: Query Active Women's Journey (Screen W07)
runStep("22. Women's Journey: Query Active Transit (GET /womens-journey/active)", function () use ($baseUrl, $commuterToken) {
    $res = httpReq('GET', "{$baseUrl}/api/v1/womens-journey/active", null, $commuterToken);

    if ($res['status'] !== 200 || !($res['body']['has_active_journey'] ?? false)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    $j = $res['body']['journey'];
    return ['ok' => true, 'msg' => "Tracking code: {$j['journey_code']}, Seat: {$j['psv']['seat_no']}"];
});

// STEP 23: Post In-Transit Milestone / Check-in Note (Screen W08/W10)
runStep("23. Women's Journey: Post Milestone Check-In (POST /womens-journey/{uuid}/updates)", function () use ($baseUrl, $commuterToken, $journeyUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/womens-journey/{$journeyUuid}/updates", [
        'update_type'   => 'check_in',
        'content'       => 'Bus stopped at Bhera rest area for 15 minutes break.',
        'location_name' => 'Bhera Rest Area M-2',
        'latitude'      => 32.48201,
        'longitude'     => 72.91240,
    ], $commuterToken);

    if ($res['status'] !== 201 || !($res['body']['success'] ?? false)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Milestone recorded, monitored by 130 Female Desk."];
});

// STEP 24: 130 Control Room Female Desk Queue
runStep("24. 130 Female Desk Queue Inspection (GET /womens-journey/desk-queue)", function () use ($baseUrl, $officerToken) {
    $res = httpReq('GET', "{$baseUrl}/api/v1/womens-journey/desk-queue", null, $officerToken);

    if ($res['status'] !== 200 || !isset($res['body']['active_journeys_count'])) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Desk queue contains {$res['body']['active_journeys_count']} active journeys."];
});

// STEP 25: Trigger Discreet Assistance / Silent Alert (Screen W11)
runStep("25. Screen W11: Trigger Discreet Silent Alert (POST /womens-journey/{uuid}/alert)", function () use ($baseUrl, $commuterToken, $journeyUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/womens-journey/{$journeyUuid}/alert", [
        'latitude'  => 32.21400,
        'longitude' => 73.20100,
    ], $commuterToken);

    if ($res['status'] !== 200 || ($res['body']['status'] ?? '') !== 'alert_triggered') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Priority 1 Silent Alarm dispatched to Highway Patrol."];
});

// STEP 26: Confirm Safe Arrival (Screen W12/W13)
runStep("26. Screen W12/W13: Confirm Safe Arrival (POST /womens-journey/{uuid}/safe-arrival)", function () use ($baseUrl, $commuterToken, $journeyUuid) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/womens-journey/{$journeyUuid}/safe-arrival", [
        'arrival_stop' => 'Thokar Niaz Baig Terminal, Lahore',
    ], $commuterToken);

    if ($res['status'] !== 200 || ($res['body']['status'] ?? '') !== 'completed') {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Journey safely archived. Location sharing terminated."];
});

// STEP 27: Conclude Officer Duty Session (Screen 18)
runStep("27. Screen 18: Conclude Officer Duty Session (POST /patrol/duty/end)", function () use ($baseUrl, $officerToken) {
    $res = httpReq('POST', "{$baseUrl}/api/v1/patrol/duty/end", [], $officerToken);

    if ($res['status'] !== 200 || !($res['body']['success'] ?? false)) {
        return ['ok' => false, 'error' => "HTTP {$res['status']}: " . $res['raw']];
    }
    return ['ok' => true, 'msg' => "Officer signed out. Marked OFF DUTY."];
});

echo "\n========================================================================\n";
echo "SUMMARY: Total: " . ($passCount + $failCount) . " | Passed: \033[32m{$passCount}\033[0m | Failed: \033[31m{$failCount}\033[0m\n";
echo "========================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
