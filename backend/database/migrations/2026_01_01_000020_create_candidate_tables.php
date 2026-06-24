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
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->integer('years_experience')->default(0);
            $table->decimal('target_salary', 12, 2)->nullable();
            $table->string('target_salary_currency')->default('SAR');
            $table->string('linkedin_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->text('bio')->nullable();
            $table->string('preferred_language')->default('ar');
            $table->json('skills')->nullable();
            $table->json('languages')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('candidate_cvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->default('pdf');
            $table->bigInteger('file_size')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->json('parsed_data')->nullable();
            $table->float('parsing_confidence')->default(0);
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recruitment_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_cv_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('applied');
            $table->string('pipeline_stage')->default('applied');
            $table->float('cv_match_score')->default(0);
            $table->float('overall_score')->default(0);
            $table->string('ai_recommendation')->nullable();
            $table->text('hr_notes')->nullable();
            $table->json('cv_analysis')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['recruitment_job_id', 'candidate_id']);
        });

        Schema::create('invitation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('interview_type')->default('text');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_links');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('candidate_cvs');
        Schema::dropIfExists('candidates');
    }
};
