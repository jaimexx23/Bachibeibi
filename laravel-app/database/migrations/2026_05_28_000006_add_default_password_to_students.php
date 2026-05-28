<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'default_password')) {
            Schema::table('students', function (Blueprint $table) {
                $table->boolean('default_password')->default(false)->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'default_password')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('default_password');
            });
        }
    }
};
