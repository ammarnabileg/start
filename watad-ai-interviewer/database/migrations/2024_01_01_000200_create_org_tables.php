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
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('hiring_pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('hiring_pipelines')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 110);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
            $table->unique(['pipeline_id', 'slug']);
        });

        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->enum('seniority', ['intern', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive']);
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship'])->default('full_time');
            $table->string('location', 150)->nullable();
            $table->boolean('is_remote')->default(false);
            $table->mediumText('description')->nullable();
            $table->json('responsibilities')->nullable();
            $table->json('requirements')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->char('currency', 3)->default('EGP');
            $table->unsignedBigInteger('default_template_id')->nullable(); // FK added in 000800
            $table->unsignedBigInteger('pipeline_id')->nullable();          // FK added in 000800
            $table->enum('status', ['draft', 'open', 'paused', 'closed'])->default('draft');
            $table->unsignedSmallInteger('openings')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_positions');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('hiring_pipelines');
        Schema::dropIfExists('departments');
    }
};
