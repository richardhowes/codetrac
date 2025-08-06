<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->text('description')->nullable();
            $table->integer('total_sessions')->default(0);
            $table->integer('total_tokens_used')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->integer('total_lines_written')->default(0);
            $table->integer('total_minutes_spent')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            $table->unique('path');
            $table->index('name');
        });
    }
};
