<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('extra_curricular_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignID('subject_id')->constrained()->cascadeOnDelete();
            $table->string('activity_name');
            $table->integer('full_marks')->nullable();
            $table->integer('pass_marks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extra_curricular_activities');
    }
};
