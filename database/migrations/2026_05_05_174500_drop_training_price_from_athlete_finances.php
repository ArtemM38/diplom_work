<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_finances', function (Blueprint $table) {
            $table->dropColumn('training_price');
        });
    }

    public function down(): void
    {
        Schema::table('athlete_finances', function (Blueprint $table) {
            $table->decimal('training_price', 10, 2)->default(0);
        });
    }
};
