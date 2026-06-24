<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('avatars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('heygen_avatar_id')->nullable();
            $table->string('heygen_voice_id')->nullable();
            $table->string('gender')->default('male');
            $table->string('personality')->default('professional');
            $table->string('language')->default('ar');
            $table->string('photo')->nullable();
            $table->text('intro_message')->nullable();
            $table->text('intro_message_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recruitment_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('avatar_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('seniority')->default('mid');
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('currency')->default('SAR');
            $table->longText('description')->nullable();
            $table->longText('description_ar')->nullable();
            $table->longText('requirements')->nullable();
            $table->longText('responsibilities')->nullable();
            $table->longText('benefits')->nullable();
            $table->string('interview_type')->default('text');
            $table->string('interview_language')->default('ar');
            $table->integer('max_questions')->default(12);
            $table->integer('interview_duration')->default(20);
            $table->string('status')->default('draft');
            $table->boolean('is_public')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('ai_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_job_id')->constrained()->cascadeOnDelete();
            $table->string('criterion');
            $table->string('criterion_ar')->nullable();
            $table->integer('weight')->default(10);
            $table->integer('target_score')->default(3);
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recruitment_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question');
            $table->string('question_ar')->nullable();
            $table->string('skill_category')->nullable();
            $table->string('difficulty')->default('medium');
            $table->string('language')->default('ar');
            $table->text('ideal_answer_hints')->nullable();
            $table->boolean('is_follow_up')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank');
        Schema::dropIfExists('job_criteria');
        Schema::dropIfExists('recruitment_jobs');
        Schema::dropIfExists('avatars');
        Schema::dropIfExists('departments');
    }
};
