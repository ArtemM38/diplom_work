<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SportsLookupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ranks = [
            '1 юношеский',
            '2 юношеский',
            '3 юношеский',
            '1 спортивный',
            '2 спортивный',
            '3 спортивный',
            'Кандидат в мастера спорта',
            'Мастер спорта'
        ];
        foreach ($ranks as $name) {
            \DB::table('ranks')->insert(['name' => $name]);
        }

        $categories = [
            'Юный судья',
            '3 судейская категория',
            '2 судейская категория',
            '1 судейская категория',
            'Высшая судейская категория'
        ];
        foreach ($categories as $name) {
            \DB::table('referee_categories')->insert(['name' => $name]);
        }
    }
}
