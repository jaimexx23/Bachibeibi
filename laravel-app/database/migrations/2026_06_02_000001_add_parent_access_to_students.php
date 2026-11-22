<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'parent_password_hash')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('parent_password_hash')->nullable()->after('password_hash');
                $table->boolean('parent_default_password')->default(true)->after('parent_password_hash');
            });
        }

        $defaultPassword = (string) env('PARENT_DEFAULT_PASSWORD', 'Padres05');

        DB::table('students')
            ->whereNotNull('account_number')
            ->where('account_number', '!=', '')
            ->whereNull('parent_password_hash')
            ->update([
                'parent_password_hash' => Hash::make($defaultPassword),
                'parent_default_password' => true,
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'parent_default_password')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('parent_default_password');
            });
        }

        if (Schema::hasColumn('students', 'parent_password_hash')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('parent_password_hash');
            });
        }
    }
};
