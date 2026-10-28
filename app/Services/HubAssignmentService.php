<?php

namespace App\Services;

use App\Models\Hub;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class HubAssignmentService
{
    /**
     * Legacy order-level lookup retained for compatibility.
     */
    public static function findBestHubForOrder(Order $order): ?Hub
    {
        $order->loadMissing([
            'sellerOrders.seller.owner',
            'sellerOrders.seller.pickupAddress',
        ]);

        $sellerOrder = $order->sellerOrders->first();

        return $sellerOrder
            ? self::findBestHubForSellerOrder($sellerOrder)
            : self::fallbackHub();
    }

    /**
     * Legacy single-hub lookup.
     *
     * For new shipments this returns the origin hub. Call
     * findRoutingHubsForSellerOrder() when both ends are needed.
     */
    public static function findBestHubForSellerOrder(SellerOrder $sellerOrder): ?Hub
    {
        return self::findRoutingHubsForSellerOrder($sellerOrder)['origin'] ?? null;
    }

    /**
     * Resolve the pickup/origin hub and buyer/destination hub.
     *
     * Deterministic fallback priority:
     * 1. Exact barangay coverage.
     * 2. Same city/municipality coverage.
     * 3. Same province coverage.
     * 4. Explicitly configured default hub.
     * 5. Unresolved (no silent/random hub assignment).
     *
     * Destination hub is kept inside the same logistics company as the
     * selected origin hub so one shipment stays under one provider.
     *
     * @return array{
     *     origin:?Hub,
     *     destination:?Hub,
     *     origin_match:string,
     *     destination_match:string
     * }
     */
    public static function findRoutingHubsForSellerOrder(SellerOrder $sellerOrder): array
    {
        $sellerOrder->loadMissing([
            'order.user',
            'seller.owner',
            'seller.pickupAddress',
        ]);

        $originLocality = self::sellerLocality($sellerOrder);
        $destinationLocality = self::destinationLocality($sellerOrder);
        $preferredLogisticId = $sellerOrder->logistic_id
            ? (int) $sellerOrder->logistic_id
            : null;

        $originResolution = self::resolveHubByLocality(
            barangay: $originLocality['barangay'],
            city: $originLocality['city'],
            province: $originLocality['province'],
            logisticId: $preferredLogisticId,
        );

        $origin = $originResolution['hub'];
        $originMatch = $originResolution['match'];

        if (!$origin) {
            $origin = self::fallbackHub($preferredLogisticId);
            $originMatch = $origin ? 'default_hub' : 'unresolved';
        }

        if (!$origin) {
            return [
                'origin' => null,
                'destination' => null,
                'origin_match' => 'unresolved',
                'destination_match' => 'unresolved',
            ];
        }

        $destinationResolution = self::resolveHubByLocality(
            barangay: $destinationLocality['barangay'],
            city: $destinationLocality['city'],
            province: $destinationLocality['province'],
            logisticId: (int) $origin->logistic_id,
        );

        $destination = $destinationResolution['hub'];
        $destinationMatch = $destinationResolution['match'];

        if (!$destination) {
            $destination = self::fallbackHub((int) $origin->logistic_id);
            $destinationMatch = $destination ? 'default_hub' : 'unresolved';
        }

        return [
            'origin' => $origin,
            'destination' => $destination,
            'origin_match' => $originMatch,
            'destination_match' => $destinationMatch,
        ];
    }

    /**
     * Backward-compatible hub lookup that returns only the hub model.
     */
    public static function findHubByLocality(
        ?string $barangay,
        ?string $city,
        ?string $province,
        ?int $logisticId = null
    ): ?Hub {
        return self::resolveHubByLocality(
            $barangay,
            $city,
            $province,
            $logisticId
        )['hub'];
    }

    /**
     * @return array{hub:?Hub,match:string}
     */
    public static function resolveHubByLocality(
        ?string $barangay,
        ?string $city,
        ?string $province,
        ?int $logisticId = null
    ): array {
        $barangay = self::normalize($barangay);
        $city = self::normalize($city);
        $province = self::normalize($province);

        $base = Hub::query()
            ->where('status', 'active');

        if ($logisticId !== null) {
            $base->where('logistic_id', $logisticId);
        }

        if ($barangay !== null) {
            $exact = (clone $base)
                ->whereRaw('LOWER(TRIM(barangay)) = ?', [$barangay]);

            self::applyOptionalLocality($exact, 'city', $city);
            self::applyOptionalLocality($exact, 'province', $province);

            $hub = $exact->orderBy('id')->first();

            if ($hub) {
                return ['hub' => $hub, 'match' => 'exact_barangay'];
            }
        }

        if ($city !== null) {
            $cityQuery = (clone $base)
                ->whereRaw('LOWER(TRIM(city)) = ?', [$city]);

            self::applyOptionalLocality($cityQuery, 'province', $province);

            $hub = $cityQuery->orderBy('id')->first();

            if ($hub) {
                return ['hub' => $hub, 'match' => 'city_fallback'];
            }
        }

        if ($province !== null) {
            $hub = (clone $base)
                ->whereRaw('LOWER(TRIM(province)) = ?', [$province])
                ->orderBy('id')
                ->first();

            if ($hub) {
                return ['hub' => $hub, 'match' => 'province_fallback'];
            }
        }

        return ['hub' => null, 'match' => 'unresolved'];
    }

    public static function findBestHubByCoordinates(
        float $lat,
        float $lng,
        ?int $logisticId = null
    ): ?Hub {
        return self::findNearestHub($lat, $lng, $logisticId);
    }

    public static function fallbackHub(?int $logisticId = null): ?Hub
    {
        $query = Hub::query()
            ->where('status', 'active')
            ->where('is_default', true)
            ->when(
                $logisticId !== null,
                fn (Builder $query) => $query->where('logistic_id', $logisticId)
            )
            ->orderBy('id');

        if ($logisticId !== null) {
            return $query->first();
        }

        // Without a known logistics provider, only use a global default when
        // there is exactly one active default hub. Multiple defaults belong to
        // different companies and choosing one silently would be arbitrary.
        $defaults = $query->limit(2)->get();

        return $defaults->count() === 1 ? $defaults->first() : null;
    }

    public static function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public static function autoAssignRiderToOrder(Order $order): ?User
    {
        $order->loadMissing('sellerOrders');

        $firstAssignedRider = null;

        foreach ($order->sellerOrders as $sellerOrder) {
            $rider = self::autoAssignRiderToSellerOrder($sellerOrder);

            if (!$firstAssignedRider && $rider) {
                $firstAssignedRider = $rider;
            }
        }

        return $firstAssignedRider;
    }

    /**
     * Create a routed shipment for one seller parcel.
     *
     * Rider assignment is intentionally separate:
     * - pickup rider belongs to origin_hub_id
     * - final rider belongs to destination_hub_id
     */
    public static function autoAssignRiderToSellerOrder(SellerOrder $sellerOrder): ?User
    {
        $sellerOrder->loadMissing([
            'order.user',
            'seller.owner',
            'seller.pickupAddress',
            'shipment',
        ]);

        $order = $sellerOrder->order;

        if (!$order || $sellerOrder->shipment) {
            return null;
        }

        $route = self::findRoutingHubsForSellerOrder($sellerOrder);
        $originHub = $route['origin'];
        $destinationHub = $route['destination'];
        $originMatch = $route['origin_match'] ?? 'unresolved';
        $destinationMatch = $route['destination_match'] ?? 'unresolved';

        if (!$originHub || !$destinationHub) {
            return null;
        }

        $seller = $sellerOrder->seller;
        $owner = $seller?->owner;
        $pickupAddress = $seller?->pickupAddress;

        $pickupAddressText = $pickupAddress
            ? implode(', ', array_filter([
                $pickupAddress->address_line1 ?? null,
                $pickupAddress->address_line2 ?? null,
                $pickupAddress->barangay ?? null,
                $pickupAddress->city ?? null,
                $pickupAddress->province ?? null,
                $pickupAddress->postal_code ?? null,
                $pickupAddress->country ?? null,
            ]))
            : implode(', ', array_filter([
                $owner?->business_name,
                $owner?->street_address,
                $owner?->barangay_name ?: $owner?->barangay,
                $owner?->municipality_name ?: $owner?->municipality,
                $owner?->province_name ?: $owner?->province,
            ]));

        \Illuminate\Support\Facades\DB::transaction(
            function () use (
                $sellerOrder,
                $order,
                $originHub,
                $destinationHub,
                $pickupAddressText,
                $originMatch,
                $destinationMatch
            ) {
                $lockedSellerOrder = SellerOrder::query()
                    ->whereKey($sellerOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (Shipment::query()
                    ->where('seller_order_id', $lockedSellerOrder->id)
                    ->exists()) {
                    return;
                }

                Shipment::create([
                    'seller_order_id' => $lockedSellerOrder->id,
                    'logistic_id' => $originHub->logistic_id,

                    // Legacy hub_id remains the current processing hub.
                    'hub_id' => $originHub->id,
                    'origin_hub_id' => $originHub->id,
                    'destination_hub_id' => $destinationHub->id,
                    'routing_status' => 'awaiting_pickup',

                    'tracking_number' => 'SPE-' . strtoupper(uniqid()),
                    'status' => 'pending',
                    'pickup_address' => $pickupAddressText ?: 'Seller address',
                    'delivery_address' => $order->shipping_address,
                    'notes' => 'Route: '
                        . $originHub->name
                        . ' -> '
                        . $destinationHub->name
                        . ' | Origin match: ' . $originMatch
                        . ' | Destination match: ' . $destinationMatch,
                ]);

                if (!$lockedSellerOrder->logistic_id) {
                    $lockedSellerOrder->update([
                        'logistic_id' => $originHub->logistic_id,
                    ]);
                }
            }
        );

        return null;
    }

    public static function findBestRiderForHub(Hub $hub): ?User
    {
        return User::whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('hub_id', $hub->id)
            ->where('availability_status', 'available')
            ->whereColumn('current_load', '<', 'max_capacity')
            ->orderBy('current_load')
            ->orderBy('id')
            ->first();
    }

    private static function findNearestHub(
        ?float $lat,
        ?float $lng,
        ?int $logisticId = null
    ): ?Hub {
        if ($lat === null || $lng === null) {
            return null;
        }

        $hubs = Hub::query()
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when(
                $logisticId !== null,
                fn (Builder $query) => $query->where('logistic_id', $logisticId)
            )
            ->get();

        $bestHub = null;
        $bestDistance = null;

        foreach ($hubs as $hub) {
            $distance = self::calculateDistance(
                $lat,
                $lng,
                (float) $hub->latitude,
                (float) $hub->longitude
            );

            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestHub = $hub;
            }
        }

        return $bestHub;
    }

    private static function sellerLocality(SellerOrder $sellerOrder): array
    {
        $seller = $sellerOrder->seller;
        $pickup = $seller?->pickupAddress;
        $owner = $seller?->owner;

        return [
            'barangay' => $pickup?->barangay
                ?: $owner?->barangay_name
                ?: $owner?->barangay,
            'city' => $pickup?->city
                ?: $owner?->municipality_name
                ?: $owner?->municipality,
            'province' => $pickup?->province
                ?: $owner?->province_name
                ?: $owner?->province,
            'latitude' => self::floatOrNull(
                $pickup?->latitude ?? $owner?->latitude
            ),
            'longitude' => self::floatOrNull(
                $pickup?->longitude ?? $owner?->longitude
            ),
        ];
    }

    private static function destinationLocality(SellerOrder $sellerOrder): array
    {
        $order = $sellerOrder->order;
        $shipping = is_array($order?->shipping_address)
            ? $order->shipping_address
            : [];

        $buyer = $order?->user;

        return [
            'barangay' => $shipping['barangay'] ?? null
                ?: $buyer?->barangay_name
                ?: $buyer?->barangay,
            'city' => $shipping['city'] ?? null
                ?: $shipping['municipality'] ?? null
                ?: $buyer?->municipality_name
                ?: $buyer?->municipality,
            'province' => $shipping['province'] ?? null
                ?: $buyer?->province_name
                ?: $buyer?->province,
            'latitude' => self::floatOrNull(
                $shipping['latitude'] ?? $order?->customer_latitude
            ),
            'longitude' => self::floatOrNull(
                $shipping['longitude'] ?? $order?->customer_longitude
            ),
        ];
    }

    private static function applyOptionalLocality(
        Builder $query,
        string $column,
        ?string $value
    ): void {
        if ($value !== null) {
            $query->whereRaw(
                'LOWER(TRIM(' . $column . ')) = ?',
                [$value]
            );
        }
    }

    private static function normalize(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return mb_strtolower(trim($value));
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value)
            ? (float) $value
            : null;
    }
}
