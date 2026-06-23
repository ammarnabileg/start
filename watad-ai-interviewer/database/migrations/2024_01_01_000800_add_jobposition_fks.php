<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->foreign('default_template_id')->references('id')->on('interview_templates')->nullOnDelete();
            $table->foreign('pipeline_id')->references('id')->on('hiring_pipelines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->dropForeign(['default_template_id']);
            $table->dropForeign(['pipeline_id']);
        });
    }
};
