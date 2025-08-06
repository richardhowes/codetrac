<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionCommand extends Model
{
    protected $fillable = [
        'session_id',
        'command',
        'command_type',
        'success',
        'duration_ms',
        'occurrences',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClaudeSession::class, 'session_id');
    }

    public static function extractCommandType(string $command): ?string
    {
        $parts = explode(' ', trim($command));
        return $parts[0] ?? null;
    }
}
