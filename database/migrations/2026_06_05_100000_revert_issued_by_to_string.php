<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE athlete_documents MODIFY `issued_by` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // Откат не требуется.
    }
};
