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
        Schema::create('terms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('result_setting_id')->constrained('result_settings')->cascadeOnDelete();
    
    $table->string('name'); // First Term, Second Term, Final
    $table->integer('weight')->nullable();

    $table->date('exam_date')->nullable();
    $table->date('publish_date')->nullable();
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
