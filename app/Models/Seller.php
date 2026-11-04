<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo_path',
        'banner_path',
        'status',
        'rejection_reason',
        'commission_bps',
        'pickup_address_id',
        'is_on_vacation',
        'vacation_ends_on',
    ];

    /**
     * User/account that owns this shop.
     */
    protected $casts = [
        'is_on_vacation' => 'boolean',
        'vacation_ends_on' => 'date',
    ];

    public function isOnVacation(): bool
    {
        if (!$this->is_on_vacation) {
            return false;
        }

        return $this->vacation_ends_on === null
            || $this->vacation_ends_on->greaterThanOrEqualTo(
                now()->startOfDay()
            );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Products that belong to this shop.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Seller-specific orders that belong to this shop.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * Pickup address of this shop.
     */
    public function pickupAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    /**
     * Vouchers created by this seller.
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'seller_id')
            ->where('owner_type', 'seller');
    }
}