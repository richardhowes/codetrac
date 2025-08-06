<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'path',
        'description',
        'total_sessions',
        'total_tokens_used',
        'total_cost',
        'total_lines_written',
        'total_minutes_spent',
        'last_activity_at',
    ];

    protected $casts = [
        'total_cost' => 'decimal:4',
        'last_activity_at' => 'datetime',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(ClaudeSession::class);
    }

    public static function findOrCreateByPath(string $path): self
    {
        $name = basename($path);
        
        return static::firstOrCreate(
            ['path' => $path],
            ['name' => $name]
        );
    }
}
