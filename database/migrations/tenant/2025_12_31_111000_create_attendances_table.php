<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->onDelete('cascade');
            $table->foreignId('student_id')->nullable()->constrained('students')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->onDelete('cascade');
            $table->foreignId('class_id')->nullable()->constrained('classes')->onDelete('cascade');
            $table->date('attendance_date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'on_leave'])->default('present');
            $table->string('source')->default('device'); // e.g., 'device', 'manual'
            $table->string('device_id')->nullable(); // ID of the thumb device
            $table->string('device_user_id')->nullable(); // User ID on the thumb device
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Add indexes for performance
            $table->index(['attendance_date', 'student_id']);
            $table->index(['attendance_date', 'teacher_id']);
            $table->index(['attendance_date', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
