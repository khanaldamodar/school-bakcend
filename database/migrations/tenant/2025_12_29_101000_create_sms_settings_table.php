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
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();

            // Academic year specific settings
            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->onDelete('cascade');

            // Optional event type filter (e.g., exam, sports, meeting)
            $table->string('event_type')->nullable();

            // How many days before the event date the SMS should be sent
            $table->unsignedInteger('days_before')->default(1);

            // Target audience and template
            $table->string('target_group')->default('parents'); // parents, students, teachers, all
            $table->boolean('is_active')->default(true);
            $table->text('message_template')->nullable();

            $table->timestamps();

            $table->index(['academic_year_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_settings');
    }
};


