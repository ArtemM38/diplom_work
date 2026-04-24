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
        // 1. Справочник разрядов
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 1 юношеский и т.д.
        });

        // 2. Справочник судейских категорий
        Schema::create('referee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Юный судья и т.д.
        });

        // 3. История разрядов спортсмена
        Schema::create('athlete_rank_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->onDelete('cascade');
            $table->foreignId('rank_id')->constrained('ranks');
            $table->date('assigned_at'); // Дата присвоения
            $table->timestamps();
        });

        // 4. История судейских категорий
        Schema::create('athlete_referee_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->onDelete('cascade');
            $table->foreignId('referee_category_id')->constrained('referee_categories');
            $table->date('assigned_at');
            $table->timestamps();
        });

        // 5. Документы (сканы)
        Schema::create('athlete_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->onDelete('cascade');
            $table->string('type'); // medical, insurance, identity
            $table->string('series')->nullable();
            $table->string('number')->nullable();
            $table->string('issued_by')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_path'); // Путь к файлу
            $table->timestamps();
        });

        // 6. Инвентарь и Экипировка (простая реализация через boolean)
        Schema::create('athlete_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->onDelete('cascade');
            // Инвентарь
            $table->boolean('weapon_case')->default(false);
            $table->boolean('jo')->default(false);
            $table->boolean('boken')->default(false);
            $table->boolean('tanto')->default(false);
            // Экипировка
            $table->boolean('tshirt')->default(false);
            $table->boolean('olympic_jacket')->default(false);
            $table->boolean('cap')->default(false);
            $table->boolean('backpack')->default(false);
            $table->boolean('shoe_bag')->default(false);
            // Документы
            $table->boolean('budo_passport')->default(false);
            $table->boolean('qual_book')->default(false);
            $table->boolean('referee_book')->default(false);
            $table->timestamps();
        });
    }
};
