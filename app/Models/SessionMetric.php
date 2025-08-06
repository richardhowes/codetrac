<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionMetric extends Model
{
    protected $fillable = [
        'session_id',
        'tokens_used',
        'input_tokens',
        'output_tokens',
        'cost',
        'lines_written',
        'lines_read',
        'files_created',
        'files_modified',
        'files_read',
        'commands_executed',
        'api_calls',
        'model_used',
        'input_cost_per_1k',
        'output_cost_per_1k',
    ];

    protected $casts = [
        'cost' => 'decimal:6',
        'input_cost_per_1k' => 'decimal:6',
        'output_cost_per_1k' => 'decimal:6',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClaudeSession::class, 'session_id');
    }

    public function calculateCost(): float
    {
        if (!$this->input_tokens || !$this->output_tokens) {
            return 0;
        }

        $inputCost = ($this->input_tokens / 1000) * ($this->input_cost_per_1k ?? 0.015);
        $outputCost = ($this->output_tokens / 1000) * ($this->output_cost_per_1k ?? 0.075);

        return round($inputCost + $outputCost, 6);
    }
}
