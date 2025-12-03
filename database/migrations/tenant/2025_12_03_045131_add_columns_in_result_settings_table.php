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
        Schema::table('result_settings', function (Blueprint $table) {
            $table->boolean('evaluation_per_term')->default(false)->after('term_weights');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('result_settings', function (Blueprint $table) {
            $table->dropColumn('evaluation_per_term');
        });
    }
};
