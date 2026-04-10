<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

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
        return $this->belongsTo(User::class);
    }

    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
    }
}
