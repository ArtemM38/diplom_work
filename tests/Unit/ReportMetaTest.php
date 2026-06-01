<?php

namespace Tests\Unit;

use App\Support\ReportMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_at_includes_irkutsk_label(): void
    {
        $formatted = ReportMeta::generatedAtFormatted();

        $this->assertStringContainsString('Иркутск', $formatted);
    }
}
