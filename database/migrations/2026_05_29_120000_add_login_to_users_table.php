<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 50)->nullable()->after('name');
        });

        $used = [];
        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $login = $this->generateLogin((string) $user->email, (int) $user->id, $used);
            DB::table('users')->where('id', $user->id)->update(['login' => $login]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('login');
            $table->dropUnique(['email']);
        });

        if (Schema::hasTable('password_reset_tokens') && Schema::hasColumn('password_reset_tokens', 'email')) {
            Schema::drop('password_reset_tokens');
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('login')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('password_reset_tokens') && Schema::hasColumn('password_reset_tokens', 'login')) {
            Schema::drop('password_reset_tokens');
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
            $table->dropUnique(['login']);
            $table->dropColumn('login');
        });
    }

    /**
     * @param  array<string, true>  $used
     */
    private function generateLogin(string $email, int $id, array &$used): string
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9._-]+/', '', strtok($email, '@') ?: ''));
        if ($base === '' || strlen($base) < 3) {
            $base = 'user' . $id;
        }

        $login = $base;
        $suffix = 1;
        while (isset($used[$login])) {
            $login = $base . $suffix;
            $suffix++;
        }

        $used[$login] = true;

        return $login;
    }
};
