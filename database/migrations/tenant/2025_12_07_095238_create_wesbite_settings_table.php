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
        Schema::create('wesbite_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title')->default('Welcome to Our School');
            $table->text('hero_desc')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('heroButtonText')->default('Learn More');
            $table->string('heroButtonUrl')->default('#');


            $table->integer('number_of_teachers')->default(0);
            $table->integer('number_of_students')->default(0);
            $table->integer('year_of_experience')->default(0);
            $table->integer('number_of_events')->default(0);
            $table->integer('total_awards')->default(0);
            $table->integer('total_courses')->default(0);


            $table->string('mission')->default('Our mission is to provide quality education.');
            $table->string('vision')->default('Our vision is to empower students for a better future.');


            $table->string('pass_rate')->default('95%');
            $table->string('top_score')->default('100%');
            $table->text('history');


            $table->string("principal_name")->default('John Doe')->nullable();
            $table->string("principal_image")->nullable();
            $table->text("principal_message")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wesbite_settings');
    }
};
