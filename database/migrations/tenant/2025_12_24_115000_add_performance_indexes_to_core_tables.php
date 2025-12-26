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
        $conn = Schema::getConnection();
        
        // Helper to check if index exists (MySQL specific)
        $indexExists = function($table, $index) use ($conn) {
            $indexes = DB::select("SHOW INDEXES FROM {$table} WHERE Key_name = '{$index}'");
            return count($indexes) > 0;
        };

        // Students Table Indexes
        Schema::table('students', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('students', 'idx_student_class_status')) {
                $table->index(['class_id', 'is_deleted'], 'idx_student_class_status');
            }
            if (!$indexExists('students', 'idx_student_names')) {
                $table->index(['first_name', 'last_name'], 'idx_student_names');
            }
            if (!$indexExists('students', 'idx_student_deleted')) {
                $table->index('is_deleted', 'idx_student_deleted');
            }
        });

        // Teachers Table Indexes
        Schema::table('teachers', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('teachers', 'idx_teacher_deleted')) {
                $table->index('is_deleted', 'idx_teacher_deleted');
            }
            if (!$indexExists('teachers', 'idx_teacher_user')) {
                $table->index('user_id', 'idx_teacher_user');
            }
        });

        // Results Table Indexes - Critical for report generation
        Schema::table('results', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('results', 'idx_result_student_academic_term')) {
                $table->index(['student_id', 'academic_year_id', 'term_id'], 'idx_result_student_academic_term');
            }
            if (!$indexExists('results', 'idx_result_class_academic_term')) {
                $table->index(['class_id', 'academic_year_id', 'term_id'], 'idx_result_class_academic_term');
            }
        });

        // Classes Table Indexes
        Schema::table('classes', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('classes', 'idx_class_code')) {
                $table->index('class_code', 'idx_class_code');
            }
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
