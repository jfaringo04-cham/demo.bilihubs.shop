<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticWithdrawal extends Model
{
    protected $fillable = [
        'logistic_wallet_id',
        'logistic_id',
        'amount_minor',
        'payment_method',
        'account_name',
        'account_number',
        'bank_name',
        'status',
        'transaction_reference',
        'logistic_notes',
        'admin_notes',
        'requested_at',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(LogisticWallet::class, 'logistic_wallet_id');
    }

    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
