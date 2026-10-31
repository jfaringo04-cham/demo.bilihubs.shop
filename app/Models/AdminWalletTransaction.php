<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminWalletTransaction extends Model
{
    protected $fillable = [
        'admin_wallet_id',
        'logistic_admin_remittance_id',
        'seller_settlement_id',
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
        return $this->belongsTo(AdminWallet::class);
    }

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(
            LogisticAdminRemittance::class,
            'logistic_admin_remittance_id'
        );
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(
            SellerSettlement::class,
            'seller_settlement_id'
        );
    }
}
