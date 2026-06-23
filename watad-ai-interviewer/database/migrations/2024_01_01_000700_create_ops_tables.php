<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['email', 'whatsapp', 'inapp']);
            $table->string('event', 60);
            $table->string('recipient', 190);
            $table->string('notifiable_type', 120)->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed', 'delivered', 'read'])->default('queued');
            $table->string('error', 512)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index(['notifiable_type', 'notifiable_id']);
        });

        Schema::create('sheet_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->string('spreadsheet_id', 120);
            $table->string('sheet_tab', 120)->default('Candidates');
            $table->unsignedInteger('row_number')->nullable();
            $table->enum('status', ['pending', 'synced', 'failed'])->default('pending');
            $table->string('error', 512)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('user_id');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sheet_syncs');
        Schema::dropIfExists('notifications');
    }
};
