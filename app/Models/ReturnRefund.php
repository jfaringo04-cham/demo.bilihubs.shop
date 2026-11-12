<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRefund extends Model
{
    protected $fillable = [
        'seller_order_id', 'amount_minor', 'payout_details', 'transfer_reference',
        'sent_at', 'received_at', 'reported_at',
    ];

    protected $hidden = ['payout_details'];

    protected $casts = [
        'amount_minor' => 'integer',
        'payout_details' => 'encrypted:array',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

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
