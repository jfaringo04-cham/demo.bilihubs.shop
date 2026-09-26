<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'seller_order_id',
        'product_id',
        'product_name',
        'price_minor',
        'quantity',
        'subtotal_minor',
        'size_id',
        'variant_id',
    ];

    protected $casts = [

        // Milestone 4 integer centavo fields.
        'price_minor' => 'integer',
        'subtotal_minor' => 'integer',
    ];

    /**
     * Seller-specific parent order.
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
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
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Legacy compatibility alias.
     */
    public function variation(): BelongsTo
    {
        return $this->variant();
    }
}

