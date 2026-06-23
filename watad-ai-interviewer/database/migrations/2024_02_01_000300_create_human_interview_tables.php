<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('job_position_id')->nullable()->constrained('job_positions')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('interview_type', 20)->nullable(); // technical|manager|department|panel|null
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->string('label', 190);
            $table->enum('type', ['rating', 'scale', 'boolean', 'select', 'text'])->default('rating');
            $table->decimal('weight', 5, 2)->default(10);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('human_interviews', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('evaluation_templates')->nullOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['technical', 'manager', 'department', 'panel'])->default('technical');
            $table->enum('mode', ['onsite', 'online'])->default('online');
            $table->string('meeting_provider', 20)->nullable(); // zoom|google_meet|ms_teams|onsite
            $table->string('meeting_url', 512)->nullable();
            $table->string('location', 255)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedSmallInteger('duration_min')->default(45);
            $table->string('timezone', 40)->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled'])->default('scheduled');
            $table->decimal('aggregate_rating', 4, 2)->nullable();
            $table->timestamps();
            $table->index('application_id');
            $table->index('status');
        });

        Schema::create('interview_panelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('human_interview_id')->constrained('human_interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 60)->nullable();
            $table->boolean('is_lead')->default(false);
            $table->boolean('responded')->default(false);
            $table->unique(['human_interview_id', 'user_id']);
        });

        Schema::create('interview_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('human_interview_id')->constrained('human_interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('evaluation_templates')->nullOnDelete();
            $table->decimal('overall_rating', 3, 1)->nullable();
            $table->enum('recommendation', ['strong_yes', 'yes', 'neutral', 'no', 'strong_no'])->nullable();
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->text('notes')->nullable();
            $table->json('criteria_scores')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['human_interview_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_evaluations');
        Schema::dropIfExists('interview_panelists');
        Schema::dropIfExists('human_interviews');
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('evaluation_templates');
    }
};
