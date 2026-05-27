<?php

namespace DbLiteAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryHistory extends Model
{
    protected $fillable = [
        'user_id',
        'database_connection_id',
        'sql',
        'query_type',
        'status',
        'message',
        'affected_rows',
        'duration_ms',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'affected_rows' => 'integer',
            'duration_ms' => 'integer',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class));
    }

    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
    }
}
