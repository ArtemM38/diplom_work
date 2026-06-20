<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('event_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('event_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('rank')->nullable();
            $table->string('city')->nullable();
            $table->string('contacts')->nullable();
            $table->text('extra_info')->nullable();
            $table->timestamps();
        });

        Schema::create('portfolio_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->onDelete('cascade');
            $table->string('event_name');
            $table->foreignId('event_type_id')->constrained()->onDelete('restrict');
            $table->string('event_place')->nullable();
            $table->date('event_date')->nullable();
            $table->foreignId('event_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_host_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result_label')->nullable();
            $table->unsignedTinyInteger('result_place')->nullable();
            $table->foreignId('result_rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->string('certificate_id')->nullable();
            $table->text('result_description')->nullable();
            $table->string('evidence_file_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_achievements');
        Schema::dropIfExists('event_hosts');
        Schema::dropIfExists('event_levels');
        Schema::dropIfExists('event_types');
    }
};
