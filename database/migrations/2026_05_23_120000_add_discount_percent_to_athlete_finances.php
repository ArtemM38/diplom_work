<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_finances', function (Blueprint $table) {
            if (! Schema::hasColumn('athlete_finances', 'discount_percent')) {
                $table->unsignedTinyInteger('discount_percent')->nullable()->after('balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('athlete_finances', function (Blueprint $table) {
            if (Schema::hasColumn('athlete_finances', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }
        });
    }
};
