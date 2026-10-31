<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Logistic extends Model
{
    use HasFactory;

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

        // Company registration
        'sec_registration_number',
        'tin',

        // Business address
        'business_region',
        'business_province',
        'business_city',
        'business_barangay',
        'business_street',
        'business_postal_code',

        // Authorized contact person
        'contact_first_name',
        'contact_last_name',
        'contact_position',

        // Operations
        'service_areas',

        // Private registration documents
        'sec_certificate',
        'bir_form_2303',
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

    public function wallet()
    {
        return $this->hasOne(LogisticWallet::class);
    }

    public function walletTransactions()
    {
        return $this->hasManyThrough(
            LogisticWalletTransaction::class,
            LogisticWallet::class,
            'logistic_id',
            'logistic_wallet_id',
            'id',
            'id'
        );
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(LogisticWithdrawal::class);
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


