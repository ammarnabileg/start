<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique(); // ULID
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_position_id')->constrained('job_positions')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('interview_templates')->nullOnDelete();
            $table->foreignId('avatar_id')->nullable()->constrained('avatars')->nullOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained('interview_invitations')->nullOnDelete();
            $table->enum('mode', ['text', 'voice', 'video'])->default('text');
            $table->char('language', 2)->default('en');
            $table->enum('status', ['scheduled', 'in_progress', 'processing', 'completed', 'abandoned', 'error'])->default('scheduled');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->enum('recommendation', ['strong_hire', 'hire', 'maybe', 'reject'])->nullable();
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('llm_input_tokens')->default(0);
            $table->unsignedInteger('llm_output_tokens')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('state')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('recommendation');
            $table->index('job_position_id');
            $table->index('created_at');
        });

        Schema::create('interview_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->enum('role', ['agent', 'candidate', 'system']);
            $table->mediumText('content');
            $table->string('audio_path', 512)->nullable();
            $table->string('competency', 40)->nullable();
            $table->string('thread_key', 60)->nullable();
            $table->boolean('is_follow_up')->default(false);
            $table->unsignedInteger('ms_offset')->nullable();
            $table->unsignedInteger('tokens')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['interview_id', 'seq']);
            $table->index('interview_id');
        });

        Schema::create('interview_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->unsignedInteger('ms_offset');
            $table->string('type', 50);
            $table->enum('severity', ['info', 'positive', 'warning', 'critical'])->default('info');
            $table->string('label', 190);
            $table->foreignId('message_id')->nullable()->constrained('interview_messages')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['interview_id', 'ms_offset']);
        });

        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->enum('kind', ['video', 'audio', 'screen', 'transcript']);
            $table->string('provider', 40)->nullable();
            $table->string('url', 512)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index('interview_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
        Schema::dropIfExists('interview_events');
        Schema::dropIfExists('interview_messages');
        Schema::dropIfExists('interviews');
    }
};
