<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('balance_top_up_codes');
    }

    public function down(): void
    {
        // Intentionally empty — top-up codes feature removed.
    }
};
