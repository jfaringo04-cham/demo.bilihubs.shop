<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticCodWallet extends Model
{
    protected $fillable = [
        'logistic_id',
        'available_balance_minor',
        'pending_balance_minor',
    ];

    protected $casts = [
        'available_balance_minor' => 'integer',
        'pending_balance_minor' => 'integer',
    ];

    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LogisticCodWalletTransaction::class);
    }
}
