<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rider extends Model
{
    protected $fillable = [
        'user_id',
        'logistic_id',
        'vehicle_type',
        'license_number',
        'rider_documents',
        'or_document',
        'cr_document',
        'status',
        'rejection_reason',
        'approved_at',
        'max_capacity',
        'current_load',
        'availability_status',
        'assigned_zone',
        'last_active_at',
        'daily_pickups_completed',
        'daily_deliveries_completed',
        'last_quota_reset_date',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'last_active_at' => 'datetime',
        'last_quota_reset_date' => 'date',
        'max_capacity' => 'integer',
        'current_load' => 'integer',
        'daily_pickups_completed' => 'integer',
        'daily_deliveries_completed' => 'integer',
    ];

    /**
     * Login/account associated with this rider profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Logistics company this rider belongs to.
     */
    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }
}