<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'admin', 'label' => 'Администратор'],
            ['slug' => 'accountant', 'label' => 'Бухгалтер'],
            ['slug' => 'coach', 'label' => 'Тренер'],
            ['slug' => 'athlete', 'label' => 'Спортсмен'],
            ['slug' => 'guardian', 'label' => 'Родитель'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                ['label' => $role['label']],
            );
        }
    }
}
