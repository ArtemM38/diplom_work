<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'event_date_to')) {
                $table->date('event_date_to')->nullable()->after('event_date');
            }
            if (! Schema::hasColumn('events', 'event_period')) {
                $table->string('event_period', 120)->nullable()->after('event_date');
            }
        });

        Schema::table('event_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('event_participants', 'attendance_status')) {
                $table->enum('attendance_status', ['Я', 'Н', 'У'])->nullable()->after('athlete_id');
            }
            if (! Schema::hasColumn('event_participants', 'excused_certificate')) {
                $table->string('excused_certificate')->nullable()->after('attendance_status');
            }
        });

        Schema::table('athlete_balance_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('athlete_balance_histories', 'event_participant_id')) {
                $table->foreignId('event_participant_id')->nullable()->after('attendance_id')
                    ->constrained('event_participants')->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('groups', 'status')) {
            DB::statement("ALTER TABLE `groups` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->convertMoneyColumn('events', 'cost');
        $this->convertMoneyColumn('groups', 'tariff_amount');
        $this->convertMoneyColumn('athlete_finances', 'balance', true);
        $this->convertMoneyColumn('athlete_finances', 'training_price');
        $this->convertMoneyColumn('athlete_balance_histories', 'change_amount', true);
        $this->convertMoneyColumn('athlete_balance_histories', 'balance_before', true);
        $this->convertMoneyColumn('athlete_balance_histories', 'balance_after', true);

        if (Schema::hasTable('athlete_group') && Schema::hasColumn('athlete_group', 'training_price')) {
            $this->convertMoneyColumn('athlete_group', 'training_price', false, true);
        }
    }

    public function down(): void
    {
        Schema::table('athlete_balance_histories', function (Blueprint $table) {
            if (Schema::hasColumn('athlete_balance_histories', 'event_participant_id')) {
                $table->dropConstrainedForeignId('event_participant_id');
            }
        });

        Schema::table('event_participants', function (Blueprint $table) {
            if (Schema::hasColumn('event_participants', 'excused_certificate')) {
                $table->dropColumn('excused_certificate');
            }
            if (Schema::hasColumn('event_participants', 'attendance_status')) {
                $table->dropColumn('attendance_status');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'event_period')) {
                $table->dropColumn('event_period');
            }
            if (Schema::hasColumn('events', 'event_date_to')) {
                $table->dropColumn('event_date_to');
            }
        });
    }

    private function convertMoneyColumn(string $table, string $column, bool $signed = false, bool $nullable = false): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $type = $signed ? 'SIGNED' : 'UNSIGNED';
        $nullSql = $nullable ? 'NULL' : 'NOT NULL DEFAULT 0';

        DB::statement("UPDATE `{$table}` SET `{$column}` = ROUND(`{$column}`) WHERE `{$column}` IS NOT NULL");
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` INT {$type} {$nullSql}");
    }
};
