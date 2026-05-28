<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    $admin = \App\Models\User::create([
        'name' => 'Главный Админ',
        'email' => 'admin@test.ru',
        'password' => bcrypt('qawsedrf'),
    ]);
    $admin->syncRoles(['admin']);
}
}
