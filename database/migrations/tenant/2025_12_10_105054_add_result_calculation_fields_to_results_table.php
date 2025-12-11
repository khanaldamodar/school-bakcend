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
        Schema::table('results', function (Blueprint $table) {
            // Add term_id as required foreign key
            $table->foreignId('term_id')
                ->after('teacher_id')
                ->constrained('terms')
                ->onDelete('cascade');
            
            // Add percentage field for percentage-based results
            $table->decimal('percentage', 5, 2)
                ->nullable()
                ->after('gpa')
                ->comment('Percentage when result_type is percentage');
            
            // Add final_result field for weighted calculations
            $table->decimal('final_result', 5, 2)
                ->nullable()
                ->after('percentage')
                ->comment('Final weighted result for weighted calculation method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['term_id']);
            $table->dropColumn(['term_id', 'percentage', 'final_result']);
        });
    }
};
