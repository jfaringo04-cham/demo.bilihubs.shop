<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Logistic extends Model
{
    protected $fillable = [
        'owner_user_id',
        'company_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'api_address',
        'latitude',
        'longitude',
        'status',
        'rejection_reason',
        'approved_at',
        'logo',
        'business_permit',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Legacy relationship.
     * Riders are still represented by User records in the current system.
     */
    public function riders(): HasMany
    {
        return $this->hasMany(User::class, 'logistic_id');
    }

    /**
     * New Milestone 4 rider profile relationship.
     */
    public function riderProfiles(): HasMany
    {
        return $this->hasMany(Rider::class, 'logistic_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Seller orders assigned to this logistics provider.
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class, 'logistic_id');
    }

    public function hubs(): HasMany
    {
        return $this->hasMany(Hub::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
    }

        try {
            if (Storage::disk('s3')->exists($this->logo)) {
                return Storage::disk('s3')->url($this->logo);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (Storage::disk('public')->exists($this->logo)) {
            return Storage::disk('public')->url($this->logo);
        }

        return null;
    }
}
