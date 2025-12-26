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
        Schema::table('result_settings', function (Blueprint $table) {
            // Add academic_year_id field for Nepal compliance
            $table->foreignId('academic_year_id')->nullable()->after('setting_id')
                  ->constrained('academic_years')
                  ->onDelete('cascade');
                  
            // Add index for better performance
            $table->index('academic_year_id');
        });

        // Update existing records to use current academic year
        DB::statement('UPDATE result_settings SET academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1) WHERE academic_year_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('result_settings', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropIndex(['academic_year_id']);
            $table->dropColumn('academic_year_id');
        });
    }
};