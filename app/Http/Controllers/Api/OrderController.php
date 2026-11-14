<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\Product;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Voucher;
use App\Models\Shipment;
use App\Services\ShippingFeeService;
use App\Services\VoucherCalculationService;
use App\Services\VoucherRedemptionService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with([
            'items.product:id,name,price_minor,discounted_price_minor,discount_percent,discount_starts_at,discount_ends_at,image',
            'items.variation:id,product_id,name,price_minor,discounted_price_minor,discount_percent,image',
            'sellerOrders.seller:id,name,slug',
            'sellerOrders.shipment:id,seller_order_id,tracking_number,status,delivered_at,rider_id',
            'sellerOrders.shipment.rider:id,name,phone,vehicle_type,latitude,longitude',
            'rider:id,name,phone,vehicle_type',
        ])
        ->where('user_id', Auth::id())
        ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min($request->get('per_page', 15), 50);
        $orders = $query->paginate($perPage);

        return response()->json([
            'data' => collect($orders->items())->map(function ($order) {
                return $this->formatOrder($order);
            })->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'address_id' => 'required|exists:addresses,id',
            'payment_method' => 'required|in:cod,gcash,maya,card,bank_transfer',
            'notes' => 'nullable|string|max:1000',
            'voucher_code' => 'nullable|string|max:80',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.size_id' => 'nullable|exists:sizes,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $address = Address::where('id', $request->address_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $cartItems = [];
        $shippingCartItems = [];
        $voucherCartItems = collect();
        $subtotalMinor = 0;

        foreach ($request->items as $item) {
            $product = Product::with([
                'variations.sizes',
                'seller',
            ])->findOrFail($item['product_id']);

            if (
                $product->status !== 'published' ||
                $product->compliance_status !== 'approved'
            ) {
                return response()->json([
                    'message' => "Product {$product->name} is not available.",
                ], 422);
            }

            if (!$product->seller_id) {
                return response()->json([
                    'message' => "Product {$product->name} is not connected to a seller shop yet.",
                ], 422);
            }

            $variation = null;

            if (!empty($item['variant_id'])) {
                $variation = $product->variations()
                    ->findOrFail($item['variant_id']);
            } elseif (!empty($item['size_id'])) {
                $variation = $product->variations()
                    ->whereHas('sizes', function ($query) use ($item) {
                        $query->where('sizes.id', $item['size_id']);
                    })
                    ->first();
            }

            $quantity = (int) $item['quantity'];

            // Check variation or product stock.
            if ($variation) {
                if ($variation->stock < $quantity) {
                    return response()->json([
                        'message' => "Insufficient stock for {$product->name} ({$variation->name})",
                    ], 422);
                }

                $priceMinor = (int) $variation->effective_price_minor;
            } else {
                if ($product->stock < $quantity) {
                    return response()->json([
                        'message' => "Insufficient stock for {$product->name}",
                    ], 422);
                }

                $priceMinor = (int) $product->effective_price_minor;
            }

            // Check size stock.
            if (!empty($item['size_id'])) {
                $size = $product->sizes()
                    ->findOrFail($item['size_id']);

                if (($size->pivot->stock ?? 0) < $quantity) {
                    return response()->json([
                        'message' => "Size {$size->name} stock insufficient for {$product->name}",
                    ], 422);
                }
            }

            $itemSubtotalMinor = $priceMinor * $quantity;
            $subtotalMinor += $itemSubtotalMinor;

            $cartIndex = count($cartItems);

            $cartItems[] = [
                'cart_index' => $cartIndex,
                'product_id' => $product->id,
                'packed_weight_kg' => $product->packed_weight_kg,
                'seller_id' => $product->seller_id,
                'variant_id' => $variation?->id,
                'size_id' => $item['size_id'] ?? null,
                'quantity' => $quantity,
                'price_minor' => $priceMinor,
                'subtotal_minor' => $itemSubtotalMinor,
            ];

            // VoucherCalculationService expects product, variation, and quantity.
            $voucherCartItems->push((object) [
                'product' => $product,
                'variation' => $variation,
                'quantity' => $quantity,
            ]);

            // ShippingFeeService expects each cart item to expose its product.
            $shippingCartItems[] = (object) [
                'product' => $product,
            ];
        }

        // Group items by seller.
        $itemsBySeller = collect($cartItems)->groupBy('seller_id');
        $sellerCount = $itemsBySeller->count();

        // Build the buyer's complete address for shipping-distance calculation.
        $buyerAddressText = collect([
            $address->address_line1,
            $address->address_line2,
            $address->barangay,
            $address->city,
            $address->province,
            $address->postal_code,
            $address->country,
        ])
            ->filter(fn($value) => $value !== null && trim((string) $value) !== '')
            ->map(fn($value) => trim((string) $value))
            ->implode(', ');

        // Use the same distance-based shipping calculation as web checkout.
        $shippingQuote = app(ShippingFeeService::class)->quoteForCart(
            collect($shippingCartItems),
            $address,
            $buyerAddressText
        );

        $shippingBySellerMinor = $shippingQuote['per_seller_minor'];
        $shippingFeeMinor = (int) $shippingQuote['total_fee_minor'];

        // Platform Service Fee: 3% of product subtotal before voucher discounts.
        $serviceFeeMinor = intdiv(($subtotalMinor * 3) + 50, 100);

        DB::beginTransaction();

        try {
            $voucherCode = trim((string) $request->input('voucher_code', ''));

            $voucherResult = null;
            $lockedVoucher = null;

            $voucherDiscountMinor = 0;
            $sellerDiscountMinor = 0;
            $platformDiscountMinor = 0;

            $itemVoucherDiscounts = [];

            if ($voucherCode !== '') {
                $lockedVoucher = Voucher::query()
                    ->where('code', strtoupper($voucherCode))
                    ->lockForUpdate()
                    ->first();

                if (!$lockedVoucher) {
                    throw ValidationException::withMessages([
                        'voucher_code' => 'The selected voucher is no longer available.',
                    ]);
                }

                // Require the authenticated buyer to claim this voucher before checkout.
                $hasClaimedVoucher = DB::table('buyer_voucher_claims')
                    ->where('voucher_id', $lockedVoucher->id)
                    ->where('user_id', Auth::id())
                    ->exists();

                if (!$hasClaimedVoucher) {
                    throw ValidationException::withMessages([
                        'voucher_code' => 'Please claim this voucher before using it.',
                    ]);
                }

                $voucherResult = app(VoucherCalculationService::class)->calculate(
                    $voucherCode,
                    $voucherCartItems,
                    (int) Auth::id()
                );

                if ((int) $voucherResult['voucher_id'] !== (int) $lockedVoucher->id) {
                    throw ValidationException::withMessages([
                        'voucher_code' => 'Voucher validation failed. Please apply it again.',
                    ]);
                }

                $voucherDiscountMinor = (int) $voucherResult['discount_minor'];
                $sellerDiscountMinor = (int) $voucherResult['seller_discount_minor'];
                $platformDiscountMinor = (int) $voucherResult['platform_discount_minor'];

                foreach ($voucherResult['items'] as $discountRow) {
                    $cartIndex = (int) $discountRow['cart_index'];

                    $cartItem = $cartItems[$cartIndex] ?? null;
                    $voucherCartItem = $voucherCartItems->get($cartIndex);

                    if (
                        !$cartItem ||
                        !$voucherCartItem ||
                        (int) $cartItem['product_id'] !== (int) $discountRow['product_id'] ||
                        (int) $cartItem['seller_id'] !== (int) $discountRow['seller_id'] ||
                        (int) $voucherCartItem->product->id !== (int) $discountRow['product_id']
                    ) {
                        throw ValidationException::withMessages([
                            'voucher_code' => 'Voucher item allocation failed. Please retry checkout.',
                        ]);
                    }

                    $itemVoucherDiscounts[$cartIndex] =
                        (int) $discountRow['discount_minor'];
                }
            }

            $totalMinor = $subtotalMinor
                + $shippingFeeMinor
                + $serviceFeeMinor
                - $voucherDiscountMinor;

            $order = Order::create([
                'user_id' => Auth::id(),
                'order_number' => 'ORD-' . Str::upper(Str::random(10)),
                'reference' => 'REF-' . Str::upper(Str::random(12)),
                'status' => 'placed',
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_method === 'cod'
                    ? 'unpaid'
                    : 'pending',

                'subtotal_minor' => $subtotalMinor,
                'service_fee_minor' => $serviceFeeMinor,
                'shipping_minor' => $shippingFeeMinor,
                'total_minor' => $totalMinor,

                'discount_minor' => $voucherDiscountMinor,
                'seller_discount_minor' => $sellerDiscountMinor,
                'platform_discount_minor' => $platformDiscountMinor,

                'notes' => $request->notes,

                'shipping_address' => [
                    'label' => $address->label,
                    'address_line1' => $address->address_line1,
                    'address_line2' => $address->address_line2,
                    'barangay' => $address->barangay,
                    'city' => $address->city,
                    'province' => $address->province,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                    'phone' => $address->phone,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                ],

                'ordered_at' => now(),
                'customer_latitude' => null,
                'customer_longitude' => null,
            ]);

            // Split the buyer order into one SellerOrder per seller/shop.
            $allocatedServiceFeeMinor = 0;
            $sellerIndex = 0;
            $firstSellerOrderId = null;

            foreach ($itemsBySeller as $sellerId => $sellerItems) {
                $sellerIndex++;

                $sellerSubtotalMinor = (int) $sellerItems->sum('subtotal_minor');

                // Calculate voucher discounts belonging to this seller.
                $sellerOrderDiscountMinor = 0;
                $sellerOrderSellerDiscountMinor = 0;
                $sellerOrderPlatformDiscountMinor = 0;

                foreach ($sellerItems as $item) {
                    $itemDiscountMinor = (int) (
                        $itemVoucherDiscounts[$item['cart_index']] ?? 0
                    );

                    $sellerOrderDiscountMinor += $itemDiscountMinor;

                    if ($voucherResult && $voucherResult['funded_by'] === 'seller') {
                        $sellerOrderSellerDiscountMinor += $itemDiscountMinor;
                    } elseif ($voucherResult && $voucherResult['funded_by'] === 'platform') {
                        $sellerOrderPlatformDiscountMinor += $itemDiscountMinor;
                    }
                }

                // Use the actual shipping quote for this seller.
                $sellerShippingMinor = (int) (
                    $shippingBySellerMinor[(int) $sellerId]
                    ?? app(ShippingFeeService::class)->baseFeeMinor()
                );

                // Allocate the 3% platform service fee proportionally.
                if ($sellerIndex === $sellerCount) {
                    $sellerServiceFeeMinor = $serviceFeeMinor
                        - $allocatedServiceFeeMinor;
                } else {
                    $sellerServiceFeeMinor = $subtotalMinor > 0
                        ? intdiv(
                            $serviceFeeMinor * $sellerSubtotalMinor,
                            $subtotalMinor
                        )
                        : 0;

                    $allocatedServiceFeeMinor += $sellerServiceFeeMinor;
                }

                // Only seller-funded discounts reduce the seller's payable total.
                // Platform-funded discounts are funded by the platform.
                $sellerTotalMinor = $sellerSubtotalMinor
                    + $sellerServiceFeeMinor
                    + $sellerShippingMinor
                    - $sellerOrderSellerDiscountMinor;

                $sellerOrder = SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'status' => 'pending',

                    'subtotal_minor' => $sellerSubtotalMinor,
                    'service_fee_minor' => $sellerServiceFeeMinor,
                    'shipping_minor' => $sellerShippingMinor,
                    'total_minor' => $sellerTotalMinor,

                    'discount_minor' => $sellerOrderDiscountMinor,
                    'seller_discount_minor' => $sellerOrderSellerDiscountMinor,
                    'platform_discount_minor' => $sellerOrderPlatformDiscountMinor,
                ]);

                if ($firstSellerOrderId === null) {
                    $firstSellerOrderId = $sellerOrder->id;
                }

                foreach ($sellerItems as $item) {
                    $product = Product::find($item['product_id']);
                    $productName = $product?->name ?? 'Product';

                    if (!empty($item['variant_id'])) {
                        $variationName = $product?->variations()
                            ->whereKey($item['variant_id'])
                            ->value('name');

                        if ($variationName) {
                            $productName .= ' (' . $variationName . ')';
                        }
                    }

                    // Lock and re-check stock inside the transaction.
                    $lockedProduct = Product::query()
                        ->lockForUpdate()
                        ->findOrFail($item['product_id']);

                    if (!$lockedProduct->isPublished()) {
                        throw ValidationException::withMessages([
                            'items' => 'A product in this order is no longer available.',
                        ]);
                    }

                    if (!empty($item['variant_id'])) {
                        $lockedVariation = $lockedProduct->variations()
                            ->lockForUpdate()
                            ->findOrFail($item['variant_id']);

                        if ($lockedVariation->stock < $item['quantity']) {
                            throw new \RuntimeException(
                                "Insufficient stock for {$lockedProduct->name}."
                            );
                        }

                        $lockedVariation->decrement(
                            'stock',
                            $item['quantity']
                        );
                    } else {
                        if ($lockedProduct->stock < $item['quantity']) {
                            throw new \RuntimeException(
                                "Insufficient stock for {$lockedProduct->name}."
                            );
                        }

                        $lockedProduct->decrement(
                            'stock',
                            $item['quantity']
                        );
                    }

                    // Deduct size stock if a size was selected.
                    if (!empty($item['size_id'])) {
                        $sizeRow = DB::table('product_size')
                            ->where('product_id', $item['product_id'])
                            ->where('size_id', $item['size_id'])
                            ->lockForUpdate()
                            ->first();

                        if (
                            !$sizeRow ||
                            $sizeRow->stock < $item['quantity']
                        ) {
                            throw new \RuntimeException(
                                "Insufficient size stock for {$lockedProduct->name}."
                            );
                        }

                        DB::table('product_size')
                            ->where('id', $sizeRow->id)
                            ->update([
                                'stock' => $sizeRow->stock - $item['quantity'],
                                'updated_at' => now(),
                            ]);
                    }

                    $itemDiscountMinor = (int) (
                        $itemVoucherDiscounts[$item['cart_index']] ?? 0
                    );

                    $itemSellerDiscountMinor = (
                        $voucherResult &&
                        $voucherResult['funded_by'] === 'seller'
                    )
                        ? $itemDiscountMinor
                        : 0;

                    $itemPlatformDiscountMinor = (
                        $voucherResult &&
                        $voucherResult['funded_by'] === 'platform'
                    )
                        ? $itemDiscountMinor
                        : 0;

                    OrderItem::create([
                        'seller_order_id' => $sellerOrder->id,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'],
                        'size_id' => $item['size_id'],
                        'product_name' => $productName,
                        'packed_weight_kg' => $item['packed_weight_kg'],
                        'quantity' => $item['quantity'],
                        'price_minor' => $item['price_minor'],
                        'subtotal_minor' => $item['subtotal_minor'],

                        'discount_minor' => $itemDiscountMinor,
                        'seller_discount_minor' => $itemSellerDiscountMinor,
                        'platform_discount_minor' => $itemPlatformDiscountMinor,
                    ]);
                }
            }

            // Reserve the COD voucher until all shipment payments are collected
            // and the parent order transitions to paid.
            if ($voucherResult && $lockedVoucher) {
                app(VoucherRedemptionService::class)->recordForOrder(
                    (int) $order->id,
                    $firstSellerOrderId ? (int) $firstSellerOrderId : null,
                    (int) $lockedVoucher->id,
                    (int) Auth::id(),
                    $voucherResult,
                    true
                );
            }

            // Clear the cart items included in this order.
            CartItem::where('user_id', Auth::id())
                ->whereIn(
                    'product_id',
                    array_column($cartItems, 'product_id')
                )
                ->delete();

            // Create one shipment for each seller/shop parcel.
            app(\App\Services\ShipmentCreationService::class)
                ->createForOrder($order);

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully.',
                'data' => $this->formatOrder(
                    $order->load(
                        'items.product',
                        'sellerOrders.shipment.rider'
                    )
                ),
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message' => 'Unable to place your order. Please try again.',
            ], 500);
        }
    }

    public function show(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->load([
            'items.product:id,name,price_minor,discounted_price_minor,discount_percent,discount_starts_at,discount_ends_at,image',
            'items.variation:id,product_id,name,price_minor,discounted_price_minor,discount_percent,image',
            'items.size:id,name,slug',
            'sellerOrders.seller:id,name,slug',
            'sellerOrders.shipment:id,seller_order_id,tracking_number,status,delivered_at,picked_up_at,rider_id',
            'sellerOrders.shipment.rider:id,name,phone,vehicle_type,latitude,longitude',
            'rider:id,name,phone,vehicle_type,latitude,longitude',
        ]);

        return response()->json([
            'data' => $this->formatOrder($order),
        ]);
    }

    public function cancel(Order $order)
    {
        if ((int) $order->user_id !== (int) Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $cancelledOrder = DB::transaction(function () use ($order) {
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    !in_array(
                        $lockedOrder->status,
                        ['pending', 'placed', 'confirmed', 'preparing'],
                        true
                    )
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'order' => 'Order cannot be cancelled at this stage.',
                    ]);
                }

                $sellerOrderIds = $lockedOrder
                    ->sellerOrders()
                    ->pluck('id');

                $shipments = Shipment::query()
                    ->whereIn('seller_order_id', $sellerOrderIds)
                    ->lockForUpdate()
                    ->get();

                /*
                |--------------------------------------------------------------------------
                | PHYSICAL CUSTODY GUARD
                |--------------------------------------------------------------------------
                |
                | Once the parcel has already been physically collected by a rider
                | or entered the logistics flow, normal buyer cancellation is no
                | longer allowed.
                |
                */
                foreach ($shipments as $shipment) {
                    // A status may lag behind a confirmed physical handoff.
                    // Do not cancel parcels that have entered rider or hub custody.
                    $hasPhysicalCustody =
                        $shipment->picked_up_at !== null ||
                        $shipment->seller_scan_confirmed_at !== null ||
                        $shipment->scanned_at_seller !== null ||
                        $shipment->hub_picked_up_at !== null ||
                        (bool) $shipment->received_by_sorting_center ||
                        $shipment->received_at !== null ||
                        $shipment->at_sorting_center_at !== null ||
                        $shipment->origin_hub_received_at !== null ||
                        $shipment->destination_hub_received_at !== null ||
                        $shipment->transfer_dispatched_at !== null ||
                        $shipment->linehaul_started_at !== null ||
                        $shipment->linehaul_completed_at !== null ||
                        $shipment->scanned_at !== null ||
                        $shipment->sorted_at !== null ||
                        $shipment->staged_at !== null ||
                        $shipment->failed_returned_to_sorting_center_at !== null ||
                        $shipment->failed_return_received_at !== null;

                    if (
                        $hasPhysicalCustody ||
                        in_array(
                            $shipment->status,
                            [
                                'picked_up',
                                'in_transit',
                                'at_sorting_center',
                                'sorted',
                                'staged',
                                'assigned_to_rider',
                                'out_for_delivery',
                                'delivery_failed',
                                'failed_return_at_sorting_center',
                                'delivered',
                                'completed',
                                'failed',
                                'cancelled',
                                'canceled',
                            ],
                            true
                        )
                    ) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'order' =>
                            'Order can no longer be cancelled because a parcel '
                                . 'is already in the logistics delivery process.',
                        ]);
                    }
                }

                // Reverse any active voucher reservation/redemption in the
                // same transaction as cancellation and inventory restoration.
                app(VoucherRedemptionService::class)->reverseForOrder(
                    (int) $lockedOrder->id
                );

                /*
                |--------------------------------------------------------------------------
                | RELEASE RESERVED RIDER CAPACITY
                |--------------------------------------------------------------------------
                |
                | A rider assignment reserves one capacity slot. If cancellation
                | happens before physical pickup, release the slot exactly once.
                |
                */
                foreach ($shipments as $shipment) {
                    $riderIds = collect([
                        $shipment->pickup_rider_id,
                        $shipment->rider_id,
                    ])
                        ->filter()
                        ->unique()
                        ->values();

                    foreach ($riderIds as $riderId) {
                        $rider = \App\Models\User::query()
                            ->whereKey($riderId)
                            ->lockForUpdate()
                            ->first();

                        if (!$rider) {
                            continue;
                        }

                        $newLoad = max(
                            0,
                            (int) $rider->current_load - 1
                        );

                        $capacity = $rider->max_capacity;

                        $rider->update([
                            'current_load' => $newLoad,
                            'availability_status' =>
                            $capacity === null ||
                                $newLoad < (int) $capacity
                                ? 'available'
                                : 'busy',
                        ]);
                    }

                    $shipmentUpdates = [
                        'rider_id' => null,
                        'status' => 'cancelled',
                    ];

                    if (
                        \Illuminate\Support\Facades\Schema::hasColumn(
                            'shipments',
                            'pickup_rider_id'
                        )
                    ) {
                        $shipmentUpdates['pickup_rider_id'] = null;
                    }

                    $shipment->update($shipmentUpdates);
                }

                /*
                |--------------------------------------------------------------------------
                | RESTORE INVENTORY
                |--------------------------------------------------------------------------
                */
                $lockedOrder->loadMissing([
                    'items.product',
                    'items.variation',
                ]);

                foreach ($lockedOrder->items as $item) {
                    $quantity = (int) $item->quantity;

                    if ($quantity <= 0) {
                        continue;
                    }

                    if (
                        $item->variant_id &&
                        $item->variation
                    ) {
                        $item->variation->increment(
                            'stock',
                            $quantity
                        );
                    } elseif ($item->product) {
                        $item->product->increment(
                            'stock',
                            $quantity
                        );
                    }

                    if (
                        $item->size_id &&
                        $item->product_id
                    ) {
                        DB::table('product_size')
                            ->where(
                                'product_id',
                                $item->product_id
                            )
                            ->where(
                                'size_id',
                                $item->size_id
                            )
                            ->increment(
                                'stock',
                                $quantity
                            );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | KEEP ALL ORDER LEVELS IN SYNC
                |--------------------------------------------------------------------------
                */
                $lockedOrder->sellerOrders()->update([
                    'status' => 'cancelled',
                ]);

                $lockedOrder->update([
                    'status' => 'cancelled',
                ]);

                return $lockedOrder->fresh();
            }, 3);

            return response()->json([
                'message' => 'Order cancelled successfully.',
                'data' => $this->formatOrder($cancelledOrder),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' =>
                collect($e->errors())
                    ->flatten()
                    ->first()
                    ?? 'Order cannot be cancelled.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' =>
                'Failed to cancel order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function rate(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'delivered') {
            return response()->json([
                'message' => 'Can only rate delivered orders.',
            ], 422);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.rating' => 'required|integer|min:1|max:5',
            'items.*.review' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create overall order review
        $order->update([
            'rating' => $request->rating,
            'review' => $request->review,
            'reviewed_at' => now(),
        ]);

        // Create item reviews
        if (!empty($request->items)) {
            foreach ($request->items as $itemReview) {
                $order->items()->where('id', $itemReview['order_item_id'])->first()?->reviews()->create([
                    'user_id' => Auth::id(),
                    'rating' => $itemReview['rating'],
                    'review' => $itemReview['review'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Thank you for your review!',
        ]);
    }

    private function formatOrder(Order $order): array
    {
        $order->loadMissing([
            'items.product',
            'items.variation',
            'items.size',
            'sellerOrders.seller',
            'sellerOrders.shipment.rider',
            'rider',
        ]);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'delivery_status' => $order->delivery_status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,

            'subtotal' => $order->subtotal_minor / 100,
            'shipping_fee' => $order->shipping_minor / 100,

            // Platform Service Fee: 3% of product subtotal before voucher discounts.
            'service_fee' => (
                $order->service_fee_minor ?? 0
            ) / 100,

            // Voucher discount breakdown.
            'discount' => (
                $order->discount_minor ?? 0
            ) / 100,
            'seller_discount' => (
                $order->seller_discount_minor ?? 0
            ) / 100,
            'platform_discount' => (
                $order->platform_discount_minor ?? 0
            ) / 100,

            'total' => $order->total_minor / 100,
            'notes' => $order->notes,
            'shipping_address' => $order->shipping_address,
            'created_at' => $order->created_at?->toISOString(),
            'confirmed_at' => $order->confirmed_at?->toISOString(),
            'delivered_at' => $order->delivered_at?->toISOString(),

            'address' => [
                'full_address' => collect($order->shipping_address)
                    ->only([
                        'address_line1',
                        'address_line2',
                        'barangay',
                        'city',
                        'province',
                        'postal_code',
                        'country',
                    ])
                    ->filter()
                    ->implode(', '),
            ],

            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'product_image' => $item->product?->thumbnail
                        ?? $item->product?->image,
                    'variation_name' => $item->variation?->name,
                    'size_name' => $item->size?->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price_minor / 100,
                    'subtotal' => $item->subtotal_minor / 100,

                    // Voucher discount breakdown.
                    'discount' => ($item->discount_minor ?? 0) / 100,
                    'seller_discount' => ($item->seller_discount_minor ?? 0) / 100,
                    'platform_discount' => ($item->platform_discount_minor ?? 0) / 100,
                ];
            }),

            'seller_orders' => $order->sellerOrders->map(function ($sellerOrder) {
                $shipment = $sellerOrder->shipment;

                return [
                    'id' => $sellerOrder->id,
                    'seller_id' => $sellerOrder->seller_id,

                    'seller' => $sellerOrder->seller ? [
                        'id' => $sellerOrder->seller->id,
                        'name' => $sellerOrder->seller->name,
                        'slug' => $sellerOrder->seller->slug,
                    ] : null,

                    'status' => $sellerOrder->status,
                    'subtotal' => $sellerOrder->subtotal_minor / 100,
                    'discount' => ($sellerOrder->discount_minor ?? 0) / 100,
                    'seller_discount' => ($sellerOrder->seller_discount_minor ?? 0) / 100,
                    'platform_discount' => ($sellerOrder->platform_discount_minor ?? 0) / 100,
                    'service_fee' => (
                        $sellerOrder->service_fee_minor ?? 0
                    ) / 100,
                    'shipping' => $sellerOrder->shipping_minor / 100,
                    'total' => $sellerOrder->total_minor / 100,

                    'shipment' => $shipment ? [
                        'id' => $shipment->id,
                        'seller_order_id' => $shipment->seller_order_id,
                        'tracking_number' => $shipment->tracking_number,
                        'status' => $shipment->status,
                        'picked_up_at' => $shipment->picked_up_at?->toISOString(),
                        'delivered_at' => $shipment->delivered_at?->toISOString(),

                        'rider' => $shipment->rider ? [
                            'id' => $shipment->rider->id,
                            'name' => $shipment->rider->name,
                            'phone' => $shipment->rider->phone,
                            'vehicle_type' => $shipment->rider->vehicle_type,
                            'latitude' => $shipment->rider->latitude,
                            'longitude' => $shipment->rider->longitude,
                        ] : null,
                    ] : null,
                ];
            })->values(),

            'rider' => $order->rider ? [
                'id' => $order->rider->id,
                'name' => $order->rider->name,
                'phone' => $order->rider->phone,
            ] : null,
        ];
    }
}
