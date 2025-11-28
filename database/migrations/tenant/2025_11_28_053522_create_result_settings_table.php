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
        Schema::create('result_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('total_terms');
            $table->enum('calculation_method', ['simple', 'weighted'])->default('simple');
            $table->enum('result_type', ['percentage', 'gpa'])->default('percentage');
            $table->timestamps();
            $table->foreignId('setting_id')
                ->constrained('settings')
                ->cascadeOnDelete()
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_settings');
    }
};
