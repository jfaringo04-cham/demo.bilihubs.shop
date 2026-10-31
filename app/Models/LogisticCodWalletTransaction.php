<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticCodWalletTransaction extends Model
{
    protected $fillable = [
        'logistic_cod_wallet_id',
        'cod_remittance_id',
        'logistic_admin_remittance_id',
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
        return $this->belongsTo(
            LogisticCodWallet::class,
            'logistic_cod_wallet_id'
        );
    }

    public function codRemittance(): BelongsTo
    {
        return $this->belongsTo(CodRemittance::class);
    }

    public function adminRemittance(): BelongsTo
    {
        return $this->belongsTo(
            LogisticAdminRemittance::class,
            'logistic_admin_remittance_id'
        );
    }
}
