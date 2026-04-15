<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users');
            $table->foreignId('shared_with_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('shared_with_email');
            $table->enum('permission', ['viewer', 'editor', 'admin'])->default('viewer');
            $table->string('invite_token', 128)->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // A user/email can only have one active share per project
            $table->unique(['project_id', 'shared_with_email']);

            $table->index(['shared_with_user_id', 'accepted_at']);
            $table->index('invite_token');
            $table->index('shared_with_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_shares');
    }
};
