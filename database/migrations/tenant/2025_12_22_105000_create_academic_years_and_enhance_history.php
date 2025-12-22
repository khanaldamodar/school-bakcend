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
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "2024-2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false); // Only one can be current
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Add academic_year_id to student_class_histories if not exists
        if (!Schema::hasColumn('student_class_histories', 'academic_year_id')) {
            Schema::table('student_class_histories', function (Blueprint $table) {
                $table->foreignId('academic_year_id')->nullable()->after('year')->constrained('academic_years')->onDelete('cascade');
                $table->integer('roll_number')->nullable()->after('class_id'); // Store roll number at that time
                $table->date('promoted_date')->nullable(); // When they were promoted
                $table->string('status')->default('active'); // active, promoted, transferred, graduated
                $table->text('remarks')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('student_class_histories', 'academic_year_id')) {
            Schema::table('student_class_histories', function (Blueprint $table) {
                $table->dropForeign(['academic_year_id']);
                $table->dropColumn(['academic_year_id', 'roll_number', 'promoted_date', 'status', 'remarks']);
            });
        }
        
        Schema::dropIfExists('academic_years');
    }
};
