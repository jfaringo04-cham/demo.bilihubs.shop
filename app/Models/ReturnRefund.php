<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRefund extends Model
{
    protected $fillable = [
        'seller_order_id', 'amount_minor', 'payout_details', 'transfer_reference',
        'sent_at', 'received_at', 'reported_at',
        'dispute_reviewed_at', 'dispute_proof_requested_at', 'dispute_proof_path',
        'dispute_proof_submitted_at', 'dispute_resolved_at', 'dispute_resolution', 'dispute_resolved_by',
    ];

    protected $hidden = ['payout_details', 'dispute_proof_path'];

    protected $casts = [
        'amount_minor' => 'integer',
        'payout_details' => 'encrypted:array',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'reported_at' => 'datetime',
        'dispute_reviewed_at' => 'datetime',
        'dispute_proof_requested_at' => 'datetime',
        'dispute_proof_submitted_at' => 'datetime',
        'dispute_resolved_at' => 'datetime',
    ];

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function getDisputeStatusLabelAttribute(): string
    {
        return match (true) {
            $this->dispute_resolved_at !== null || $this->received_at !== null => 'Resolved',
            $this->dispute_reviewed_at !== null => 'Under Review',
            default => 'Open',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->received_at !== null => 'Refund received',
            $this->reported_at !== null => 'Refund reported missing',
            $this->sent_at !== null => 'Refund sent',
            default => 'Refund pending',
        };
    }
}
