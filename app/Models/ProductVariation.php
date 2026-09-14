<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'price',
        'stock',
        'sku',
        'image',
        'image_id',
        'discount_percent',
        'discounted_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discounted_price' => 'decimal:2',
    ];

    public function hasActiveDiscount(): bool
    {
        if (!$this->discount_percent || $this->discount_percent <= 0) {
            return false;
        }
        return true;
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->hasActiveDiscount()
            ? (float) ($this->discounted_price ?? round($this->price * (1 - $this->discount_percent / 100), 2))
            : (float) $this->price;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(Size::class, 'product_variation_size')->withPivot('stock');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_id && $this->productImage) {
            return $this->productImage->url;
        }
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }
}
