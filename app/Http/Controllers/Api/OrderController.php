<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\Product;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Shipment;
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
            'data' => $orders->items()->map(function ($order) {
                return $this->formatOrder($order);
            }),
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
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.size_id' => 'nullable|exists:sizes,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $address = Address::where('id', $request->address_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $cartItems = [];
        $subtotalMinor = 0;

        foreach ($request->items as $item) {
            $product = Product::with(['variations.sizes', 'seller'])->findOrFail($item['product_id']);

            if (!$product->seller_id) {
                return response()->json([
                    'message' => "Product {$product->name} is not connected to a seller shop yet.",
                ], 422);
            }

            $variation = null;
            if (!empty($item['variant_id'])) {
                $variation = $product->variations()->findOrFail($item['variant_id']);
            } elseif (!empty($item['size_id'])) {
                $variation = $product->variations()
                    ->whereHas('sizes', function ($query) use ($item) {
                        $query->where('sizes.id', $item['size_id']);
                    })
                    ->first();
            }

            $quantity = $item['quantity'];

            // Check stock
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

            // Check size stock
            if (!empty($item['size_id'])) {
                $size = $product->sizes()->findOrFail($item['size_id']);
                if (($size->pivot->stock ?? 0) < $quantity) {
                    return response()->json([
                        'message' => "Size {$size->name} stock insufficient for {$product->name}",
                    ], 422);
                }
            }

            $itemSubtotalMinor = $priceMinor * $quantity;
            $subtotalMinor += $itemSubtotalMinor;

            $cartItems[] = [
                'product_id' => $product->id,
                'seller_id' => $product->seller_id,
                'variant_id' => $variation?->id,
                'size_id' => $item['size_id'] ?? null,
                'quantity' => $quantity,
                'price_minor' => $priceMinor,
                'subtotal_minor' => $itemSubtotalMinor,
            ];
        }

        // Money calculations use integer centavos as the source of truth.
        $shippingFeeMinor = 8000; // â‚±80.00 base shipping
        $serviceFeeMinor = intdiv(($subtotalMinor * 3) + 50, 100); // 3%, rounded to nearest centavo
        $totalMinor = $subtotalMinor + $shippingFeeMinor + $serviceFeeMinor;

        // Legacy peso values are dual-written temporarily for compatibility.
        $subtotal = $subtotalMinor / 100;
        $shippingFee = $shippingFeeMinor / 100;
        $serviceFee = $serviceFeeMinor / 100;
        $total = $totalMinor / 100;

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => Auth::id(),
                'order_number' => 'ORD-' . Str::upper(Str::random(10)),
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_method === 'cod' ? 'unpaid' : 'pending',
                'subtotal_minor' => $subtotalMinor,
                // Existing API service fee is temporarily stored in the order tax field
                // until the coordinated money/schema transition.
                'tax_minor' => $serviceFeeMinor,
                'shipping_minor' => $shippingFeeMinor,
                'total_minor' => $totalMinor,
                'notes' => $request->notes,
                'shipping_address' => collect([
                    $address->address_line1,
                    $address->address_line2,
                    $address->city,
                    $address->province,
                    $address->postal_code,
                    $address->country,
                ])->filter()->implode(', '),
                'ordered_at' => now(),
                'customer_latitude' => null,
                'customer_longitude' => null,
            ]);

            // Split the buyer order into one SellerOrder per seller/shop.
            $itemsBySeller = collect($cartItems)->groupBy('seller_id');
            $sellerCount = max(1, $itemsBySeller->count());

            $allocatedShippingMinor = 0;
            $allocatedServiceFeeMinor = 0;
            $sellerIndex = 0;

            foreach ($itemsBySeller as $sellerId => $sellerItems) {
                $sellerIndex++;
                $sellerSubtotalMinor = (int) $sellerItems->sum('subtotal_minor');

                // Allocate fees in centavos. The final seller receives any rounding remainder
                // so all SellerOrder totals add up exactly to the buyer Order total.
                if ($sellerIndex === $sellerCount) {
                    $sellerShippingMinor = $shippingFeeMinor - $allocatedShippingMinor;
                    $sellerServiceFeeMinor = $serviceFeeMinor - $allocatedServiceFeeMinor;
                } else {
                    $sellerShippingMinor = intdiv($shippingFeeMinor, $sellerCount);
                    $sellerServiceFeeMinor = $subtotalMinor > 0
                        ? intdiv(($serviceFeeMinor * $sellerSubtotalMinor) + intdiv($subtotalMinor, 2), $subtotalMinor)
                        : 0;

                    $allocatedShippingMinor += $sellerShippingMinor;
                    $allocatedServiceFeeMinor += $sellerServiceFeeMinor;
                }

                $sellerTotalMinor = $sellerSubtotalMinor + $sellerServiceFeeMinor + $sellerShippingMinor;

                $sellerOrder = SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'status' => 'pending',
                    'subtotal_minor' => $sellerSubtotalMinor,
                    'shipping_minor' => $sellerShippingMinor,
                    'total_minor' => $sellerTotalMinor,
                ]);

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

                    // Re-check and deduct stock inside the same DB transaction
                    // so a failed order rolls all stock changes back.
                    $lockedProduct = Product::query()->lockForUpdate()->findOrFail($item['product_id']);

                    if (!empty($item['variant_id'])) {
                        $lockedVariation = $lockedProduct->variations()
                            ->lockForUpdate()
                            ->findOrFail($item['variant_id']);

                        if ($lockedVariation->stock < $item['quantity']) {
                            throw new \RuntimeException("Insufficient stock for {$lockedProduct->name}.");
                        }

                        $lockedVariation->decrement('stock', $item['quantity']);
                    } else {
                        if ($lockedProduct->stock < $item['quantity']) {
                            throw new \RuntimeException("Insufficient stock for {$lockedProduct->name}.");
                        }

                        $lockedProduct->decrement('stock', $item['quantity']);
                    }

                    if (!empty($item['size_id'])) {
                        $sizeRow = DB::table('product_size')
                            ->where('product_id', $item['product_id'])
                            ->where('size_id', $item['size_id'])
                            ->lockForUpdate()
                            ->first();

                        if (!$sizeRow || $sizeRow->stock < $item['quantity']) {
                            throw new \RuntimeException("Insufficient size stock for {$lockedProduct->name}.");
                        }

                        DB::table('product_size')
                            ->where('id', $sizeRow->id)
                            ->update([
                                'stock' => $sizeRow->stock - $item['quantity'],
                                'updated_at' => now(),
                            ]);
                    }

                    OrderItem::create([
                        'seller_order_id' => $sellerOrder->id,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'],
                        'size_id' => $item['size_id'],
                        'product_name' => $productName,
                        'quantity' => $item['quantity'],
                        'price_minor' => $item['price_minor'],
                        'subtotal_minor' => $item['subtotal_minor'],
                    ]);
                }
            }

            // Clear cart for these items
            CartItem::where('user_id', Auth::id())
                ->whereIn('product_id', array_column($cartItems, 'product_id'))
                ->delete();

            DB::commit();

            // Create one shipment for each seller/shop parcel.
            $this->createShipmentsForSellerOrders($order);

            return response()->json([
                'message' => 'Order placed successfully.',
                'data' => $this->formatOrder($order->load(
                    'items.product',
                    'sellerOrders.shipment.rider'
                )),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create order: ' . $e->getMessage(),
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
            'items.size:id,name,code',
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
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($order->status, ['pending', 'confirmed', 'preparing'])) {
            return response()->json([
                'message' => 'Order cannot be cancelled at this stage.',
            ], 422);
        }

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => request('reason') ?? 'Customer cancelled',
        ]);

        // Restore stock
        foreach ($order->items as $item) {
            if ($item->variant_id) {
                $item->variation->increment('stock', $item->quantity);
            } else {
                $item->product->increment('stock', $item->quantity);
            }

            if ($item->size_id) {
                $item->product->sizes()->where('sizes.id', $item->size_id)
                    ->increment('pivot_stock', $item->quantity);
            }
        }

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'data' => $this->formatOrder($order->fresh()),
        ]);
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
            'service_fee' => $order->tax_minor / 100,
            'discount' => $order->discount ?? 0,
            'total' => $order->total_minor / 100,
            'notes' => $order->notes,
            'shipping_address' => $order->shipping_address,
            'created_at' => $order->created_at?->toISOString(),
            'confirmed_at' => $order->confirmed_at?->toISOString(),
            'delivered_at' => $order->delivered_at?->toISOString(),
            'address' => [
                'full_address' => $order->shipping_address,
            ],
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'product_image' => $item->product?->thumbnail ?? $item->product?->image,
                    'variation_name' => $item->variation?->name,
                    'size_name' => $item->size?->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price_minor / 100,
                    'subtotal' => $item->subtotal_minor / 100,
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

    private function createShipmentsForSellerOrders(Order $order): void
    {
        $order->loadMissing([
            'sellerOrders.seller.owner',
            'sellerOrders.seller.pickupAddress',
            'sellerOrders.shipment',
        ]);

        foreach ($order->sellerOrders as $sellerOrder) {
            // Prevent duplicate parcel creation if this method is called again.
            if ($sellerOrder->shipment) {
                continue;
            }

            $hub = \App\Services\HubAssignmentService::findBestHubForSellerOrder($sellerOrder);
            $seller = $sellerOrder->seller;
            $owner = $seller?->owner;
            $pickupAddress = $seller?->pickupAddress;

            if ($pickupAddress) {
                $pickup = collect([
                    $pickupAddress->address_line1,
                    $pickupAddress->address_line2,
                    $pickupAddress->city,
                    $pickupAddress->province,
                    $pickupAddress->postal_code,
                    $pickupAddress->country,
                ])->filter()->implode(', ');
            } elseif ($owner) {
                $pickup = collect([
                    $owner->business_name,
                    $owner->street_address,
                    $owner->barangay,
                    $owner->municipality,
                    $owner->province,
                ])->filter()->implode(', ');
            } else {
                $pickup = 'Seller address';
            }

            $shipment = Shipment::create([
                'seller_order_id' => $sellerOrder->id,
                'logistic_id' => $hub?->logistic_id,
                'hub_id' => $hub?->id,
                'tracking_number' => 'SPE-' . Str::upper(Str::random(10)),
                'status' => $hub ? 'pending' : 'pending_logistic',
                'pickup_address' => $pickup ?: 'Seller address',
                'delivery_address' => $order->shipping_address,
                'notes' => $hub
                    ? 'Auto-assigned hub based on seller pickup location: ' . $hub->name
                    : 'Awaiting logistic assignment',
            ]);

            if ($hub) {
                $rider = \App\Services\RiderAssignmentService::findBestRiderForShipment($shipment);

                if ($rider) {
                    \App\Services\RiderAssignmentService::assignRiderToShipment($shipment, $rider);
                }
            }
        }
    }
}




