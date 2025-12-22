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
        Schema::create('teacher_role_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('teacher_role_id')
                  ->constrained('teacher_roles')
                  ->cascadeOnDelete();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->unique(['teacher_id', 'teacher_role_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_role_map');
    }
};
