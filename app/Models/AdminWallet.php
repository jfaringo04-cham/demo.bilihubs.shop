<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminWallet extends Model
{
    protected $fillable = [
        'available_balance_minor',
    ];

    protected $casts = [
        'available_balance_minor' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(AdminWalletTransaction::class);
    }
}
