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
                'address' => 'Муравьева, 4',
                'name' => 'Зал красный',
            ],
            [
                'address' => 'Муравьева, 4',
                'name' => 'Зал синий',
            ],
            [
                'address' => 'Байкальская, 172',
                'name' => 'Зал зеленый',
            ],
        ];
        \App\Models\Location::insert($locations);
    }
}
