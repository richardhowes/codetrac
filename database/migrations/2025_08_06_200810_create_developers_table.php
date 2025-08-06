<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username');
            $table->string('hostname');
            $table->string('machine_id')->nullable();
            $table->string('os_type');
            $table->string('os_version');
            $table->string('architecture');
            $table->string('ip_address')->nullable();
            $table->string('claude_version')->nullable();
            $table->integer('total_sessions')->default(0);
            $table->integer('total_tokens_used')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->integer('total_lines_written')->default(0);
            $table->integer('total_minutes_spent')->default(0);
            $table->timestamps();
            
            $table->unique(['username', 'hostname', 'machine_id']);
            $table->index('username');
        });
    }
};
