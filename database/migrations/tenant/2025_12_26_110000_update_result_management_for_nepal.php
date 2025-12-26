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
        // 1. Update Subjects Table
        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'practical_pass_marks')) {
                $table->decimal('practical_pass_marks')->default(0)->after('theory_pass_marks');
            }
        });

        // 2. Update Result Settings Table
        $conn = Schema::getConnection();
        $indexExists = function($table, $index) use ($conn) {
            $indexes = DB::select("SHOW INDEXES FROM {$table} WHERE Key_name = '{$index}'");
            return count($indexes) > 0;
        };

        if ($indexExists('result_settings', 'result_settings_setting_id_unique')) {
            Schema::table('result_settings', function (Blueprint $table) {
                $table->dropUnique('result_settings_setting_id_unique');
            });
        } elseif ($indexExists('result_settings', 'setting_id')) {
             // Sometimes standard indexes are named just after the column
             Schema::table('result_settings', function (Blueprint $table) {
                $table->dropUnique(['setting_id']);
            });
        }

        Schema::table('result_settings', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('result_settings', 'idx_setting_year_unique')) {
                $table->unique(['setting_id', 'academic_year_id'], 'idx_setting_year_unique');
            }
        });

        // 3. Update Terms Table
        Schema::table('terms', function (Blueprint $table) {
            if (!Schema::hasColumn('terms', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('result_setting_id')
                      ->constrained('academic_years')
                      ->onDelete('cascade');
            }
        });

        // Backfill practical_pass_marks to 33% of practical_marks if they are 0
        DB::statement('UPDATE subjects SET practical_pass_marks = practical_marks * 0.33 WHERE practical_pass_marks = 0 AND practical_marks > 0');

        // Sync academic_year_id from result_settings to terms
        DB::statement('UPDATE terms t JOIN result_settings rs ON t.result_setting_id = rs.id SET t.academic_year_id = rs.academic_year_id WHERE t.academic_year_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn('academic_year_id');
        });

        Schema::table('result_settings', function (Blueprint $table) {
            $table->dropUnique('idx_setting_year_unique');
            $table->unique('setting_id');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('practical_pass_marks');
        });
    }
};
