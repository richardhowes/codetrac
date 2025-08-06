<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionFile extends Model
{
    protected $fillable = [
        'session_id',
        'file_path',
        'file_name',
        'file_extension',
        'operation',
        'lines_affected',
        'occurrences',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClaudeSession::class, 'session_id');
    }

    public static function extractExtension(string $filePath): ?string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        return $extension ?: null;
    }
}
