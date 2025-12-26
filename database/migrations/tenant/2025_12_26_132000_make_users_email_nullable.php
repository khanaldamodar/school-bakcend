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
        // Make email nullable in users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
        
        // Drop unique constraint from email if it exists
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['email']);
            });
        } catch (\Exception $e) {
            // Unique constraint might not exist, continue
        }
        
        // Drop unique constraint from phone if it exists
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['phone']);
            });
        } catch (\Exception $e) {
            // Unique constraint might not exist, continue
        }
        
        // Make phone nullable as well
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We don't revert these changes as it may break existing data
        // This is a one-way migration for safety
    }
};
