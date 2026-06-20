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
        Schema::create('athletes', function (Blueprint $table) {
            $table->id();

            // Привязка к пользователю (для авторизации)
            // nullable, так как сначала можем создать анкету, а потом дать доступ
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            // ФИО в падежах (для генерации документов)
            $table->string('last_name_nom');   // Фамилия (Им. падеж)
            $table->string('first_name_nom');  // Имя
            $table->string('middle_name_nom')->nullable(); // Отчество

            // Для документов часто удобнее хранить полные строки в нужных падежах
            $table->string('full_name_gen')->nullable(); // Родительный (Петрова Петра Петровича)
            $table->string('full_name_dat')->nullable(); // Дательный (Петрову Петру Петровичу)
            $table->string('full_name_ins')->nullable(); // Творительный (Петровым Петром Петровичем)

            $table->string('photo')->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('birth_date');
            $table->enum('gender', ['male', 'female']);
            $table->enum('occupation_type', ['study', 'work', 'kindergarten'])->nullable();

            // Адрес (будем складывать сюда ответ от DaData)
            $table->text('registration_address')->nullable();

            // Место обучения (Таблица "Место обучения" по ТЗ)
            $table->string('school_name')->nullable();
            $table->string('school_director_dat')->nullable(); // ФИО директора в дат. падеже
            $table->string('school_class')->nullable();

            $table->string('kindergarten_name')->nullable();

            // Место работы
            $table->string('work_place')->nullable();
            $table->string('work_position')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('athletes');
    }
};
