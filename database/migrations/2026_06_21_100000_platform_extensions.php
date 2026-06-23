<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique(['email']);
                });
            } catch (\Throwable) {
                // index may already be absent
            }

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
            } else {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('email')->nullable()->change();
                });
            }
        }

        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['study', 'kindergarten', 'work']);
            $table->string('name');
            $table->string('director_dat')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::table('athletes', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('occupation_type')->constrained()->nullOnDelete();
        });

        $this->migrateAthleteInstitutions();

        Schema::table('athletes', function (Blueprint $table) {
            if (Schema::hasColumn('athletes', 'school_name')) {
                $table->dropColumn(['school_name', 'school_director_dat', 'kindergarten_name', 'work_place']);
            }
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('athlete_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->unique(['athlete_id', 'inventory_item_id']);
        });

        $this->migrateInventoryItems();

        if (Schema::hasTable('athlete_inventories')) {
            Schema::drop('athlete_inventories');
        }

        Schema::create('event_participant_evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_participant_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        $this->migrateEvidenceFiles();

        if (Schema::hasColumn('event_participants', 'evidence_file_path')) {
            Schema::table('event_participants', function (Blueprint $table) {
                $table->dropColumn('evidence_file_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_participants', 'evidence_file_path')) {
            // restored manually if needed
        } elseif (Schema::hasTable('event_participants')) {
            Schema::table('event_participants', function (Blueprint $table) {
                if (! Schema::hasColumn('event_participants', 'evidence_file_path')) {
                    $table->string('evidence_file_path')->nullable();
                }
            });
        }

        Schema::dropIfExists('event_participant_evidence_files');

        Schema::create('athlete_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->boolean('weapon_case')->default(false);
            $table->boolean('jo')->default(false);
            $table->boolean('boken')->default(false);
            $table->boolean('tanto')->default(false);
            $table->boolean('tshirt')->default(false);
            $table->boolean('olympic_jacket')->default(false);
            $table->boolean('cap')->default(false);
            $table->boolean('backpack')->default(false);
            $table->boolean('shoe_bag')->default(false);
            $table->boolean('budo_passport')->default(false);
            $table->boolean('qual_book')->default(false);
            $table->boolean('referee_book')->default(false);
            $table->timestamps();
        });

        Schema::dropIfExists('athlete_inventory');
        Schema::dropIfExists('inventory_items');

        Schema::table('athletes', function (Blueprint $table) {
            if (Schema::hasColumn('athletes', 'institution_id')) {
                $table->dropConstrainedForeignId('institution_id');
            }
            if (! Schema::hasColumn('athletes', 'school_name')) {
                $table->string('school_name')->nullable();
                $table->string('school_director_dat')->nullable();
                $table->string('kindergarten_name')->nullable();
                $table->string('work_place')->nullable();
            }
        });

        Schema::dropIfExists('institutions');
    }

    private function migrateAthleteInstitutions(): void
    {
        if (! Schema::hasTable('athletes')) {
            return;
        }

        $institutionMap = [];

        foreach (DB::table('athletes')->orderBy('id')->get() as $athlete) {
            $type = $athlete->occupation_type;
            $name = null;
            $director = null;

            if ($type === 'study') {
                $name = trim((string) ($athlete->school_name ?? ''));
                $director = trim((string) ($athlete->school_director_dat ?? ''));
            } elseif ($type === 'kindergarten') {
                $name = trim((string) ($athlete->kindergarten_name ?? ''));
            } elseif ($type === 'work') {
                $name = trim((string) ($athlete->work_place ?? ''));
            }

            if (! $type || $name === '') {
                continue;
            }

            $key = $type . '|' . mb_strtolower($name) . '|' . mb_strtolower($director ?? '');
            if (! isset($institutionMap[$key])) {
                $institutionMap[$key] = DB::table('institutions')->insertGetId([
                    'type' => $type,
                    'name' => $name,
                    'director_dat' => $type === 'study' && $director !== '' ? $director : null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('athletes')->where('id', $athlete->id)->update([
                'institution_id' => $institutionMap[$key],
            ]);
        }
    }

    private function migrateInventoryItems(): void
    {
        $defaults = [
            ['slug' => 'weapon_case', 'name' => 'Чехол для оружия', 'sort' => 1],
            ['slug' => 'jo', 'name' => 'Деревянный меч (Д)', 'sort' => 2],
            ['slug' => 'boken', 'name' => 'Деревянный меч (Б)', 'sort' => 3],
            ['slug' => 'tanto', 'name' => 'Танто', 'sort' => 4],
            ['slug' => 'tshirt', 'name' => 'Футболка', 'sort' => 5],
            ['slug' => 'olympic_jacket', 'name' => 'Олимпийка', 'sort' => 6],
            ['slug' => 'cap', 'name' => 'Кепка', 'sort' => 7],
            ['slug' => 'backpack', 'name' => 'Рюкзак', 'sort' => 8],
            ['slug' => 'shoe_bag', 'name' => 'Мешок для обуви', 'sort' => 9],
            ['slug' => 'budo_passport', 'name' => 'Будо-паспорт', 'sort' => 10],
            ['slug' => 'qual_book', 'name' => 'Квалификационная книжка', 'sort' => 11],
            ['slug' => 'referee_book', 'name' => 'Судейская книжка', 'sort' => 12],
        ];

        $slugToId = [];
        foreach ($defaults as $item) {
            $slugToId[$item['slug']] = DB::table('inventory_items')->insertGetId([
                'slug' => $item['slug'],
                'name' => $item['name'],
                'sort_order' => $item['sort'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('athlete_inventories')) {
            return;
        }

        foreach (DB::table('athlete_inventories')->get() as $row) {
            foreach ($slugToId as $slug => $itemId) {
                if (! empty($row->{$slug})) {
                    DB::table('athlete_inventory')->insert([
                        'athlete_id' => $row->athlete_id,
                        'inventory_item_id' => $itemId,
                    ]);
                }
            }
        }
    }

    private function migrateEvidenceFiles(): void
    {
        if (! Schema::hasColumn('event_participants', 'evidence_file_path')) {
            return;
        }

        DB::table('event_participants')
            ->whereNotNull('evidence_file_path')
            ->where('evidence_file_path', '!=', '')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('event_participant_evidence_files')->insert([
                    'event_participant_id' => $row->id,
                    'file_path' => $row->evidence_file_path,
                    'original_name' => basename($row->evidence_file_path),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
};
