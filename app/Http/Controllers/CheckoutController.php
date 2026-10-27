<?php

namespace App\Http\Controllers;

use App\Models\CartItem;

use App\Models\Order;

use App\Models\OrderItem;

use App\Models\SellerOrder;

use App\Models\Notification;

use App\Services\ShipmentCreationService;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Str;

class CheckoutController extends Controller

{

    private function createNotification($userId, $title, $message, $type = 'order', $link = null)

    {

        Notification::create([

            'user_id' => $userId,

            'title' => $title,

            'message' => $message,

            'type' => $type,

            'link' => $link,

        ]);
    }

    private function geocodeAddress($address)

    {

        if (empty($address)) {

            return null;
        }

        /*

     * 1. Try Google Maps first if configured.

     */

        $apiKey = config('services.googlemaps.key');

        if (!empty($apiKey)) {

            try {

                $response = Http::timeout(10)->get(

                    'https://maps.googleapis.com/maps/api/geocode/json',

                    [

                        'address' => $address,

                        'key' => $apiKey,

                    ]

                );

                if ($response->successful()) {

                    $data = $response->json();

                    if (

                        ($data['status'] ?? null) === 'OK' &&

                        !empty($data['results'][0]['geometry']['location'])

                    ) {

                        $location = $data['results'][0]['geometry']['location'];

                        return [

                            'lat' => (float) $location['lat'],

                            'lng' => (float) $location['lng'],

                        ];
                    }
                }
            } catch (\Throwable $e) {

                report($e);
            }
        }

        /*

     * 2. OpenStreetMap fallback.

     *

     * Try the complete address first.

     * If that fails, progressively remove the most specific

     * address components until a usable location is found.

     */

        $parts = array_values(

            array_filter(

                array_map('trim', explode(',', $address))

            )

        );

        $queries = [];

        // Complete buyer-entered address.

        $queries[] = $address;

        // Progressively remove the first / most specific components.

        for ($i = 1; $i < count($parts); $i++) {

            $remaining = array_slice($parts, $i);

            if (count($remaining) >= 2) {

                $queries[] = implode(', ', $remaining);
            }
        }

        // Add Philippines for better matching.

        $queries = array_map(function ($query) {

            if (stripos($query, 'Philippines') === false) {

                return $query . ', Philippines';
            }

            return $query;
        }, $queries);

        $queries = array_values(array_unique($queries));

        foreach ($queries as $query) {

            try {

                $response = Http::timeout(10)

                    ->withHeaders([

                        'User-Agent' => config('app.name', 'BiliHub') . '/1.0',

                        'Accept' => 'application/json',

                    ])

                    ->get('https://nominatim.openstreetmap.org/search', [

                        'q' => $query,

                        'format' => 'jsonv2',

                        'limit' => 1,

                        'countrycodes' => 'ph',

                    ]);

                if (!$response->successful()) {

                    continue;
                }

                $results = $response->json();

                if (

                    !empty($results[0]['lat']) &&

                    !empty($results[0]['lon'])

                ) {

                    return [

                        'lat' => (float) $results[0]['lat'],

                        'lng' => (float) $results[0]['lon'],

                    ];
                }

                // Be polite to the public Nominatim service.

                usleep(1000000);
            } catch (\Throwable $e) {

                report($e);
            }
        }

        return null;
    }

    private function determineDeliveryZone($lat, $lng)

    {

        if (!$lat || !$lng) {

            return 'Zone A';
        }

        $distance = $this->calculateDistance($lat, $lng, 14.5995, 120.9842);

        if ($distance <= 5) {

            return 'Zone A';
        } elseif ($distance <= 10) {

            return 'Zone B';
        } elseif ($distance <= 20) {

            return 'Zone C';
        } else {

            return 'Zone D';
        }
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)

    {

        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);

        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +

            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *

            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**

     * Use the same checkout selection for the summary and order placement.

     * Buy Now is session-only; normal cart items remain untouched.

     */

    public function checkoutCartItems(Request $request)

    {

        $selection = $request->session()->get('checkout.buy_now');

        if ($selection !== null) {

            if (!is_array($selection)) {

                throw \Illuminate\Validation\ValidationException::withMessages([

                    'cart' => 'Invalid Buy Now selection. Please choose the product again.',

                ]);
            }

            $productId = filter_var($selection['product_id'] ?? null, FILTER_VALIDATE_INT);

            $quantity = filter_var($selection['quantity'] ?? null, FILTER_VALIDATE_INT);

            $sizeId = $selection['size_id'] ?? null;

            $variantId = $selection['variant_id'] ?? null;

            if (!$productId || !$quantity || $quantity < 1) {

                throw \Illuminate\Validation\ValidationException::withMessages([

                    'cart' => 'Invalid Buy Now selection. Please choose the product again.',

                ]);
            }

            $product = \App\Models\Product::with(['sizes', 'seller'])

                ->find($productId);

            if (!$product || !$product->isPublished() || (int) $product->user_id === (int) Auth::id()) {

                throw \Illuminate\Validation\ValidationException::withMessages([

                    'cart' => 'This product is no longer available for checkout.',

                ]);
            }

            $size = null;

            if ($sizeId !== null) {

                $size = $product->sizes->firstWhere('id', $sizeId);

                if (!$size) {

                    throw \Illuminate\Validation\ValidationException::withMessages([

                        'cart' => 'The selected size is no longer available.',

                    ]);
                }
            }

            $variant = null;

            if ($variantId !== null) {

                $variant = $product->variations()->whereKey($variantId)->first();

                if (!$variant) {

                    throw \Illuminate\Validation\ValidationException::withMessages([

                        'cart' => 'The selected product variation is no longer available.',

                    ]);
                }
            }

            $item = new CartItem([

                'user_id' => Auth::id(),

                'product_id' => $product->id,

                'size_id' => $size?->id,

                'variant_id' => $variant?->id,

                'quantity' => $quantity,

                'is_buy_now' => true,

            ]);

            $item->setRelation('product', $product);

            $item->setRelation('size', $size);

            $item->setRelation('variation', $variant);

            return collect([$item]);
        }

        return CartItem::where('user_id', Auth::id())
            ->with(['product.sizes', 'product.seller', 'size', 'variation'])
            ->get();
    }

    public function index(
        \App\Services\VoucherCalculationService $voucherService
    ) {
        $request = request();

        $cartItems = $this->checkoutCartItems($request);

        if ($cartItems->isEmpty()) {
            return redirect()
                ->route('cart.index')
                ->with('error', 'Your cart is empty.');
        }

        $subtotalMinor = $cartItems->sum(function ($item) {
            $priceMinor = $item->variation
                ? (int) $item->variation->effective_price_minor
                : (int) $item->product->effective_price_minor;

            return $priceMinor * (int) $item->quantity;
        });

        // Existing BiliHub checkout fees.
        $taxMinor = intdiv(($subtotalMinor * 3) + 50, 100);

        // Shipping is PHP 50.00 per seller/shipment.
        $sellerCount = max(1, $cartItems->pluck('product.seller_id')->filter()->unique()->count());
        $shippingPerSellerMinor = (int) config('bilihub.shipping_fee_per_seller_minor', 5000);
        $shippingMinor = $shippingPerSellerMinor * $sellerCount;

        // Voucher preview.
        $voucherCode = $request->session()->get('checkout.voucher_code');
        $voucherDiscountMinor = 0;
        $voucherError = null;

        if ($voucherCode) {
            try {
                $voucherResult = $voucherService->calculate(
                    $voucherCode,
                    $cartItems,
                    (int) \Illuminate\Support\Facades\Auth::id()
                );

                $voucherDiscountMinor = max(
                    0,
                    min(
                        $subtotalMinor,
                        (int) ($voucherResult['discount_minor'] ?? 0)
                    )
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                $voucherError = collect($e->errors())
                    ->flatten()
                    ->first() ?? 'This voucher is no longer valid.';

                $request->session()->forget('checkout.voucher_code');
                $voucherCode = null;
            }
        }

        $totalMinor = max(
            0,
            $subtotalMinor + $taxMinor + $shippingMinor - $voucherDiscountMinor
        );

        $subtotal = $subtotalMinor / 100;
        $tax = $taxMinor / 100;
        $shipping = $shippingMinor / 100;
        $voucherDiscount = $voucherDiscountMinor / 100;
        $total = $totalMinor / 100;

        $paymentMethods = ['cod' => 'Cash on Delivery'];

        $user = \Illuminate\Support\Facades\Auth::user();
        $defaultAddress = $this->buildUserAddress($user);

        return view('checkout.index', compact(
            'cartItems',
            'subtotal',
            'tax',
            'shipping',
            'total',
            'paymentMethods',
            'user',
            'defaultAddress',
            'voucherCode',
            'voucherDiscount',
            'voucherError'
        ));
    }

    private function buildUserAddress($user): string

    {

        $parts = array_filter([

            $user->house_number,

            $user->street_address,

            $user->barangay_name ?: $user->barangay,

            $user->municipality_name ?: $user->municipality,

            $user->province_name ?: $user->province,

            $user->region_name ?: $user->region,

        ]);

        return implode(', ', $parts);
    }

    public function store(
        Request $request,
        \App\Services\VoucherCalculationService $voucherService
    ) {

        $request->validate([

            'shipping_address' => 'required|string|max:1000',

            'payment_method' => 'required|in:cod',

            'contact_name' => 'nullable|string|max:255',

            'contact_phone' => 'nullable|string|max:20',

            'notes' => 'nullable|string|max:1000',

        ]);

        $isBuyNow = $request->session()->has('checkout.buy_now');

        $cartItems = $this->checkoutCartItems($request);

        if ($cartItems->isEmpty()) {

            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Every product must already belong to the new sellers table.

        $missingSeller = $cartItems->first(function ($item) {

            return !$item->product || !$item->product->seller_id;
        });

        if ($missingSeller) {

            return redirect()->route('cart.index')

                ->with('error', 'One or more products are not connected to a seller shop yet.');
        }

        $subtotalMinor = $cartItems->sum(function ($item) {

            $priceMinor = $item->variation

                ? (int) $item->variation->effective_price_minor

                : (int) $item->product->effective_price_minor;

            return $priceMinor * $item->quantity;
        });

        // Standardized BiliHub checkout fees.
        // Buyer service fee: 3%.
        $taxMinor = intdiv(($subtotalMinor * 3) + 50, 100);

        // Shipping is PHP 50.00 per seller/shipment.
        $sellerCount = max(1, $cartItems->pluck('product.seller_id')->filter()->unique()->count());
        $shippingPerSellerMinor = (int) config('bilihub.shipping_fee_per_seller_minor', 5000);
        $shippingMinor = $shippingPerSellerMinor * $sellerCount;

        // Get the applied voucher from the checkout session.
        // Final voucher validation happens inside the DB transaction.
        $voucherCode = $request->session()->get('checkout.voucher_code');

        // Final buyer payment (discount recalculated inside transaction).
        $totalMinor = $subtotalMinor + $taxMinor + $shippingMinor;

        // Temporary legacy peso values for dual-write compatibility.

        $subtotal = $subtotalMinor / 100;

        $tax = $taxMinor / 100;

        $shipping = $shippingMinor / 100;

        $total = $totalMinor / 100;

        $shippingAddress = [

            'recipient' => $request->contact_name ?: Auth::user()->name,

            'phone' => $request->contact_phone ?: Auth::user()->phone,

            'address' => $request->shipping_address,

        ];

        $geocoded = $this->geocodeAddress($request->shipping_address);

        $customerLat = $geocoded['lat'] ?? null;

        $customerLng = $geocoded['lng'] ?? null;

        $deliveryZone = $this->determineDeliveryZone($customerLat, $customerLng);

        $order = DB::transaction(function () use (

            $request,

            $cartItems,

            $isBuyNow,

            $subtotal,

            $tax,

            $shipping,

            $total,

            $subtotalMinor,

            $taxMinor,

            $shippingMinor,

            $shippingPerSellerMinor,

            $totalMinor,

            $shippingAddress,

            $customerLat,

            $customerLng,

            $deliveryZone,

            $voucherCode,

            $voucherService

        ) {

            // Acquire product locks in a consistent order to reduce deadlocks.

            // Keep these locks until the checkout transaction commits.

            $productIds = $cartItems

                ->pluck('product_id')

                ->unique()

                ->sort()

                ->values()

                ->all();

            foreach ($productIds as $productId) {

                $lockedProduct = \App\Models\Product::query()
                    ->whereKey($productId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Check product availability and ownership.
                if (
                    !$lockedProduct->isPublished() ||
                    (int) $lockedProduct->user_id === (int) Auth::id()
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'cart' => "The product '{$lockedProduct->name}' is no longer available for checkout.",
                    ]);
                }

                // Validate every cart item belonging to this product.
                $productItems = $cartItems->where('product_id', $productId);

                foreach ($productItems as $item) {

                    if ((int) $item->quantity < 1) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'cart' => "Invalid quantity for {$lockedProduct->name}.",
                        ]);
                    }

                    // Require a variant if the product has variants.
                    if (
                        $lockedProduct->variations()->exists() &&
                        !$item->variant_id
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'cart' => "Please select a variant for {$lockedProduct->name}.",
                        ]);
                    }

                    // Ensure selected variant belongs to this product.
                    if ($item->variant_id) {
                        $validVariant = $lockedProduct->variations()
                            ->whereKey($item->variant_id)
                            ->exists();

                        if (!$validVariant) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'cart' => "The selected variant for {$lockedProduct->name} is no longer available.",
                            ]);
                        }
                    }

                    // Require a size if the product has sizes.
                    if (
                        $lockedProduct->sizes()->exists() &&
                        !$item->size_id
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'cart' => "Please select a size for {$lockedProduct->name}.",
                        ]);
                    }

                    // Ensure selected size belongs to this product.
                    if ($item->size_id) {
                        $validSize = $lockedProduct->sizes()
                            ->whereKey($item->size_id)
                            ->exists();

                        if (!$validSize) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'cart' => "The selected size for {$lockedProduct->name} is no longer available.",
                            ]);
                        }
                    }
                }
            }

            // Recalculate voucher under a row lock to prevent concurrent over-redemption.
            $voucherResult = null;
            $lockedVoucher = null;
            $voucherDiscountMinor = 0;
            $sellerDiscountMinor = 0;
            $platformDiscountMinor = 0;
            $itemVoucherDiscounts = [];

            if ($voucherCode) {
                $lockedVoucher = \App\Models\Voucher::query()
                    ->where('code', strtoupper(trim($voucherCode)))
                    ->lockForUpdate()
                    ->first();

                if (!$lockedVoucher) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'voucher_code' => 'The selected voucher is no longer available.',
                    ]);
                }

                $voucherResult = $voucherService->calculate(
                    $voucherCode,
                    $cartItems,
                    (int) Auth::id()
                );

                if ((int) $voucherResult['voucher_id'] !== (int) $lockedVoucher->id) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'voucher_code' => 'Voucher validation failed. Please apply it again.',
                    ]);
                }

                $voucherDiscountMinor = (int) $voucherResult['discount_minor'];
                $sellerDiscountMinor = (int) $voucherResult['seller_discount_minor'];
                $platformDiscountMinor = (int) $voucherResult['platform_discount_minor'];

                foreach ($voucherResult['items'] as $discountRow) {
                    $cartItem = $cartItems->get((int) $discountRow['cart_index']);

                    if (
                        !$cartItem ||
                        (int) $cartItem->product_id !== (int) $discountRow['product_id'] ||
                        (int) $cartItem->product->seller_id !== (int) $discountRow['seller_id']
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'voucher_code' => 'Voucher item allocation failed. Please retry checkout.',
                        ]);
                    }

                    $itemVoucherDiscounts[spl_object_id($cartItem)] =
                        (int) $discountRow['discount_minor'];
                }
            }

            $totalMinor = $subtotalMinor + $taxMinor + $shippingMinor - $voucherDiscountMinor;

            $orderReference = 'ORD-' . strtoupper(Str::random(10));

            $order = Order::forceCreate([

                'user_id' => Auth::id(),

                'order_number' => $orderReference,

                'reference' => $orderReference,

                'status' => 'placed',

                'subtotal_minor' => $subtotalMinor,

                'tax_minor' => $taxMinor,

                'shipping_minor' => $shippingMinor,

                'total_minor' => $totalMinor,

                'discount_minor' => $voucherDiscountMinor,

                'seller_discount_minor' => $sellerDiscountMinor,

                'platform_discount_minor' => $platformDiscountMinor,

                'shipping_address' => json_encode($shippingAddress),

                'notes' => $request->notes,

                'ordered_at' => now(),

                'payment_method' => $request->payment_method,

                'payment_status' => 'unpaid',

                'customer_latitude' => $customerLat,

                'customer_longitude' => $customerLng,

                'delivery_zone' => $deliveryZone,

            ]);

            // Split this buyer checkout into one SellerOrder per shop.

            $itemsBySeller = $cartItems->groupBy(function ($item) {

                return $item->product->seller_id;
            });

            $sellerCount = max(1, $itemsBySeller->count());

            $sellerIndex = 0;

            $allocatedTaxMinor = 0;

            $firstSellerOrderId = null;

            foreach ($itemsBySeller as $sellerId => $sellerItems) {

                $sellerIndex++;

                $sellerSubtotalMinor = $sellerItems->sum(function ($item) {

                    $unitPriceMinor = $item->variation

                        ? (int) $item->variation->effective_price_minor

                        : (int) $item->product->effective_price_minor;

                    return $unitPriceMinor * $item->quantity;
                });

                // Each SellerOrder represents one seller/shipment and receives the full
                // per-seller shipping fee. Tax/service fee still reconciles proportionally.
                $sellerShippingMinor = $shippingPerSellerMinor;

                if ($sellerIndex === $sellerCount) {
                    $sellerTaxMinor = $taxMinor - $allocatedTaxMinor;
                } else {
                    $sellerTaxMinor = $subtotalMinor > 0
                        ? intdiv($taxMinor * $sellerSubtotalMinor, $subtotalMinor)
                        : 0;

                    $allocatedTaxMinor += $sellerTaxMinor;
                }

                $sellerVoucherDiscountMinor = $sellerItems->sum(function ($item) use ($itemVoucherDiscounts) {
                    return $itemVoucherDiscounts[spl_object_id($item)] ?? 0;
                });

                $sellerFundedDiscountMinor = $voucherResult && $voucherResult['funded_by'] === 'seller'
                    ? $sellerVoucherDiscountMinor : 0;
                $platformFundedDiscountMinor = $voucherResult && $voucherResult['funded_by'] === 'platform'
                    ? $sellerVoucherDiscountMinor : 0;

                $sellerTotalMinor = $sellerSubtotalMinor + $sellerTaxMinor
                    + $sellerShippingMinor - $sellerVoucherDiscountMinor;

                // Temporary legacy peso values for dual-write compatibility.

                $sellerSubtotal = $sellerSubtotalMinor / 100;

                $sellerShipping = $sellerShippingMinor / 100;

                $sellerTax = $sellerTaxMinor / 100;

                $sellerTotal = $sellerTotalMinor / 100;

                $sellerOrder = SellerOrder::create([

                    'order_id' => $order->id,

                    'seller_id' => $sellerId,

                    'status' => 'pending',

                    'subtotal_minor' => $sellerSubtotalMinor,

                    'shipping_minor' => $sellerShippingMinor,

                    'total_minor' => $sellerTotalMinor,

                    'discount_minor' => $sellerVoucherDiscountMinor,

                    'seller_discount_minor' => $sellerFundedDiscountMinor,

                    'platform_discount_minor' => $platformFundedDiscountMinor,

                ]);

                $firstSellerOrderId ??= $sellerOrder->id;

                foreach ($sellerItems as $item) {

                    $unitPriceMinor = $item->variation

                        ? (int) $item->variation->effective_price_minor

                        : (int) $item->product->effective_price_minor;

                    $itemSubtotalMinor = $unitPriceMinor * $item->quantity;

                    $itemDiscountMinor = $itemVoucherDiscounts[spl_object_id($item)] ?? 0;
                    $itemSellerDiscountMinor = $voucherResult && $voucherResult['funded_by'] === 'seller'
                        ? $itemDiscountMinor : 0;
                    $itemPlatformDiscountMinor = $voucherResult && $voucherResult['funded_by'] === 'platform'
                        ? $itemDiscountMinor : 0;

                    // Temporary legacy peso values for dual-write compatibility.

                    $unitPrice = $unitPriceMinor / 100;

                    $itemSubtotal = $itemSubtotalMinor / 100;

                    $productName = $item->product->name;

                    if ($item->variation) {

                        $productName .= ' (' . ($item->variation->display_name ?: $item->variation->name) . ')';
                    }

                    OrderItem::create([

                        'seller_order_id' => $sellerOrder->id,

                        'product_id' => $item->product_id,

                        'variant_id' => $item->variant_id,

                        'size_id' => $item->size_id,

                        'product_name' => $productName,

                        'price_minor' => $unitPriceMinor,

                        'quantity' => $item->quantity,

                        'subtotal_minor' => $itemSubtotalMinor,

                        'discount_minor' => $itemDiscountMinor,

                        'seller_discount_minor' => $itemSellerDiscountMinor,

                        'platform_discount_minor' => $itemPlatformDiscountMinor,

                    ]);

                    // Lock the product row to prevent concurrent stock deductions.

                    $lockedProduct = \App\Models\Product::query()

                        ->lockForUpdate()

                        ->findOrFail($item->product_id);

                    $quantity = (int) $item->quantity;

                    if ($quantity < 1) {

                        throw \Illuminate\Validation\ValidationException::withMessages([

                            'cart' => 'Invalid product quantity.',

                        ]);
                    }

                    // Validate and deduct the product's overall stock.

                    if ((int) $lockedProduct->stock < $quantity) {

                        throw \Illuminate\Validation\ValidationException::withMessages([

                            'cart' => "Insufficient stock for {$lockedProduct->name}.",

                        ]);
                    }

                    if ($item->size_id) {

                        $size = $lockedProduct->sizes()

                            ->lockForUpdate()

                            ->findOrFail($item->size_id);

                        if ((int) $size->pivot->stock < $quantity) {

                            throw \Illuminate\Validation\ValidationException::withMessages([

                                'cart' => "Insufficient stock for {$lockedProduct->name} ({$size->name}).",

                            ]);
                        }

                        $lockedProduct->sizes()->updateExistingPivot($item->size_id, [

                            'stock' => (int) $size->pivot->stock - $quantity,

                        ]);
                    }

                    // Validate stock using the saved variant ID, not a loaded relationship.
                    if ($item->variant_id) {

                        $variation = $lockedProduct->variations()

                            ->lockForUpdate()

                            ->findOrFail($item->variant_id);

                        if ((int) $variation->stock < $quantity) {

                            throw \Illuminate\Validation\ValidationException::withMessages([

                                'cart' => "Insufficient stock for {$lockedProduct->name} ({$variation->name}).",

                            ]);
                        }

                        $variation->decrement('stock', $quantity);
                    }

                    $lockedProduct->decrement('stock', $quantity);
                } // End foreach seller items

            } // End foreach sellers

            // Exactly one redemption for the parent order. Use an existing seller order
            // as the FK anchor even if seller_order_id is NOT NULL in this schema.
            if ($voucherResult && $lockedVoucher) {
                \App\Models\VoucherRedemption::create([
                    'voucher_id' => $lockedVoucher->id,
                    'user_id' => Auth::id(),
                    'order_id' => $order->id,
                    'seller_order_id' => $firstSellerOrderId,
                    'voucher_code_snapshot' => $voucherResult['code'],
                    'funded_by' => $voucherResult['funded_by'],
                    'discount_minor' => $voucherDiscountMinor,
                    'status' => 'redeemed',
                    'redeemed_at' => now(),
                ]);

                $lockedVoucher->increment('usage_count');
            }

            // Create one shipment per seller order within the checkout transaction.

            app(ShipmentCreationService::class)->createForOrder($order);

            if (!$isBuyNow) {

                CartItem::where('user_id', Auth::id())
                    ->whereIn('id', $cartItems->pluck('id'))
                    ->delete();
            }

            return $order;
        }, 3);

        if ($isBuyNow) {

            $request->session()->forget('checkout.buy_now');
        }

        $request->session()->forget('checkout.voucher_code');

        $this->createNotification(

            Auth::id(),

            'Order Placed Successfully',

            'Your order ' . $order->order_number . ' has been placed.',

            'order',

            route('orders.show', $order)

        );

        /*
         * Notify every seller included in this checkout.
         *
         * One buyer order can contain products from multiple sellers, so
         * each SellerOrder is resolved to the seller shop owner and that
         * seller receives one notification for this order.
         */
        $order->loadMissing('sellerOrders.seller.owner');

        foreach ($order->sellerOrders as $sellerOrder) {

            $sellerUser = $sellerOrder->seller?->owner;

            if (!$sellerUser) {
                continue;
            }

            $this->createNotification(

                $sellerUser->id,

                'New Order Received',

                'You received a new order ' . $order->order_number . '.',

                'order',

                route('seller.orders.show', $order)

            );
        }

        return redirect()

            ->route('orders.show', $order)

            ->with('success', 'Order placed successfully!');
    }
}
