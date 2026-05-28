<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('student_code')->unique();
            $table->string('account_number')->nullable()->unique();
            $table->string('classroom');
            $table->string('role')->default('student');
            $table->string('password_hash')->nullable();
            $table->boolean('default_password')->default(false);
            $table->string('photo_filename')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
