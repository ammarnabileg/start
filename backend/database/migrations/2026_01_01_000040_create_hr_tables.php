<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_by')->constrained('users');
            $table->json('interviewers')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('location')->nullable();
            $table->string('type')->default('technical');
            $table->string('status')->default('scheduled');
            $table->timestamp('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('human_interview_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('human_interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluated_by')->constrained('users');
            $table->integer('technical_depth')->default(0);
            $table->integer('problem_solving')->default(0);
            $table->integer('communication')->default(0);
            $table->integer('culture_fit')->default(0);
            $table->integer('takes_ownership')->default(0);
            $table->integer('seniority_fit')->default(0);
            $table->integer('overall_rating')->default(0);
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->string('recommendation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->decimal('salary', 12, 2);
            $table->string('currency')->default('SAR');
            $table->string('employment_type')->default('full_time');
            $table->date('start_date')->nullable();
            $table->text('benefits')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('candidate_response')->nullable();
            $table->text('candidate_notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('talent_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->string('color')->default('#7C3AED');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('talent_pool_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['talent_pool_id', 'candidate_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->text('body')->nullable();
            $table->text('body_ar')->nullable();
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->integer('rating')->default(0);
            $table->text('feedback')->nullable();
            $table->text('suggestions')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature');
            $table->string('model')->default('gpt-4o');
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->bigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('feedback_responses');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('talent_pool_candidates');
        Schema::dropIfExists('talent_pools');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('human_interview_evaluations');
        Schema::dropIfExists('human_interviews');
    }
};
