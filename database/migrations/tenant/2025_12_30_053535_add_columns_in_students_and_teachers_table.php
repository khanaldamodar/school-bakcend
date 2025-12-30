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
        Schema::table('students', function (Blueprint $table) {
            $table->enum('disability_options', ['none', 'visual', 'hearing', 'physical', 'mental', 'other'])->default('none');
            
        });
        Schema::table('teachers', function (Blueprint $table) {
            $table->enum('disability_options', ['none', 'visual', 'hearing', 'physical', 'mental', 'other'])->default('none');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('disability_options');
        });
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('disability_options');
        });
    }
};
