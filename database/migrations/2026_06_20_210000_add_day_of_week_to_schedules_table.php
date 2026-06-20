<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('schedules', 'day_of_week')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedTinyInteger('day_of_week')->nullable()->after('initial_coach_id');
        });

        DB::table('schedules')
            ->whereNotNull('lesson_date')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($schedule) {
                DB::table('schedules')
                    ->where('id', $schedule->id)
                    ->update([
                        'day_of_week' => Carbon::parse($schedule->lesson_date)->dayOfWeekIso,
                    ]);
            });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `schedules` MODIFY `day_of_week` TINYINT UNSIGNED NOT NULL');
        } else {
            Schema::table('schedules', function (Blueprint $table) {
                $table->unsignedTinyInteger('day_of_week')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('schedules', 'day_of_week')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('day_of_week');
        });
    }
};
