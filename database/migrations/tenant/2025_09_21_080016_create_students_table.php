<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            
            // School/Class Info
            $table->foreignId('class_id')->nullable()->constrained('classes')->onDelete('set null');
            $table->year('enrollment_year')->nullable(); // when student joined
            
            // If student is transferred out
            $table->boolean('is_transferred')->default(false);
            $table->string('transferred_to')->nullable(); // new school name/code
            $table->timestamps();
        });

        // To track promotions (history of class movements)
        Schema::create('student_class_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->year('year'); // academic year of enrollment
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_histories');
        Schema::dropIfExists('students');
    }
};



