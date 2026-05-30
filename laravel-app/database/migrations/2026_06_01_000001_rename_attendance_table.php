<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance') && !Schema::hasTable('attendances')) {
            Schema::rename('attendance', 'attendances');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendances') && !Schema::hasTable('attendance')) {
            Schema::rename('attendances', 'attendance');
        }
    }
};
