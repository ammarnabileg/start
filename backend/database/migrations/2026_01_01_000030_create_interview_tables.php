<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invitation_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('text');
            $table->string('status')->default('pending');
            $table->integer('questions_asked')->default(0);
            $table->integer('max_questions')->default(12);
            $table->integer('duration_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('detected_language')->nullable();
            $table->timestamps();
        });

        Schema::create('interview_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_session_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('user');
            $table->longText('content');
            $table->string('message_type')->default('text');
            $table->integer('question_number')->nullable();
            $table->boolean('is_follow_up')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interview_session_id')->nullable()->constrained()->nullOnDelete();
            $table->float('overall_score')->default(0);
            $table->string('recommendation')->nullable();
            $table->text('executive_summary')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('missing_skills')->nullable();
            $table->json('criteria_scores')->nullable();
            $table->json('raw_response')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('skill_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('skill_key');
            $table->string('skill_name');
            $table->string('skill_name_ar')->nullable();
            $table->float('score')->default(0);
            $table->float('weight')->default(0);
            $table->float('confidence')->default(0);
            $table->text('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('behavioral_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->json('disc_profile')->nullable();
            $table->json('big_five')->nullable();
            $table->float('growth_score')->default(0);
            $table->float('stress_score')->default(0);
            $table->string('leadership_style')->nullable();
            $table->string('learning_ability')->nullable();
            $table->text('cultural_fit_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('risk_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('flag_type');
            $table->string('severity')->default('low');
            $table->text('description');
            $table->text('evidence')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_flags');
        Schema::dropIfExists('behavioral_analyses');
        Schema::dropIfExists('skill_scores');
        Schema::dropIfExists('ai_evaluations');
        Schema::dropIfExists('interview_messages');
        Schema::dropIfExists('interview_sessions');
    }
};
