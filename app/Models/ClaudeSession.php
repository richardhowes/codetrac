<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClaudeSession extends Model
{
    protected $table = 'claude_sessions';

    protected $fillable = [
        'session_id',
        'developer_id',
        'project_id',
        'task_description',
        'transcript',
        'working_directory',
        'started_at',
        'ended_at',
        'duration_minutes',
        'stop_hook_active',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'stop_hook_active' => 'boolean',
    ];

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(SessionMetric::class, 'session_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(SessionFile::class, 'session_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(SessionCommand::class, 'session_id');
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'duration_minutes' => $this->started_at->diffInMinutes(now()),
        ]);
    }
}
