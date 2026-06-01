<?php

namespace Tests\Unit;

use App\Support\DateFormatter;
use Tests\TestCase;

class DateFormatterTest extends TestCase
{
    public function test_to_date_time_avoids_double_time_specification(): void
    {
        $dateTime = DateFormatter::toDateTime('2026-06-02 00:00:00', '20:45:00');

        $this->assertSame('2026-06-02 20:45:00', $dateTime->format('Y-m-d H:i:s'));
    }
}
