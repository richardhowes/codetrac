<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claude_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('developer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('task_description')->nullable();
            $table->longText('transcript')->nullable();
            $table->string('working_directory');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->boolean('stop_hook_active')->default(false);
            $table->enum('status', ['active', 'completed', 'failed'])->default('active');
            $table->timestamps();
            
            $table->index(['developer_id', 'started_at']);
            $table->index(['project_id', 'started_at']);
            $table->index('status');
        });
    }
};
