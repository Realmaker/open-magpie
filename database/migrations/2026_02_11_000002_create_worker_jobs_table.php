<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('prompt');
            $table->enum('type', ['code_change', 'new_project', 'prepared'])->default('code_change');
            $table->enum('status', [
                'pending_approval', 'approved', 'queued', 'claimed',
                'running', 'done', 'failed', 'cancelled',
            ])->default('queued');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('project_path')->nullable();
            $table->string('working_directory')->nullable();
            $table->json('environment')->nullable();
            $table->longText('output')->nullable();
            $table->longText('error_output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->longText('result_summary')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['worker_id', 'status']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_jobs');
    }
};
