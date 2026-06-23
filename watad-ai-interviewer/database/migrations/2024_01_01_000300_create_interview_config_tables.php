<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avatars', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('role_label', 120);
            $table->enum('gender', ['female', 'male', 'neutral'])->default('neutral');
            $table->text('personality');
            $table->enum('questioning_style', ['friendly', 'formal', 'probing', 'rapid', 'socratic'])->default('friendly');
            $table->char('language', 2)->default('en');
            $table->string('voice_provider', 40)->nullable();
            $table->string('voice_id', 120)->nullable();
            $table->string('video_provider', 40)->nullable();
            $table->string('video_replica_id', 120)->nullable();
            $table->string('avatar_image_url', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('interview_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('avatar_id')->nullable()->constrained('avatars')->nullOnDelete();
            $table->enum('mode', ['text', 'voice', 'video'])->default('text');
            $table->char('language', 2)->default('en');
            $table->text('intro_script')->nullable();
            $table->unsignedSmallInteger('min_questions')->default(6);
            $table->unsignedSmallInteger('max_questions')->default(14);
            $table->unsignedSmallInteger('max_duration_min')->default(25);
            $table->enum('difficulty', ['adaptive', 'easy', 'standard', 'hard'])->default('adaptive');
            $table->unsignedTinyInteger('follow_up_depth')->default(2);
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('template_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('interview_templates')->cascadeOnDelete();
            $table->string('competency', 40);
            $table->decimal('weight', 5, 2)->default(10);
            $table->boolean('is_enabled')->default(true);
            $table->unique(['template_id', 'competency']);
        });

        Schema::create('question_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('question_libraries')->cascadeOnDelete();
            $table->string('competency', 40);
            $table->string('seniority', 20)->nullable();
            $table->text('text');
            $table->text('text_ar')->nullable();
            $table->text('expected_signals')->nullable();
            $table->enum('difficulty', ['easy', 'standard', 'hard'])->default('standard');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('competency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_libraries');
        Schema::dropIfExists('template_competencies');
        Schema::dropIfExists('interview_templates');
        Schema::dropIfExists('avatars');
    }
};
