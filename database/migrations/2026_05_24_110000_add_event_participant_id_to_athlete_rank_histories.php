<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_rank_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('athlete_rank_histories', 'event_participant_id')) {
                $table->foreignId('event_participant_id')
                    ->nullable()
                    ->after('rank_id')
                    ->constrained('event_participants')
                    ->nullOnDelete();
                $table->unique('event_participant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('athlete_rank_histories', function (Blueprint $table) {
            if (Schema::hasColumn('athlete_rank_histories', 'event_participant_id')) {
                $table->dropConstrainedForeignId('event_participant_id');
            }
        });
    }
};
