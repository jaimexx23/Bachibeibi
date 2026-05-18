<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->string('student_code');
            $table->string('student_name')->nullable();
            $table->string('classroom')->nullable();
            $table->date('date');
            $table->timestamp('checked_at')->useCurrent();
            $table->string('source')->default('qr');
            $table->unique(['student_code','date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
