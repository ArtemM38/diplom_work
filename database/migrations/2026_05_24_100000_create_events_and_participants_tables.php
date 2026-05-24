<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_hosts', function (Blueprint $table) {
            if (! Schema::hasColumn('event_hosts', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('full_name');
            }
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('cost', 10, 2)->default(0);
            $table->foreignId('event_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_place')->nullable();
            $table->foreignId('event_host_id')->nullable()->constrained()->nullOnDelete();
            $table->date('event_date')->nullable();
            $table->string('event_period')->nullable();
            $table->string('status', 20)->default('planned');
            $table->timestamps();
        });

        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->string('result_label')->nullable();
            $table->unsignedTinyInteger('result_place')->nullable();
            $table->foreignId('result_rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->string('certificate_id')->nullable();
            $table->text('result_description')->nullable();
            $table->string('evidence_file_path')->nullable();
            $table->timestamp('results_filled_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'athlete_id']);
        });

        Schema::table('portfolio_achievements', function (Blueprint $table) {
            if (! Schema::hasColumn('portfolio_achievements', 'event_id')) {
                $table->foreignId('event_id')->nullable()->after('athlete_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_achievements', function (Blueprint $table) {
            if (Schema::hasColumn('portfolio_achievements', 'event_id')) {
                $table->dropConstrainedForeignId('event_id');
            }
        });

        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('events');

        Schema::table('event_hosts', function (Blueprint $table) {
            if (Schema::hasColumn('event_hosts', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};
