<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_class_histories', function (Blueprint $table) {
            $table->index(['student_id', 'academic_year_id'], 'idx_history_student_year');
            $table->index(['class_id', 'academic_year_id'], 'idx_history_class_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_class_histories', function (Blueprint $table) {
            $table->dropIndex('idx_history_student_year');
            $table->dropIndex('idx_history_class_year');
        });
    }
};
