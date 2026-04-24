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
        // Таблица Групп
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название (напр. "Младшая группа А")
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('type')->default('Учебная'); // У, С, А и т.д. по ТЗ
            $table->decimal('tariff_amount', 10, 2)->default(0); // Стоимость в месяц
            $table->timestamps();
        });

        // Промежуточная таблица для зачисления спортсменов в группы
        Schema::create('athlete_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->onDelete('cascade');
            $table->foreignId('group_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('athlete_group');
        Schema::dropIfExists('groups');
    }
};
