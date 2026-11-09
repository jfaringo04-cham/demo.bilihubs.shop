<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShippingFeeService
{
    public function baseFeeMinor(): int
    {
        return max(
            0,
            (int) config(
                'shipping.base_fee_minor',
                5000
            )
        );
    }

    public function includedDistanceKm(): float
    {
        return max(
            0,
            (float) config(
                'shipping.included_distance_km',
                3
            )
        );
    }

    public function additionalBlockKm(): float
    {
        return max(0.001, (float) config('shipping.additional_block_km', 3));
    }

    public function additionalPerBlockMinor(): int
    {
        return max(0, (int) config('shipping.additional_per_block_minor', 400));
    }

    public function calculateFeeMinor(?float $distanceKm): int
    {
        $base = $this->baseFeeMinor();

        if ($distanceKm === null) {
            return $base;
        }

        $extraDistance = max(0, $distanceKm - $this->includedDistanceKm());

        // Charge one PHP 4 block for every started 3 km beyond the first 3 km.
        // 3.0 km = PHP 50; 3.1–6 km = PHP 54; over 6–9 km = PHP 58.
        $extraBlocks = (int) ceil(max(0, $extraDistance - 0.000001) / $this->additionalBlockKm());

        return $base + ($extraBlocks * $this->additionalPerBlockMinor());
    }

    public function quoteForCart(
        Collection $cartItems,
        ?Address $buyerAddress,
        string $buyerAddressText
    ): array {
        $buyerPoint = $this->pointForAddress(
            $buyerAddress,
            $buyerAddressText
        );

        $groups = $cartItems->groupBy(
            fn ($item) =>
                (int) ($item->product?->seller_id ?: 0)
        );

        $rows = [];
        $perSellerMinor = [];
        $totalFeeMinor = 0;

        foreach ($groups as $sellerId => $items) {
            if ((int) $sellerId <= 0) {
                continue;
            }

            $firstItem = $items->first();
            $seller = $firstItem?->product?->seller;

            if ($seller) {
                $seller->loadMissing('pickupAddress');
            }

            $pickupAddress = $seller?->pickupAddress;

            $sellerAddressText = $pickupAddress
                ? $this->formatAddress($pickupAddress)
                : '';

            $sellerPoint = $this->pointForAddress(
                $pickupAddress,
                $sellerAddressText
            );

            $distanceKm = null;

            if ($buyerPoint && $sellerPoint) {
                $distanceKm = $this->distanceKm(
                    $sellerPoint['lat'],
                    $sellerPoint['lng'],
                    $buyerPoint['lat'],
                    $buyerPoint['lng']
                );
            }

            $feeMinor = $this->calculateFeeMinor(
                $distanceKm
            );

            $perSellerMinor[(int) $sellerId] =
                $feeMinor;

            $totalFeeMinor += $feeMinor;

            $rows[] = [
                'seller_id' => (int) $sellerId,

                'seller_name' =>
                    $seller?->name
                    ?: 'Seller #' . $sellerId,

                'distance_km' =>
                    $distanceKm !== null
                        ? round($distanceKm, 2)
                        : null,

                'fee_minor' => $feeMinor,

                'used_base_fallback' =>
                    $distanceKm === null,
            ];
        }

        /*
         * Defensive fallback for malformed cart data.
         */
        if ($rows === []) {
            $fee = $this->baseFeeMinor();

            $rows[] = [
                'seller_id' => 0,
                'seller_name' => 'Shipping',
                'distance_km' => null,
                'fee_minor' => $fee,
                'used_base_fallback' => true,
            ];

            $totalFeeMinor = $fee;
        }

        return [
            'base_fee_minor' =>
                $this->baseFeeMinor(),

            'included_distance_km' =>
                $this->includedDistanceKm(),

            'additional_block_km' => $this->additionalBlockKm(),

            'additional_per_block_minor' => $this->additionalPerBlockMinor(),

            'total_fee_minor' =>
                $totalFeeMinor,

            'per_seller_minor' =>
                $perSellerMinor,

            'rows' =>
                $rows,
        ];
    }

    private function pointForAddress(
        ?object $address,
        string $addressText
    ): ?array {
        if (
            $address
            && $address->latitude !== null
            && $address->longitude !== null
        ) {
            return [
                'lat' => (float) $address->latitude,
                'lng' => (float) $address->longitude,
            ];
        }

        $text = trim($addressText);

        if ($text === '') {
            return null;
        }

        return $this->geocode($text);
    }

    private function formatAddress(object $address): string
    {
        return collect([
            $address->address_line1,
            $address->address_line2,
            $address->barangay,
            $address->city,
            $address->province,
            $address->postal_code,
            $address->country,
        ])
            ->filter(
                fn ($value) =>
                    $value !== null
                    && trim((string) $value) !== ''
            )
            ->map(
                fn ($value) =>
                    trim((string) $value)
            )
            ->implode(', ');
    }

    private function geocode(string $address): ?array
    {
        $cacheKey =
            'shipping-geocode:'
            . sha1(strtolower(trim($address)));

        return Cache::remember(
            $cacheKey,
            now()->addDays(30),
            function () use ($address) {
                $googleKey =
                    config('services.googlemaps.key');

                if ($googleKey) {
                    try {
                        $response = Http::timeout(10)
                            ->get(
                                'https://maps.googleapis.com/maps/api/geocode/json',
                                [
                                    'address' => $address,
                                    'key' => $googleKey,
                                ]
                            );

                        if ($response->successful()) {
                            $data = $response->json();

                            $location =
                                $data['results'][0]
                                    ['geometry']
                                    ['location']
                                ?? null;

                            if (
                                isset(
                                    $location['lat'],
                                    $location['lng']
                                )
                            ) {
                                return [
                                    'lat' =>
                                        (float) $location['lat'],

                                    'lng' =>
                                        (float) $location['lng'],
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $parts = array_values(
                    array_filter(
                        array_map(
                            'trim',
                            explode(',', $address)
                        )
                    )
                );

                $queries = [];

                // Try complete address first.
                $queries[] = $address;

                /*
                 * If exact address cannot be resolved,
                 * progressively remove the most specific
                 * leading address components.
                 *
                 * Example:
                 * 123, caba, Real, Calamba, Laguna
                 * caba, Real, Calamba, Laguna
                 * Real, Calamba, Laguna
                 * Calamba, Laguna
                 */
                for ($i = 1; $i < count($parts); $i++) {
                    $remaining = array_slice(
                        $parts,
                        $i
                    );

                    if (count($remaining) >= 2) {
                        $queries[] = implode(
                            ', ',
                            $remaining
                        );
                    }
                }

                $queries = array_map(
                    function ($query) {
                        if (
                            stripos(
                                $query,
                                'Philippines'
                            ) === false
                        ) {
                            return $query
                                . ', Philippines';
                        }

                        return $query;
                    },
                    $queries
                );

                $queries = array_values(
                    array_unique($queries)
                );

                foreach ($queries as $query) {
                    try {
                        $response = Http::timeout(10)
                            ->withHeaders([
                                'User-Agent' =>
                                    config(
                                        'app.name',
                                        'BiliHub'
                                    )
                                    . '/1.0',

                                'Accept' =>
                                    'application/json',
                            ])
                            ->get(
                                'https://nominatim.openstreetmap.org/search',
                                [
                                    'q' => $query,
                                    'format' => 'jsonv2',
                                    'limit' => 1,
                                    'countrycodes' => 'ph',
                                ]
                            );

                        if (!$response->successful()) {
                            continue;
                        }

                        $row =
                            $response->json()[0]
                            ?? null;

                        if (
                            !$row
                            || !isset(
                                $row['lat'],
                                $row['lon']
                            )
                        ) {
                            continue;
                        }

                        return [
                            'lat' =>
                                (float) $row['lat'],

                            'lng' =>
                                (float) $row['lon'],
                        ];
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                return null;
            }
        );
    }

    private function distanceKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadiusKm = 6371.0;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a =
            sin($deltaLat / 2) ** 2
            + cos($lat1Rad)
            * cos($lat2Rad)
            * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadiusKm * $c;
    }
}
