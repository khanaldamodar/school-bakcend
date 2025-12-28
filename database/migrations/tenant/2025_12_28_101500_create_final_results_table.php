<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores the final weighted results calculated from all terms.
     * It keeps the term-wise results in the 'results' table intact.
     */
    public function up(): void
    {
        Schema::create('final_results', function (Blueprint $table) {
            $table->id();
            
            // Student and class information
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            
            // Subject-wise final result (optional - for per-subject final results)
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->onDelete('cascade');
            
            // Final calculated values
            $table->decimal('final_gpa', 4, 2)->default(0)->comment('Final weighted GPA');
            $table->decimal('final_percentage', 5, 2)->nullable()->comment('Final weighted percentage');
            $table->decimal('final_theory_marks', 6, 2)->nullable()->comment('Weighted average theory marks');
            $table->decimal('final_practical_marks', 6, 2)->nullable()->comment('Weighted average practical marks');
            
            // Grade and division based on final result
            $table->string('final_grade', 5)->nullable()->comment('Grade from final GPA/percentage');
            $table->string('final_division', 50)->nullable()->comment('Division from final percentage');
            
            // Pass/Fail status
            $table->boolean('is_passed')->default(false)->comment('Whether student passed all subjects');
            
            // Result type (gpa or percentage) based on school settings
            $table->enum('result_type', ['gpa', 'percentage'])->default('gpa');
            
            // Calculation method used
            $table->enum('calculation_method', ['simple', 'weighted'])->default('weighted');
            
            // Rank within class for this result type (can be null if not calculated)
            $table->unsignedInteger('rank')->nullable();
            
            // Additional metadata
            $table->text('remarks')->nullable();
            $table->json('term_breakdown')->nullable()->comment('JSON containing term-wise breakdown used in calculation');
            
            $table->timestamps();
            
            // Unique constraint: One final result per student per class per subject per academic year
            // If subject_id is null, it's the overall final result
            $table->unique(['student_id', 'class_id', 'academic_year_id', 'subject_id'], 'unique_final_result');
            
            // Indexes for faster queries
            $table->index(['class_id', 'academic_year_id']);
            $table->index(['student_id', 'academic_year_id']);
            $table->index(['is_passed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('final_results');
    }
};
