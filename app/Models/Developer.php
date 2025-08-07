<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Developer extends Model
{
    protected $fillable = [
        'user_id',
        'username',
        'hostname',
        'machine_id',
        'os_type',
        'os_version',
        'architecture',
        'ip_address',
        'claude_version',
        'total_sessions',
        'total_tokens_used',
        'total_cost',
        'total_lines_written',
        'total_minutes_spent',
    ];

    protected $casts = [
        'total_cost' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClaudeSession::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public static function findOrCreateByMetadata(array $metadata): self
    {
        return static::firstOrCreate(
            [
                'username' => $metadata['username'],
                'hostname' => $metadata['hostname'],
                'machine_id' => $metadata['machine_id'] ?? null,
            ],
            [
                'os_type' => $metadata['os_type'],
                'os_version' => $metadata['os_version'],
                'architecture' => $metadata['architecture'],
                'ip_address' => $metadata['ip_address'] ?? null,
                'claude_version' => $metadata['claude_version'] ?? null,
            ]
        );
    }
}
