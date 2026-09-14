<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function riders(): HasMany
    {
        return $this->hasMany(User::class, 'logistic_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function hubs(): HasMany
    {
        return $this->hasMany(Hub::class);
    }
}
