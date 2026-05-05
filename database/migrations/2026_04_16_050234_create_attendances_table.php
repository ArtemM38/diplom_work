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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            // Привязка к конкретному занятию в календаре
            $table->foreignId('schedule_id')->constrained()->onDelete('cascade');
            // Привязка к спортсмену
            $table->foreignId('athlete_id')->constrained()->onDelete('cascade');

            // Статус по ТЗ: Явка/Н/УН
            $table->enum('status', ['Я', 'Н', 'У'])->default('Н');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
