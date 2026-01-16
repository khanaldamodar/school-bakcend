<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('class_subject_teacher', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('class_subject_teacher', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable(false)->change();
        });
    }
};
