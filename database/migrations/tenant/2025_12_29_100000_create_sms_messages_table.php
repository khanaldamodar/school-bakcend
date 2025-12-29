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
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();

            // Academic year context (tenant DB)
            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();

            // Optional link to an event (for reminders)
            $table->foreignId('event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();

            // Who triggered the SMS (admin/teacher)
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_role')->nullable();

            // Target group for this SMS batch
            $table->string('target_group')->nullable(); // parents, students, teachers, all, custom

            // Recipient information
            $table->string('recipient_phone');
            $table->string('recipient_model')->nullable(); // App\Models\Admin\Student, ParentModel, Teacher, etc.
            $table->unsignedBigInteger('recipient_id')->nullable();

            // Content & status
            $table->text('message');
            $table->string('status')->default('sent'); // sent, failed
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['recipient_phone']);
            $table->index(['academic_year_id', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};


