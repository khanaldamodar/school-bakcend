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
        // Students Table Indexes
        Schema::table('students', function (Blueprint $table) {
            $table->index(['class_id', 'is_deleted'], 'idx_student_class_status');
            $table->index(['first_name', 'last_name'], 'idx_student_names');
            $table->index('is_deleted', 'idx_student_deleted');
            // user_id is foreignId, already indexed by Laravel usually
        });

        // Teachers Table Indexes
        Schema::table('teachers', function (Blueprint $table) {
            $table->index('is_deleted', 'idx_teacher_deleted');
            $table->index('user_id', 'idx_teacher_user');
        });

        // Results Table Indexes - Critical for report generation
        Schema::table('results', function (Blueprint $table) {
            // Composite index for student-wise term reports
            $table->index(['student_id', 'academic_year_id', 'term_id'], 'idx_result_student_academic_term');
            // Composite index for class-wise term ledgers
            $table->index(['class_id', 'academic_year_id', 'term_id'], 'idx_result_class_academic_term');
        });

        // Classes Table Indexes
        Schema::table('classes', function (Blueprint $table) {
            $table->index('class_code', 'idx_class_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_student_class_status');
            $table->dropIndex('idx_student_names');
            $table->dropIndex('idx_student_deleted');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex('idx_teacher_deleted');
            $table->dropIndex('idx_teacher_user');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex('idx_result_student_academic_term');
            $table->dropIndex('idx_result_class_academic_term');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('idx_class_code');
        });
    }
};
