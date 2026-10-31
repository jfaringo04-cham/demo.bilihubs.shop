<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticAdminRemittance extends Model
{
    protected $fillable = [
        'reference',
        'logistic_id',
        'amount_minor',
        'status',
        'transfer_method',
        'transfer_reference',
        'notes',
        'rejection_reason',
        'submitted_by',
        'confirmed_by',
        'rejected_by',
        'submitted_at',
        'confirmed_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
