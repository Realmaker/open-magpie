<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('file_count')->default(0);
            $table->json('file_tree')->nullable();
            $table->longText('install_notes')->nullable();
            $table->json('exclude_patterns')->nullable();
            $table->string('change_note')->nullable();
            $table->enum('source', ['manual', 'claude-code', 'api'])->default('api');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['project_id', 'version']);
            $table->index(['project_id', 'created_at']);
        });

        // Add install_notes to projects table (persistent across snapshots)
        Schema::table('projects', function (Blueprint $table) {
            $table->longText('install_notes')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_snapshots');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('install_notes');
        });
    }
};
