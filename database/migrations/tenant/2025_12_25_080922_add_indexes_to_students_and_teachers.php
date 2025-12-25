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
            $table->index('gender');
            $table->index('ethnicity');
            $table->index('is_disabled');
            $table->index('is_tribe'); // Keep for now, but not primary
            $table->index('class_id');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->index('gender');
            $table->index('ethnicity');
            $table->index('is_disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropIndex(['ethnicity']);
            $table->dropIndex(['is_disabled']);
            $table->dropIndex(['is_tribe']);
            $table->dropIndex(['class_id']);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropIndex(['ethnicity']);
            $table->dropIndex(['is_disabled']);
        });
    }
};
