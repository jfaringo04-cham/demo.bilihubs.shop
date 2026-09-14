<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hub extends Model
{
    protected $fillable = [
        'logistic_id',
        'name',
        'address',
        'api_address',
        'latitude',
        'longitude',
        'contact_person',
        'phone',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }

    public function riders(): HasMany
    {
        return $this->hasMany(User::class, 'hub_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
