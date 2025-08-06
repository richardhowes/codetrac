<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('claude_sessions')->cascadeOnDelete();
            $table->integer('tokens_used')->default(0);
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost', 10, 6)->default(0);
            $table->integer('lines_written')->default(0);
            $table->integer('lines_read')->default(0);
            $table->integer('files_created')->default(0);
            $table->integer('files_modified')->default(0);
            $table->integer('files_read')->default(0);
            $table->integer('commands_executed')->default(0);
            $table->integer('api_calls')->default(0);
            $table->string('model_used')->nullable();
            $table->decimal('input_cost_per_1k', 8, 6)->nullable();
            $table->decimal('output_cost_per_1k', 8, 6)->nullable();
            $table->timestamps();
            
            $table->unique('session_id');
        });
    }
};
