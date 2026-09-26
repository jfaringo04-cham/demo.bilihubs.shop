<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'cart_id',
        'product_id',
        'quantity',
        'size_id',
        'variant_id',
        'is_buy_now',
    ];

    protected $casts = [
        'is_buy_now' => 'boolean',
    ];

    /**
     * New cart relationship.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Legacy direct user relationship.
     * Keep temporarily while transitioning to carts.
     */
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

    /**
     * Product variant relationship.
     *
     * Keep the relationship name variation() temporarily for compatibility
     * with existing controllers and views while using the new variant_id FK.
     */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Subtotal stored/calculated in integer centavos.
     */
    public function getSubtotalMinorAttribute(): int
    {
        $priceMinor = $this->variation
            ? (int) $this->variation->effective_price_minor
            : (int) ($this->product?->effective_price_minor ?? 0);

        return $priceMinor * (int) $this->quantity;
    }

    /**
     * Display-compatible subtotal in pesos.
     */
    public function getSubtotalAttribute(): float
    {
        return $this->subtotal_minor / 100;
    }
}
