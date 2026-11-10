<?php

namespace Tests\Unit\Validation;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Comprehensive Positive and Negative Input Validation Tests for Call submissions.
 * Tests phone numbers, boundary coordinates, statuses, priorities, Urdu text, and injection payloads.
 */
class CallInputValidationTest extends TestCase
{
    /**
     * Reusable validation rules replicating Call creation and update requests.
     */
    protected function getCallRules(): array
    {
        return [
            'caller_name' => ['required', 'string', 'max:150'],
            'caller_number' => ['required', 'string', 'regex:/^03[0-9]{9}$/'],
            'call_type_id' => ['required', 'integer'],
            'call_sub_type_id' => ['nullable', 'integer'],
            'priority' => ['required', 'integer', 'in:1,2,3'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,tiger_reached,completed,cancelled'],
            'caller_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'caller_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'details' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Positive Scenario: Valid Pakistani phone number formats pass validation.
     */
    #[DataProvider('validPhoneProvider')]
    public function test_valid_phone_numbers_pass_validation(string $phone): void
    {
        $validator = Validator::make(['caller_number' => $phone], [
            'caller_number' => ['required', 'string', 'regex:/^03[0-9]{9}$/'],
        ]);

        $this->assertTrue($validator->passes(), "Expected valid phone '{$phone}' to pass validation.");
    }

    public static function validPhoneProvider(): array
    {
        return [
            'Mobilink/Jazz' => ['03001234567'],
            'Zong' => ['03139876543'],
            'Warid' => ['03215555555'],
            'Ufone' => ['03331112233'],
            'Telenor' => ['03457778899'],
            'SCO' => ['03551234567'],
        ];
    }

    /**
     * Negative Scenario: Invalid, malformed, or injection phone numbers fail validation.
     */
    #[DataProvider('invalidPhoneProvider')]
    public function test_invalid_phone_numbers_fail_validation(mixed $phone): void
    {
        $validator = Validator::make(['caller_number' => $phone], [
            'caller_number' => ['required', 'string', 'regex:/^03[0-9]{9}$/'],
        ]);

        $this->assertFalse($validator->passes(), "Expected invalid phone '{$phone}' to fail validation.");
        $this->assertArrayHasKey('caller_number', $validator->errors()->toArray());
    }

    public static function invalidPhoneProvider(): array
    {
        return [
            'Empty string' => [''],
            'Too short' => ['030012345'],
            'Too long' => ['0300123456789'],
            'Non-mobile prefix' => ['0512345678'],
            'Letters in number' => ['03001234abc'],
            'Special characters' => ['0300-1234567'],
            'SQL injection payload' => ["0300' OR 1=1 --"],
            'XSS script payload' => ['<script>03001234567</script>'],
        ];
    }

    /**
     * Positive Scenario: Coordinates within valid geographic latitude/longitude boundaries.
     */
    public function test_valid_coordinates_pass_validation(): void
    {
        $data = [
            'caller_lat' => 33.6844, // Islamabad
            'caller_lng' => 73.0479,
        ];

        $validator = Validator::make($data, [
            'caller_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'caller_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->assertTrue($validator->passes());
    }

    /**
     * Negative Scenario: Coordinates exceeding physical boundaries (-90..90, -180..180) are rejected.
     */
    #[DataProvider('invalidCoordinatesProvider')]
    public function test_out_of_range_coordinates_fail_validation(mixed $lat, mixed $lng): void
    {
        $validator = Validator::make(['caller_lat' => $lat, 'caller_lng' => $lng], [
            'caller_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'caller_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->assertFalse($validator->passes());
    }

    public static function invalidCoordinatesProvider(): array
    {
        return [
            'Latitude > 90' => [91.5, 73.0479],
            'Latitude < -90' => [-95.0, 73.0479],
            'Longitude > 180' => [33.6844, 185.0],
            'Longitude < -180' => [33.6844, -181.0],
            'String non-numeric' => ['abc', 'xyz'],
        ];
    }

    /**
     * Positive Scenario: Urdu Unicode text in call details is accepted and preserved.
     */
    public function test_urdu_unicode_text_passes_validation(): void
    {
        $urduDetails = 'موٹروے ایم ون پر صوابی انٹرچینج کے قریب ٹریفک کی روانی بحال کر دی گئی ہے۔';
        $validator = Validator::make(['details' => $urduDetails], [
            'details' => ['required', 'string', 'max:5000'],
        ]);

        $this->assertTrue($validator->passes());
        $this->assertEquals($urduDetails, $validator->validated()['details']);
    }

    /**
     * Negative Scenario: Exceeding maximum allowed character length fails validation.
     */
    public function test_details_exceeding_max_length_fails_validation(): void
    {
        $oversized = str_repeat('A', 5001);
        $validator = Validator::make(['details' => $oversized], [
            'details' => ['required', 'string', 'max:5000'],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('details', $validator->errors()->toArray());
    }

    /**
     * Positive & Negative Scenarios: Operational call priorities (1, 2, 3 only).
     */
    public function test_priority_validation_accepts_valid_and_rejects_invalid(): void
    {
        // Valid
        foreach ([1, 2, 3] as $p) {
            $v = Validator::make(['priority' => $p], ['priority' => ['required', 'in:1,2,3']]);
            $this->assertTrue($v->passes(), "Priority {$p} should be valid.");
        }

        // Invalid
        foreach ([0, 4, -1, 'urgent', ''] as $bad) {
            $v = Validator::make(['priority' => $bad], ['priority' => ['required', 'in:1,2,3']]);
            $this->assertFalse($v->passes(), "Priority '{$bad}' should be rejected.");
        }
    }
}
