<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE athletes MODIFY school_name TEXT NULL');
            DB::statement('ALTER TABLE athletes MODIFY kindergarten_name TEXT NULL');
            DB::statement('ALTER TABLE athletes MODIFY work_place TEXT NULL');
            DB::statement('ALTER TABLE athletes MODIFY work_position TEXT NULL');
            DB::statement('ALTER TABLE events MODIFY name TEXT NOT NULL');
            DB::statement('ALTER TABLE events MODIFY event_place TEXT NULL');
            DB::statement('ALTER TABLE locations MODIFY address TEXT NULL');
            DB::statement("ALTER TABLE event_hosts MODIFY city TEXT NULL");

            DB::statement("ALTER TABLE `groups` MODIFY `type` ENUM(
                'Учебная',
                'Семинар',
                'Аттестация',
                'Спортивные сборы',
                'Соревнования',
                'Интенсивные тренировки',
                'Индивидуальные тренировки'
            ) NOT NULL DEFAULT 'Учебная'");

            DB::statement("ALTER TABLE events MODIFY `status` ENUM('planned', 'completed') NOT NULL DEFAULT 'planned'");

            DB::statement("ALTER TABLE athlete_documents MODIFY `type` ENUM('medical', 'insurance', 'identity') NOT NULL");

            if (Schema::hasColumn('portfolio_achievements', 'event_period')) {
                DB::statement('UPDATE portfolio_achievements SET event_period = NULL WHERE event_period IS NOT NULL AND event_period NOT REGEXP \'^[0-9]{4}-[0-9]{2}-[0-9]{2}$\'');
                DB::statement('ALTER TABLE portfolio_achievements MODIFY event_period DATE NULL');
            }
        }
    }

    public function down(): void
    {
        // Откат типов колонок не выполняется — только вперёд.
    }
};
