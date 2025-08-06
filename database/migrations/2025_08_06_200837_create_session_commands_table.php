<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('claude_sessions')->cascadeOnDelete();
            $table->text('command');
            $table->string('command_type')->nullable();
            $table->boolean('success')->default(true);
            $table->integer('duration_ms')->nullable();
            $table->integer('occurrences')->default(1);
            $table->timestamps();
            
            $table->index(['session_id', 'command_type']);
        });
    }
};
