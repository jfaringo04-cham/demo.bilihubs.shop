<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price_minor',
        'image',
        'alt_text',
        'category_id',
        'user_id',
        'seller_id',
        'description',
        'stock',
        'image_path',
        'video_path',
        'secondary_image_path',
        'compliance_status',
        'admin_notes',
        'flagged_reason',
        'flagged_at',
        'discount_percent',
        'discounted_price_minor',
        'discount_starts_at',
        'discount_ends_at',
        'subcategory_id',
        'sku',
        'low_stock_threshold',
        'attributes',
        'status',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'discount_percent' => 'decimal:2',
        'discounted_price_minor' => 'integer',
        'flagged_at' => 'datetime',
        'discount_starts_at' => 'datetime',
        'discount_ends_at' => 'datetime',
        'attributes' => 'array',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const RESUBMIT_DEADLINE_DAYS = 7;

    public function isDraft(): bool
    {
        return ($this->status ?? self::STATUS_PUBLISHED) === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return !$this->isDraft();
    }

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

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    /**
     * New Milestone 4 relationship.
     *
     * products.seller_id -> sellers.id
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * Legacy seller-user relationship.
     *
     * products.user_id -> users.id
     *
     * Temporary habang ginagamit pa ng ibang parts
     * ng existing application ang user_id.
     */
    public function sellerUser(): BelongsTo
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
        return $this->belongsToMany(Size::class, 'product_size')
            ->withPivot('stock');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /**
     * Legacy compatibility alias.
     */
    public function variations(): HasMany
    {
        return $this->variants();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Image / Media Helpers
    |--------------------------------------------------------------------------
    */

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

    // Preferred: ProductImage record
    if ($img) {
        return $img->url;
    }

    // Fallback: legacy products.image / image_path
    $path = $this->image ?: $this->image_path;

    if ($path) {
        // New Supabase Storage
        try {
            if (Storage::disk('s3')->exists($path)) {
                return Storage::disk('s3')->url($path);
            }
        } catch (\Throwable $e) {
            // Fall back to old local storage.
        }

        // Old local public storage
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }
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
        return !empty($this->video_path)
            && file_exists(storage_path('app/public/' . $this->video_path));
    }

    public function getSecondaryImageUrlAttribute(): ?string
{
    if (!$this->secondary_image_path) {
        return null;
    }

    try {
        if (Storage::disk('s3')->exists($this->secondary_image_path)) {
            return Storage::disk('s3')->url($this->secondary_image_path);
        }
    } catch (\Throwable $e) {
        // Fall back to old local storage below.
    }

    if (Storage::disk('public')->exists($this->secondary_image_path)) {
        return Storage::disk('public')->url($this->secondary_image_path);
    }

    return null;
}

public function hasSecondaryImage(): bool
{
    if (!$this->secondary_image_path) {
        return false;
    }

    try {
        if (Storage::disk('s3')->exists($this->secondary_image_path)) {
            return true;
        }
    } catch (\Throwable $e) {
        // Check old local storage below.
    }

    return Storage::disk('public')->exists($this->secondary_image_path);
}

    /*
    |--------------------------------------------------------------------------
    | Discount Helpers
    |--------------------------------------------------------------------------
    */

    public function hasActiveDiscount(): bool
    {
        if (!$this->discount_percent || $this->discount_percent <= 0) {
            return false;
        }

        $now = now();

        if (
            $this->discount_starts_at
            && $now->lt($this->discount_starts_at)
        ) {
            return false;
        }

        if (
            $this->discount_ends_at
            && $now->gt($this->discount_ends_at)
        ) {
            return false;
        }

        return true;
    }

    public function getEffectivePriceMinorAttribute(): int
    {
        $priceMinor = (int) ($this->price_minor ?? 0);

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

    public function getDisplayPriceAttribute(): float
    {
        return $this->effective_price_minor / 100;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeTrending($query, $limit = 8, $days = 30)
    {
        return $query
            ->where('compliance_status', 'approved')
            ->whereHas('orderItems.sellerOrder.order', function ($q) use ($days) {
                $q->whereIn('status', ['completed', 'delivered'])
                    ->where(
                        'created_at',
                        '>=',
                        now()->subDays($days)
                    );
            })
            ->withSum(
                [
                    'orderItems as sold_count' => function ($query) use ($days) {
                        $query
                            ->join(
                                'seller_orders',
                                'seller_orders.id',
                                '=',
                                'order_items.seller_order_id'
                            )
                            ->join(
                                'orders',
                                'orders.id',
                                '=',
                                'seller_orders.order_id'
                            )
                            ->whereIn(
                                'orders.status',
                                ['completed', 'delivered']
                            )
                            ->where(
                                'orders.created_at',
                                '>=',
                                now()->subDays($days)
                            );
                    },
                ],
                'quantity'
            )
            ->orderByDesc('sold_count')
            ->limit($limit);
    }
}


