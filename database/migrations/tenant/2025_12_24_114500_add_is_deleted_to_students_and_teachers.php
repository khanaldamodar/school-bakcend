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
            $table->boolean('is_deleted')->default(false)->after('ethnicity');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('joining_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });
    }
};
