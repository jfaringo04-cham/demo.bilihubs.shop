<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    public const OUTCOMES = [
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'redirected' => 'Redirected',
        'failed' => 'Failed',
    ];

    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_name', 'area', 'action', 'targets',
        'outcome', 'http_status', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'targets' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
