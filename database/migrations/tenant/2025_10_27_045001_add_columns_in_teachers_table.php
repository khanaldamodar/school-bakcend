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
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('blood_group')->nullable();
            $table->boolean('is_disabled')->default(0);
            $table->boolean('is_tribe')->default(0);
            $table->string('image')->nullable();
            $table->string('gender');
            $table->date('dob');
            $table->string('nationality')->default('nepali')->nullable();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['blood_group', 'is_disabled', 'is_tribe', 'image','gender', 'dob', 'nationality']);
        });
    }
};
