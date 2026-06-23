<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The pipeline spine: one row per candidate per job.
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_position_id')->constrained('job_positions')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('ai_interview_id')->nullable()->constrained('interviews')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'applied', 'ai_screening', 'qualified', 'disqualified', 'tech_interview',
                'manager_interview', 'final_review', 'offer', 'hired', 'rejected', 'withdrawn',
            ])->default('applied');
            $table->string('source', 60)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['candidate_id', 'job_position_id']);
            $table->index('status');
            $table->index('stage_id');
        });

        // Master-profile timeline (also per-application when application_id set).
        Schema::create('candidate_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('job_applications')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('actor_type', 20)->default('user'); // user | system | candidate
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('summary', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->nullable();
            $table->index(['candidate_id', 'occurred_at']);
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['cv', 'portfolio', 'certificate', 'attachment'])->default('cv');
            $table->string('label', 190)->nullable();
            $table->string('path', 512);
            $table->string('original_name', 255)->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('job_applications')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->enum('visibility', ['internal', 'private'])->default('internal');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('candidate_tag', function (Blueprint $table) {
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['candidate_id', 'tag_id']);
        });

        Schema::create('talent_pool_candidate', function (Blueprint $table) {
            $table->foreignId('talent_pool_id')->constrained('talent_pools')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->primary(['talent_pool_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_pool_candidate');
        Schema::dropIfExists('candidate_tag');
        Schema::dropIfExists('candidate_notes');
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidate_activities');
        Schema::dropIfExists('job_applications');
    }
};
