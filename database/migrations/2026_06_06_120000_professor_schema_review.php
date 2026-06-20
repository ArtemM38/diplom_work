<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events', 'event_period')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('event_period');
            });
        }

        $this->restoreDecimalColumn('events', 'cost', false, false, '0.00');

        if (Schema::hasTable('groups') && Schema::hasColumn('groups', 'status')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `groups` MODIFY `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
            } else {
                Schema::table('groups', function (Blueprint $table) {
                    $table->enum('status', ['active', 'inactive'])->default('active')->change();
                });
            }
        }

        if (Schema::hasTable('athletes')) {
            Schema::table('athletes', function (Blueprint $table) {
                $table->string('phone', 20)->nullable()->change();
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `athletes` MODIFY `occupation_type` ENUM('study', 'work', 'kindergarten') NULL COMMENT 'Тип занятости: учёба, работа, детский сад'");
            } else {
                Schema::table('athletes', function (Blueprint $table) {
                    $table->enum('occupation_type', ['study', 'work', 'kindergarten'])->nullable()->change();
                });
            }
        }

        if (Schema::hasTable('athlete_finances')) {
            $this->restoreDecimalColumn('athlete_finances', 'balance', true, false, '0.00');

            if (Schema::hasColumn('athlete_finances', 'discount_percent')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE `athlete_finances` CHANGE `discount_percent` `discount` DECIMAL(5,2) NOT NULL DEFAULT 0');
                } else {
                    Schema::table('athlete_finances', function (Blueprint $table) {
                        $table->renameColumn('discount_percent', 'discount');
                    });
                    Schema::table('athlete_finances', function (Blueprint $table) {
                        $table->decimal('discount', 5, 2)->default(0)->change();
                    });
                }
            }

            if (Schema::hasColumn('athlete_finances', 'training_price')) {
                Schema::table('athlete_finances', function (Blueprint $table) {
                    $table->dropColumn('training_price');
                });
            }
        }

        if (Schema::hasTable('role_user') && Schema::hasColumn('role_user', 'is_primary')) {
            Schema::table('role_user', function (Blueprint $table) {
                $table->dropColumn('is_primary');
            });
        }

        if (Schema::hasTable('portfolio_achievements')) {
            DB::table('portfolio_achievements')->whereNotNull('event_id')->delete();

            Schema::table('portfolio_achievements', function (Blueprint $table) {
                if (Schema::hasColumn('portfolio_achievements', 'event_id')) {
                    $table->dropConstrainedForeignId('event_id');
                }
                if (Schema::hasColumn('portfolio_achievements', 'event_period')) {
                    $table->dropColumn('event_period');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('portfolio_achievements')) {
            Schema::table('portfolio_achievements', function (Blueprint $table) {
                if (! Schema::hasColumn('portfolio_achievements', 'event_id')) {
                    $table->foreignId('event_id')->nullable()->after('athlete_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('portfolio_achievements', 'event_period')) {
                    $table->date('event_period')->nullable()->after('event_date');
                }
            });
        }

        if (Schema::hasTable('role_user') && ! Schema::hasColumn('role_user', 'is_primary')) {
            Schema::table('role_user', function (Blueprint $table) {
                $table->boolean('is_primary')->default(false)->after('role_id');
            });
        }

        if (Schema::hasTable('athlete_finances')) {
            if (! Schema::hasColumn('athlete_finances', 'training_price')) {
                Schema::table('athlete_finances', function (Blueprint $table) {
                    $table->decimal('training_price', 10, 2)->default(0)->after('balance');
                });
            }

            if (Schema::hasColumn('athlete_finances', 'discount')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE `athlete_finances` CHANGE `discount` `discount_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0');
                } else {
                    Schema::table('athlete_finances', function (Blueprint $table) {
                        $table->renameColumn('discount', 'discount_percent');
                    });
                }
            }
        }

        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'event_period')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('event_period', 120)->nullable()->after('event_date');
            });
        }
    }

    private function restoreDecimalColumn(
        string $table,
        string $column,
        bool $signed = false,
        bool $nullable = false,
        string $default = '0.00'
    ): void {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $signed, $nullable, $default) {
                $col = $blueprint->decimal($column, 10, 2);
                if ($nullable) {
                    $col->nullable();
                } else {
                    $col->default($default);
                }
                if (! $signed) {
                    $col->unsigned();
                }
                $col->change();
            });

            return;
        }

        $nullSql = $nullable ? 'NULL' : "NOT NULL DEFAULT {$default}";
        $unsigned = $signed ? '' : 'UNSIGNED';

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(10,2) {$unsigned} {$nullSql}");
    }
};
