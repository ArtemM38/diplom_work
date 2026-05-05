<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_group', function (Blueprint $table) {
            if (!Schema::hasColumn('athlete_group', 'training_price')) {
                $table->decimal('training_price', 10, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('athlete_group', function (Blueprint $table) {
            if (Schema::hasColumn('athlete_group', 'training_price')) {
                $table->dropColumn('training_price');
            }
        });
    }
};
