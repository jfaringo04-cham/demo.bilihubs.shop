<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name', 'price', 'image', 'alt_text', 'category_id', 'user_id', 'description',
        'stock', 'image_path', 'video_path', 'secondary_image_path', 'compliance_status', 'admin_notes', 'flagged_reason', 'flagged_at',
        'discount_percent', 'discounted_price', 'discount_starts_at', 'discount_ends_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'flagged_at' => 'datetime',
        'discount_starts_at' => 'datetime',
        'discount_ends_at' => 'datetime',
    ];

    public const RESUBMIT_DEADLINE_DAYS = 7;

    public function isFlagged(): bool
    {
        return $this->compliance_status === 'flagged';
    }

    public function flaggedDeadline(): ?\Carbon\Carbon
    {
        return $this->flagged_at
            ? $this->flagged_at->copy()->addDays(self::RESUBMIT_DEADLINE_DAYS)
            : null;
    }

    public function daysUntilResubmitDeadline(): ?int
    {
        if (!$this->flagged_at) {
            return null;
        }

        $deadline = $this->flaggedDeadline();
        $now = now();

        if ($now->greaterThan($deadline)) {
            return 0;
        }

        return (int) ceil($now->diffInHours($deadline) / 24);
    }

    public function isResubmitDeadlinePassed(): bool
    {
        if (!$this->flagged_at) {
            return false;
        }
        return now()->greaterThan($this->flaggedDeadline());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(Size::class, 'product_size')->withPivot('stock');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function getDisplayImageAttribute(): ?ProductImage
    {
        $primary = $this->primaryImage;
        if ($primary) {
            return $primary;
        }
        return $this->images()->first();
    }

    public function getImageUrlAttribute(): string
    {
        $img = $this->display_image;
        if ($img) {
            return $img->url;
        }
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return 'https://via.placeholder.com/300x200?text=No+Image';
    }

    public function getVideoUrlAttribute()
    {
        if ($this->video_path) {
            return asset('storage/' . $this->video_path);
        }
        return null;
    }

    public function hasVideo(): bool
    {
        return !empty($this->video_path) && file_exists(storage_path('app/public/' . $this->video_path));
    }

    public function getSecondaryImageUrlAttribute()
    {
        if ($this->secondary_image_path) {
            return asset('storage/' . $this->secondary_image_path);
        }
        return null;
    }

    public function hasSecondaryImage(): bool
    {
        return !empty($this->secondary_image_path) && file_exists(storage_path('app/public/' . $this->secondary_image_path));
    }

    public function hasActiveDiscount(): bool
    {
        if (!$this->discount_percent || $this->discount_percent <= 0) {
            return false;
        }
        $now = now();
        if ($this->discount_starts_at && $now->lt($this->discount_starts_at)) {
            return false;
        }
        if ($this->discount_ends_at && $now->gt($this->discount_ends_at)) {
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

    public function getDisplayPriceAttribute(): float
    {
        return $this->effective_price;
    }
}
