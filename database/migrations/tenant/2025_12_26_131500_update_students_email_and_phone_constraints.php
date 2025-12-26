<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make email nullable for students (if not already)
        Schema::table('students', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
        
        // Make email nullable for parents table as well
        if (Schema::hasTable('parents')) {
            Schema::table('parents', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
            });
        }
        
        // Note: Phone number unique constraint removal
        // Since we can't easily detect and drop unique constraints without Doctrine,
        // and the original migration shows phone as nullable without unique constraint,
        // we'll just ensure it's nullable
        Schema::table('students', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We don't revert email to required as it may break existing data
        // This is a one-way migration for safety
    }
};
