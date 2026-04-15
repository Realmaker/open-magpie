<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('machine_id')->unique();
            $table->enum('status', ['online', 'offline', 'busy'])->default('offline');
            $table->string('version')->nullable();
            $table->string('os_info')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('current_jobs')->nullable();
            $table->integer('max_parallel_jobs')->default(2);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
