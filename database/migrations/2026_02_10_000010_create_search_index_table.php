<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->morphs('searchable');
            $table->string('title');
            $table->longText('content');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // FULLTEXT index only works with MySQL - skip for SQLite
            // Will be added when migrating to MySQL on hosting
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index');
    }
};
