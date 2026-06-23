<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 190);
            $table->string('email', 190)->index();
            $table->string('phone', 40)->nullable();
            $table->string('linkedin_url', 512)->nullable();
            $table->string('country', 80)->nullable();
            $table->decimal('years_experience', 4, 1)->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->string('notice_period', 60)->nullable();
            $table->string('cv_path', 512)->nullable();
            $table->string('cv_original_name', 255)->nullable();
            $table->mediumText('cv_text')->nullable();
            $table->string('source', 60)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('candidate_pipeline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_position_id')->constrained('job_positions')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->unique(['candidate_id', 'job_position_id']);
        });

        Schema::create('interview_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_position_id')->constrained('job_positions')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('interview_templates')->nullOnDelete();
            $table->foreignId('avatar_id')->nullable()->constrained('avatars')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('token', 40)->unique();
            $table->string('email', 190)->nullable();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->enum('status', ['pending', 'opened', 'started', 'completed', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_invitations');
        Schema::dropIfExists('candidate_pipeline');
        Schema::dropIfExists('candidates');
    }
};
