<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventHost;
use App\Models\EventLevel;
use App\Models\EventType;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = bcrypt('qawsedrf');

        $coaches = [
            ['name' => 'Смирнов Алексей Петрович', 'login' => 'coach1', 'email' => 'coach1@test.ru'],
            ['name' => 'Козлов Дмитрий Сергеевич', 'login' => 'coach2', 'email' => 'coach2@test.ru'],
            ['name' => 'Волкова Елена Николаевна', 'login' => 'coach3', 'email' => 'coach3@test.ru'],
        ];

        foreach ($coaches as $coach) {
            $user = User::firstOrCreate(
                ['login' => $coach['login']],
                [
                    'name' => $coach['name'],
                    'email' => $coach['email'],
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['coach']);
        }

        $accountant = User::firstOrCreate(
            ['login' => 'accountant'],
            [
                'name' => 'Бухгалтерова Анна Сергеевна',
                'email' => 'accountant@test.ru',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $accountant->syncRoles(['accountant']);

        $groups = [
            ['name' => 'Группа начинающих', 'type' => 'Учебная', 'tariff_amount' => 450],
            ['name' => 'Группа 10–14 лет', 'type' => 'Учебная', 'tariff_amount' => 500],
            ['name' => 'Подготовка к аттестации', 'type' => 'Аттестация', 'tariff_amount' => 600],
        ];

        foreach ($groups as $groupData) {
            Group::firstOrCreate(
                ['name' => $groupData['name']],
                [
                    'type' => $groupData['type'],
                    'tariff_amount' => $groupData['tariff_amount'],
                    'status' => 'active',
                ]
            );
        }

        $hosts = [
            [
                'full_name' => 'Осипов Михаил Владимирович',
                'birth_date' => '1978-03-12',
                'rank' => '5 дан',
                'city' => 'Иркутск',
                'contacts' => '+7 (914) 900-11-22',
            ],
            [
                'full_name' => 'Николаева Светлана Игоревна',
                'birth_date' => '1982-07-25',
                'rank' => '4 дан',
                'city' => 'Иркутск',
                'contacts' => '+7 (914) 900-33-44',
            ],
            [
                'full_name' => 'Морозов Андрей Константинович',
                'birth_date' => '1975-11-08',
                'rank' => '6 дан',
                'city' => 'Ангарск',
                'contacts' => '+7 (914) 900-55-66',
            ],
        ];

        $hostModels = [];
        foreach ($hosts as $hostData) {
            $hostModels[] = EventHost::firstOrCreate(
                ['full_name' => $hostData['full_name']],
                $hostData
            );
        }

        $seminarType = EventType::where('name', 'Семинар')->first();
        $competitionType = EventType::where('name', 'Соревнования')->first();
        $attestationType = EventType::where('name', 'Аттестация')->first();

        $cityLevel = EventLevel::where('name', 'Городской')->first();
        $regionalLevel = EventLevel::where('name', 'Региональный')->first();

        $events = [
            [
                'name' => 'Семинар по технике айкидо',
                'cost' => 1200,
                'event_type_id' => $seminarType?->id,
                'event_level_id' => $cityLevel?->id,
                'event_place' => 'Спортзал «Байкал», Иркутск',
                'event_host_id' => $hostModels[0]->id,
                'event_date' => now()->addMonths(1)->format('Y-m-d'),
                'status' => 'planned',
            ],
            [
                'name' => 'Открытое первенство города',
                'cost' => 2500,
                'event_type_id' => $competitionType?->id,
                'event_level_id' => $regionalLevel?->id,
                'event_place' => 'ДС «Труд», Иркутск',
                'event_host_id' => $hostModels[1]->id,
                'event_date' => now()->addMonths(2)->format('Y-m-d'),
                'status' => 'planned',
            ],
            [
                'name' => 'Аттестация на пояс кю',
                'cost' => 800,
                'event_type_id' => $attestationType?->id,
                'event_level_id' => $cityLevel?->id,
                'event_place' => 'Секция «Айкидо», Иркутск',
                'event_host_id' => $hostModels[2]->id,
                'event_date' => now()->addWeeks(3)->format('Y-m-d'),
                'status' => 'planned',
            ],
        ];

        foreach ($events as $eventData) {
            if (! $eventData['event_type_id']) {
                continue;
            }

            Event::firstOrCreate(
                ['name' => $eventData['name']],
                $eventData
            );
        }

        $athletesSeed = [
            [
                'login' => 'athlete1',
                'email' => 'athlete1@test.ru',
                'name' => 'Петров Пётр Сергеевич',
                'last_name_nom' => 'Петров',
                'first_name_nom' => 'Пётр',
                'middle_name_nom' => 'Сергеевич',
                'birth_date' => '2012-03-15',
                'gender' => 'male',
                'phone' => '+7 (914) 100-01-01',
            ],
            [
                'login' => 'athlete2',
                'email' => 'athlete2@test.ru',
                'name' => 'Сидорова Анна Дмитриевна',
                'last_name_nom' => 'Сидорова',
                'first_name_nom' => 'Анна',
                'middle_name_nom' => 'Дмитриевна',
                'birth_date' => '2011-07-22',
                'gender' => 'female',
                'phone' => '+7 (914) 100-02-02',
            ],
            [
                'login' => 'athlete3',
                'email' => 'athlete3@test.ru',
                'name' => 'Кузнецов Артём Игоревич',
                'last_name_nom' => 'Кузнецов',
                'first_name_nom' => 'Артём',
                'middle_name_nom' => 'Игоревич',
                'birth_date' => '2013-11-05',
                'gender' => 'male',
                'phone' => '+7 (914) 100-03-03',
            ],
        ];

        $athleteModels = [];
        foreach ($athletesSeed as $row) {
            $user = User::firstOrCreate(
                ['login' => $row['login']],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['athlete']);

            $athleteModels[] = Athlete::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'last_name_nom' => $row['last_name_nom'],
                    'first_name_nom' => $row['first_name_nom'],
                    'middle_name_nom' => $row['middle_name_nom'],
                    'birth_date' => $row['birth_date'],
                    'gender' => $row['gender'],
                    'phone' => $row['phone'],
                    'occupation_type' => 'study',
                    'registration_address' => 'г. Иркутск',
                ]
            );
        }

        $guardiansSeed = [
            [
                'login' => 'guardian1',
                'email' => 'guardian1@test.ru',
                'name' => 'Петрова Елена Викторовна',
                'full_name' => 'Петрова Елена Викторовна',
                'phone' => '+7 (914) 200-01-01',
                'relation' => 'Мать',
                'athlete_index' => 0,
            ],
            [
                'login' => 'guardian2',
                'email' => 'guardian2@test.ru',
                'name' => 'Сидоров Дмитрий Александрович',
                'full_name' => 'Сидоров Дмитрий Александрович',
                'phone' => '+7 (914) 200-02-02',
                'relation' => 'Отец',
                'athlete_index' => 1,
            ],
            [
                'login' => 'guardian3',
                'email' => 'guardian3@test.ru',
                'name' => 'Кузнецова Ольга Петровна',
                'full_name' => 'Кузнецова Ольга Петровна',
                'phone' => '+7 (914) 200-03-03',
                'relation' => 'Мать',
                'athlete_index' => 2,
            ],
        ];

        foreach ($guardiansSeed as $row) {
            $user = User::firstOrCreate(
                ['login' => $row['login']],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['guardian']);

            $guardian = Guardian::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $row['full_name'],
                    'phone' => $row['phone'],
                    'relation' => $row['relation'],
                ]
            );

            if (isset($athleteModels[$row['athlete_index']])) {
                $guardian->athletes()->syncWithoutDetaching([$athleteModels[$row['athlete_index']]->id]);
            }
        }

        $demoGroups = Group::query()->visible()->orderBy('id')->take(3)->get();
        foreach ($athleteModels as $index => $athlete) {
            if ($demoGroups->has($index)) {
                $athlete->groups()->syncWithoutDetaching([$demoGroups[$index]->id]);
            }
        }
    }
}
