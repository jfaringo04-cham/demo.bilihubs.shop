<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
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
            'items.product:id,name,price,image,thumbnail',
            'items.variation:id,name,price,image_url',
            'address',
            'shipment:id,order_id,tracking_number,status,delivered_at',
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
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
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
        $subtotal = 0;

        foreach ($request->items as $item) {
            $product = Product::with('variations.size')->findOrFail($item['product_id']);

            $variation = null;
            if (!empty($item['variation_id'])) {
                $variation = $product->variations()->findOrFail($item['variation_id']);
            } elseif (!empty($item['size_id'])) {
                $variation = $product->variations()
                    ->where('size_id', $item['size_id'])
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
                $price = $variation->effective_price;
                $variation->decrement('stock', $quantity);
            } else {
                if ($product->stock < $quantity) {
                    return response()->json([
                        'message' => "Insufficient stock for {$product->name}",
                    ], 422);
                }
                $price = $product->effective_price;
                $product->decrement('stock', $quantity);
            }

            // Check size stock
            if (!empty($item['size_id'])) {
                $size = $product->sizes()->findOrFail($item['size_id']);
                if (($size->pivot->stock ?? 0) < $quantity) {
                    return response()->json([
                        'message' => "Size {$size->name} stock insufficient for {$product->name}",
                    ], 422);
                }
                $size->pivot->decrement('stock', $quantity);
            }

            $itemSubtotal = $price * $quantity;
            $subtotal += $itemSubtotal;

            $cartItems[] = [
                'product_id' => $product->id,
                'variation_id' => $variation?->id,
                'size_id' => $item['size_id'] ?? null,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $itemSubtotal,
            ];
        }

        // Calculate fees
        $shippingFee = 80; // Base shipping
        $serviceFee = round($subtotal * 0.03, 2); // 3% service fee
        $total = $subtotal + $shippingFee + $serviceFee;

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => Auth::id(),
                'address_id' => $address->id,
                'order_number' => 'ORD-' . Str::upper(Str::random(10)),
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_method === 'cod' ? 'unpaid' : 'pending',
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'service_fee' => $serviceFee,
                'total' => $total,
                'notes' => $request->notes,
                'shipping_address' => $address->full_address,
                'customer_latitude' => $address->latitude,
                'customer_longitude' => $address->longitude,
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'],
                    'size_id' => $item['size_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            // Clear cart for these items
            CartItem::where('user_id', Auth::id())
                ->whereIn('product_id', array_column($cartItems, 'product_id'))
                ->delete();

            DB::commit();

            // Create shipment
            $this->createShipment($order);

            return response()->json([
                'message' => 'Order placed successfully.',
                'data' => $this->formatOrder($order->load('items.product', 'address', 'shipment')),
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
            'items.product:id,name,price,image,thumbnail',
            'items.variation:id,name,price,image_url',
            'items.size:id,name,code',
            'address',
            'shipment:id,order_id,tracking_number,status,delivered_at,picked_up_at,rider_id',
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
            if ($item->variation_id) {
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
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'delivery_status' => $order->delivery_status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'subtotal' => $order->subtotal,
            'shipping_fee' => $order->shipping_fee,
            'service_fee' => $order->service_fee,
            'discount' => $order->discount ?? 0,
            'total' => $order->total,
            'notes' => $order->notes,
            'shipping_address' => $order->shipping_address,
            'created_at' => $order->created_at?->toISOString(),
            'confirmed_at' => $order->confirmed_at?->toISOString(),
            'delivered_at' => $order->delivered_at?->toISOString(),
            'address' => $order->address ? [
                'full_address' => $order->address->full_address,
                'recipient_name' => $order->address->recipient_name,
                'phone' => $order->address->phone,
            ] : null,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? 'Unknown',
                    'product_image' => $item->product?->thumbnail ?? $item->product?->image,
                    'variation_name' => $item->variation?->name,
                    'size_name' => $item->size?->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];
            }),
            'shipment' => $order->shipment ? [
                'id' => $order->shipment->id,
                'tracking_number' => $order->shipment->tracking_number,
                'status' => $order->shipment->status,
                'delivered_at' => $order->shipment->delivered_at?->toISOString(),
                'rider' => $order->shipment->rider ? [
                    'id' => $order->shipment->rider->id,
                    'name' => $order->shipment->rider->name,
                    'phone' => $order->shipment->rider->phone,
                    'vehicle_type' => $order->shipment->rider->vehicle_type,
                    'latitude' => $order->shipment->rider->latitude,
                    'longitude' => $order->shipment->rider->longitude,
                ] : null,
            ] : null,
            'rider' => $order->rider ? [
                'id' => $order->rider->id,
                'name' => $order->rider->name,
                'phone' => $order->rider->phone,
            ] : null,
        ];
    }

    private function createShipment(Order $order)
    {
        $hub = \App\Services\HubAssignmentService::findBestHubForOrder($order);
        $seller = $order->items->first()->product->user ?? null;

        $shipment = Shipment::create([
            'logistic_id' => $hub?->logistic_id,
            'hub_id' => $hub?->id,
            'order_id' => $order->id,
            'tracking_number' => 'SPE-' . Str::upper(Str::random(10)),
            'status' => $hub ? 'pending' : 'pending_logistic',
            'pickup_address' => $seller ? ($seller->business_name . ', ' . $seller->street_address . ', ' . $seller->barangay . ', ' . $seller->municipality . ', ' . $seller->province) : 'Seller address',
            'delivery_address' => $order->shipping_address,
            'notes' => $hub ? 'Auto-assigned hub based on pickup location: ' . $hub->name : 'Awaiting logistic assignment',
        ]);

        if ($hub) {
            \App\Services\RiderAssignmentService::autoAssignRiderToOrder($order);
        }

        return $shipment;
    }
}