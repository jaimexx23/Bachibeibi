<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'student_code')) {
                $table->string('student_code')->nullable()->after('student_id');
                $table->index('student_code');
            }
            if (! Schema::hasColumn('attendances', 'student_name')) {
                $table->string('student_name')->nullable()->after('student_code');
            }
            if (! Schema::hasColumn('attendances', 'classroom')) {
                $table->string('classroom')->nullable()->after('student_name');
            }
            if (! Schema::hasColumn('attendances', 'date')) {
                $table->date('date')->nullable()->after('classroom');
            }
            if (! Schema::hasColumn('attendances', 'checked_at')) {
                $table->dateTime('checked_at')->nullable()->after('date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'checked_at')) {
                $table->dropColumn('checked_at');
            }
            if (Schema::hasColumn('attendances', 'date')) {
                $table->dropColumn('date');
            }
            if (Schema::hasColumn('attendances', 'classroom')) {
                $table->dropColumn('classroom');
            }
            if (Schema::hasColumn('attendances', 'student_name')) {
                $table->dropColumn('student_name');
            }
            if (Schema::hasColumn('attendances', 'student_code')) {
                $table->dropIndex(['student_code']);
                $table->dropColumn('student_code');
            }
        });
    }
};
