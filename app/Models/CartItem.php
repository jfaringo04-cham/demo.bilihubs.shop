<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'product_id', 'quantity', 'size_id', 'variation_id', 'is_buy_now'];

    protected $casts = [
        'is_buy_now' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function getSubtotalAttribute(): float
    {
        $price = $this->variation
            ? (float) $this->variation->effective_price
            : (float) ($this->product->effective_price ?? $this->product->price);
        return $price * $this->quantity;
    }
}
