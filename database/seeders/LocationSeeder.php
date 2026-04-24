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
            ['name' => 'Зал Муравьева, 4 (красный)'],
            ['name' => 'Зал Муравьева, 4 (синий)'],
            ['name' => 'Зал Байкальская, 172 (зеленый)'],
        ];
        \App\Models\Location::insert($locations);
    }
}
