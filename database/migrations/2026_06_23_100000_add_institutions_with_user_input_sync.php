<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions')) {
            Schema::create('institutions', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['study', 'kindergarten', 'work']);
                $table->text('name');
                $table->string('director_dat')->nullable();
                $table->timestamps();
                $table->index(['type']);
            });
        }

        if (! Schema::hasColumn('athletes', 'institution_id')) {
            Schema::table('athletes', function (Blueprint $table) {
                $table->foreignId('institution_id')->nullable()->after('occupation_type')->constrained()->nullOnDelete();
            });
        }

        $this->migrateExistingAthletes();

        Schema::table('athletes', function (Blueprint $table) {
            $columns = ['school_name', 'school_director_dat', 'kindergarten_name', 'work_place'];
            $existing = array_filter($columns, fn ($col) => Schema::hasColumn('athletes', $col));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }

    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            if (! Schema::hasColumn('athletes', 'school_name')) {
                $table->text('school_name')->nullable();
                $table->string('school_director_dat')->nullable();
                $table->text('kindergarten_name')->nullable();
                $table->text('work_place')->nullable();
            }
        });

        if (Schema::hasTable('institutions') && Schema::hasColumn('athletes', 'institution_id')) {
            foreach (DB::table('athletes')->whereNotNull('institution_id')->get() as $athlete) {
                $institution = DB::table('institutions')->where('id', $athlete->institution_id)->first();
                if (! $institution) {
                    continue;
                }

                $update = match ($institution->type) {
                    'study' => [
                        'school_name' => $institution->name,
                        'school_director_dat' => $institution->director_dat,
                    ],
                    'kindergarten' => ['kindergarten_name' => $institution->name],
                    'work' => ['work_place' => $institution->name],
                    default => [],
                };

                if ($update !== []) {
                    DB::table('athletes')->where('id', $athlete->id)->update($update);
                }
            }

            Schema::table('athletes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('institution_id');
            });

            Schema::dropIfExists('institutions');
        }
    }

    private function migrateExistingAthletes(): void
    {
        if (! Schema::hasColumn('athletes', 'school_name')) {
            return;
        }

        foreach (DB::table('athletes')->orderBy('id')->get() as $athlete) {
            if ($athlete->institution_id) {
                continue;
            }

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

            $institutionId = $this->findOrCreateInstitutionId($type, $name, $director);

            DB::table('athletes')->where('id', $athlete->id)->update([
                'institution_id' => $institutionId,
            ]);
        }
    }

    private function findOrCreateInstitutionId(string $type, string $name, ?string $director = null): int
    {
        $existing = DB::table('institutions')
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            if ($type === 'study' && $director && empty($existing->director_dat)) {
                DB::table('institutions')->where('id', $existing->id)->update([
                    'director_dat' => $director,
                    'updated_at' => now(),
                ]);
            }

            return (int) $existing->id;
        }

        return (int) DB::table('institutions')->insertGetId([
            'type' => $type,
            'name' => $name,
            'director_dat' => $type === 'study' && $director !== '' ? $director : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
