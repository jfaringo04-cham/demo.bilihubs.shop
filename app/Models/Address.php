<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = ['user_id', 'label', 'address_line1', 'address_line2', 'city', 'province', 'postal_code', 'country', 'phone', 'is_default'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
