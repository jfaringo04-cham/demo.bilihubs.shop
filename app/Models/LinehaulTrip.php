<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LinehaulTrip extends Model
{
    public const STATUS_LOADING = 'loading';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'payload_limit_kg',
        'logistic_id',
        'origin_hub_id',
        'destination_hub_id',
        'driver_id',
        'trip_number',
        'status',
        'minimum_load',
        'capacity_limit',
        'scheduled_departure_at',
        'dispatched_at',
        'arrived_at',
    ];

    protected $casts = [
        'minimum_load' => 'integer',
        'capacity_limit' => 'integer',
        'scheduled_departure_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'arrived_at' => 'datetime',
    ];

    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }

    public function originHub(): BelongsTo
    {
        return $this->belongsTo(
            Hub::class,
            'origin_hub_id'
        );
    }

    public function destinationHub(): BelongsTo
    {
        return $this->belongsTo(
            Hub::class,
            'destination_hub_id'
        );
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'driver_id'
        );
    }

    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(
            Shipment::class,
            'linehaul_trip_shipments'
        )->withTimestamps();
    }

    public function parcelCount(): int
    {
        if (array_key_exists('shipments_count', $this->attributes)) {
            return (int) $this->attributes['shipments_count'];
        }

        if ($this->relationLoaded('shipments')) {
            return $this->shipments->count();
        }

        return $this->shipments()->count();
    }

    public function isFull(): bool
    {
        return $this->parcelCount() >= $this->capacity_limit;
    }

    public function minimumLoadReached(): bool
    {
        return $this->parcelCount() >= $this->minimum_load;
    }

    public function departureTimeReached(): bool
    {
        return $this->scheduled_departure_at !== null
            && now()->greaterThanOrEqualTo(
                $this->scheduled_departure_at
            );
    }

    public function canDispatch(): bool
    {
        if ($this->status !== self::STATUS_LOADING) {
            return false;
        }

        if ($this->parcelCount() === 0) {
            return false;
        }

        return $this->minimumLoadReached()
            || $this->departureTimeReached()
            || $this->isFull();
    }

    public function readinessReason(): string
    {
        if ($this->status !== self::STATUS_LOADING) {
            return ucfirst($this->status);
        }

        if ($this->parcelCount() === 0) {
            return 'Waiting for parcels';
        }

        if ($this->isFull()) {
            return 'Truck capacity reached';
        }

        if ($this->minimumLoadReached()) {
            return 'Minimum load reached';
        }

        if ($this->departureTimeReached()) {
            return 'Scheduled departure reached';
        }

        return 'Waiting for more parcels or departure time';
    }
}
