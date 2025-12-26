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
        Schema::table('extra_curricular_activities', function (Blueprint $table) {
            $table->decimal('full_marks', 8,2)->change();
            $table->decimal('pass_marks', 8,2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extra_curricular_activities', function (Blueprint $table) {
            $table->integer('full_marks')->change();
            $table->integer('pass_marks')->change();
        });
    }
};
