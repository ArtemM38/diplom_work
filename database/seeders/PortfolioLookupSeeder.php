<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PortfolioLookupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $eventTypes = [
            'Семинар',
            'Аттестация',
            'Показательные выступления',
            'Спортивные сборы',
            'Соревнования',
            'Интенсивные тренировки',
        ];

        $eventLevels = [
            'Городской',
            'Региональный',
            'Окружной',
            'Всероссийский',
            'Международный',
        ];

        foreach ($eventTypes as $name) {
            DB::table('event_types')->updateOrInsert(['name' => $name], ['updated_at' => now(), 'created_at' => now()]);
        }

        foreach ($eventLevels as $name) {
            DB::table('event_levels')->updateOrInsert(['name' => $name], ['updated_at' => now(), 'created_at' => now()]);
        }
    }
}
