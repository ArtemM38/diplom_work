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
        // 1. Справочник мест (Таблица 12 по ТЗ)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название зала
            $table->string('address')->nullable();
            $table->timestamps();
        });

        // 2. Расписание (Таблица 11 по ТЗ)
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('coach_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('initial_coach_id')->nullable()->constrained('users')->nullOnDelete();

            $table->integer('day_of_week');
            $table->date('lesson_date')->nullable();
            $table->time('start_time');
            $table->time('end_time');

            $table->enum('lesson_type', ['group', 'individual'])->default('group');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('schedule_coach_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_coach_changes');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('locations');
    }
};
