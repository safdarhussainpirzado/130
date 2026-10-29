<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CallService;
use Illuminate\Support\Facades\DB;

class CallSequenceTest extends TestCase
{
    public function test_generate_call_number_format_and_sequence(): void
    {
        $service = new CallService();
        $year = (int) date('Y');

        $num1 = $service->generateCallNumber();
        $num2 = $service->generateCallNumber();

        $this->assertMatchesRegularExpression("/^CRM-{$year}-\d{5,}$/", $num1);
        $this->assertMatchesRegularExpression("/^CRM-{$year}-\d{5,}$/", $num2);

        $seq1 = (int) substr($num1, strrpos($num1, '-') + 1);
        $seq2 = (int) substr($num2, strrpos($num2, '-') + 1);

        $this->assertEquals($seq1 + 1, $seq2, "Subsequent call numbers must be strictly sequential.");
    }
}
