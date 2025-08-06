<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('claude_sessions')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_extension')->nullable();
            $table->enum('operation', ['read', 'write', 'create', 'delete', 'edit']);
            $table->integer('lines_affected')->default(0);
            $table->integer('occurrences')->default(1);
            $table->timestamps();
            
            $table->index(['session_id', 'operation']);
            $table->index('file_extension');
        });
    }
};
