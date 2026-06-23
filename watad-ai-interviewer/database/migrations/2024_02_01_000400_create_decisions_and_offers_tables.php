<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 40);
            $table->enum('decision', ['advance', 'hold', 'reject', 'approve', 'make_offer']);
            $table->boolean('ai_overridden')->default(false);
            $table->text('reason')->nullable();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('application_id');
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 190)->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->date('start_date')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['draft', 'sent', 'viewed', 'accepted', 'declined', 'expired', 'withdrawn'])->default('draft');
            $table->string('letter_path', 512)->nullable();
            $table->string('signature_path', 512)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('application_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('hiring_decisions');
    }
};
