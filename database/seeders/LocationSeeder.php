<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Муравьева, 4',
                'color' => 'Зал красный',
            ],
            [
                'name' => 'Муравьева, 4',
                'color' => 'Зал синий',
            ],
            [
                'name' => 'Байкальская, 172',
                'color' => 'Зал зеленый',
            ],
        ];
        \App\Models\Location::insert($locations);
    }
}
