<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('interview_id')->nullable()->constrained('interviews')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->json('extracted')->nullable();
            $table->json('highlights')->nullable();
            $table->json('gaps')->nullable();
            $table->decimal('jd_match_score', 5, 2)->nullable();
            $table->json('topics_to_probe')->nullable();
            $table->string('model', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('competency_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->string('competency', 40);
            $table->decimal('score', 5, 2);
            $table->decimal('weight', 5, 2)->default(10);
            $table->decimal('confidence', 4, 2)->nullable();
            $table->text('rationale')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->unique(['interview_id', 'competency']);
        });

        Schema::create('behavioral_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->string('personality_type', 40)->nullable();
            $table->json('disc')->nullable();
            $table->json('big_five')->nullable();
            $table->text('leadership_tendency')->nullable();
            $table->decimal('growth_mindset_score', 5, 2)->nullable();
            $table->decimal('stress_handling_score', 5, 2)->nullable();
            $table->json('risk_indicators')->nullable();
            $table->json('integrity_indicators')->nullable();
            $table->text('observations')->nullable();
            $table->string('model', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('red_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->string('type', 50);
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->index('interview_id');
            $table->index('type');
        });

        Schema::create('video_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->decimal('eye_contact_score', 5, 2)->nullable();
            $table->json('facial_expression')->nullable();
            $table->decimal('engagement_score', 5, 2)->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->decimal('nervousness_score', 5, 2)->nullable();
            $table->decimal('energy_score', 5, 2)->nullable();
            $table->decimal('attention_score', 5, 2)->nullable();
            $table->decimal('professional_appearance_score', 5, 2)->nullable();
            $table->unsignedSmallInteger('speaking_pace_wpm')->nullable();
            $table->json('body_language')->nullable();
            $table->decimal('authenticity_score', 5, 2)->nullable();
            $table->json('timeline')->nullable();
            $table->string('provider', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('interview_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->unique()->constrained('interviews')->cascadeOnDelete();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->enum('recommendation', ['strong_hire', 'hire', 'maybe', 'reject'])->nullable();
            $table->text('resume_summary')->nullable();
            $table->text('interview_summary')->nullable();
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->text('technical_assessment')->nullable();
            $table->text('behavioral_assessment')->nullable();
            $table->text('ai_analysis')->nullable();
            $table->text('hiring_recommendation')->nullable();
            $table->string('pdf_path', 512)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('model', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_reports');
        Schema::dropIfExists('video_analyses');
        Schema::dropIfExists('red_flags');
        Schema::dropIfExists('behavioral_analyses');
        Schema::dropIfExists('competency_scores');
        Schema::dropIfExists('cv_analyses');
    }
};
