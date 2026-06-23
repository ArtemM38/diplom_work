<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

                $update = [];
                if ($institution->type === 'study') {
                    $update['school_name'] = $institution->name;
                    $update['school_director_dat'] = $institution->director_dat;
                } elseif ($institution->type === 'kindergarten') {
                    $update['kindergarten_name'] = $institution->name;
                } elseif ($institution->type === 'work') {
                    $update['work_place'] = $institution->name;
                }

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

    public function down(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['study', 'kindergarten', 'work']);
            $table->string('name');
            $table->string('director_dat')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('athletes', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('athletes', function (Blueprint $table) {
            if (Schema::hasColumn('athletes', 'school_name')) {
                $table->dropColumn(['school_name', 'school_director_dat', 'kindergarten_name', 'work_place']);
            }
        });
    }
};
