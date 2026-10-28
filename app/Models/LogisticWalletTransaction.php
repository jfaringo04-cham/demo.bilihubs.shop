<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticWalletTransaction extends Model
{
    protected $fillable = [
        'logistic_wallet_id',
        'shipment_id',
        'type',
        'amount_minor',
        'balance_after_minor',
        'reference',
        'description',
        'meta',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'balance_after_minor' => 'integer',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(LogisticWallet::class, 'logistic_wallet_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
