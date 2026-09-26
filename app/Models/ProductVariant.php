<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'name',
        'color',
        'size',
        'price_minor',
        'stock',
        'sku',
        'image',
        'image_id',
        'discount_percent',
        'discounted_price_minor',
        'attributes',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'discount_percent' => 'decimal:2',
        'discounted_price_minor' => 'integer',
        'attributes' => 'array',
    ];

    public function hasActiveDiscount(): bool
    {
        if (!$this->discount_percent || $this->discount_percent <= 0) {
            return false;
        }

        if ($this->product) {
            $now = now();

            if ($this->product->discount_starts_at && $now->lt($this->product->discount_starts_at)) {
                return false;
            }

            if ($this->product->discount_ends_at && $now->gt($this->product->discount_ends_at)) {
                return false;
            }
        }

        return true;
    }

    public function getEffectivePriceMinorAttribute(): int
    {
        $priceMinor = $this->price_minor !== null
            ? (int) $this->price_minor
            : (int) ($this->product?->price_minor ?? 0);

        if (!$this->hasActiveDiscount()) {
            return $priceMinor;
        }

        if ($this->discounted_price_minor !== null) {
            return (int) $this->discounted_price_minor;
        }

        return (int) round(
            $priceMinor * (1 - ((float) $this->discount_percent / 100))
        );
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->effective_price_minor / 100;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->color && $this->size) {
            return $this->color . ' / ' . $this->size;
        }

        if ($this->color) {
            return $this->color;
        }

        if ($this->size) {
            return $this->size;
        }

        return $this->name ?? '';
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
        return $this->belongsToMany(
            Size::class,
            'product_variant_size',
            'product_variant_id',
            'size_id'
        )->withPivot('stock');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_id && $this->productImage) {
            return $this->productImage->url;
        }

        if ($this->image) {
            return asset('storage/' . $this->image);
        }

        if ($this->product && $this->product->image_url) {
            return $this->product->image_url;
        }

        return null;
    }
}

