<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $apiKey = config('services.googlemaps.key');
        if (!$apiKey) {
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $apiKey;

        $response = @file_get_contents($url);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if ($data['status'] === 'OK' && !empty($data['results'])) {
            $location = $data['results'][0]['geometry']['location'];
            return [
                'lat' => $location['lat'],
                'lng' => $location['lng'],
            ];
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

    public function index()
    {
        $cartItems = CartItem::where('user_id', Auth::id())
            ->with(['product', 'size', 'variation'])
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = $cartItems->sum(function ($item) {
            $price = $item->variation
                ? (float) $item->variation->effective_price
                : (float) ($item->product->effective_price ?? $item->product->price);
            return $price * $item->quantity;
        });

        $tax = $subtotal * 0.1;
        $shipping = 50;
        $total = $subtotal + $tax + $shipping;
        $paymentMethods = ['cod' => 'Cash on Delivery', 'gcash' => 'GCash', 'credit_card' => 'Credit Card'];

        $user = Auth::user();
        $defaultAddress = $this->buildUserAddress($user);

        return view('checkout.index', compact('cartItems', 'subtotal', 'tax', 'shipping', 'total', 'paymentMethods', 'user', 'defaultAddress'));
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

    public function store(Request $request)
    {
        $request->validate([
            'shipping_address' => 'required|string|max:1000',
            'payment_method' => 'required|in:cod,gcash,credit_card',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cartItems = CartItem::where('user_id', Auth::id())
            ->with(['product.sizes', 'size', 'variation'])
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = $cartItems->sum(function ($item) {
            $price = $item->variation
                ? (float) $item->variation->effective_price
                : (float) ($item->product->effective_price ?? $item->product->price);
            return $price * $item->quantity;
        });

        $tax = $subtotal * 0.1;
        $shipping = 50;
        $total = $subtotal + $tax + $shipping;

        $fullAddress = $request->shipping_address;
        $contactLines = [];
        if ($request->filled('contact_name')) {
            $contactLines[] = 'Recipient: ' . $request->contact_name;
        }
        if ($request->filled('contact_phone')) {
            $contactLines[] = 'Phone: ' . $request->contact_phone;
        }
        if (!empty($contactLines)) {
            $fullAddress = implode("\n", $contactLines) . "\n" . $fullAddress;
        }

        $geocoded = $this->geocodeAddress($request->shipping_address);
        $customerLat = $geocoded['lat'] ?? null;
        $customerLng = $geocoded['lng'] ?? null;
        $deliveryZone = $this->determineDeliveryZone($customerLat, $customerLng);

        $order = Order::create([
            'user_id' => Auth::id(),
            'order_number' => 'ORD-' . strtoupper(Str::random(10)),
            'status' => 'placed',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'shipping_address' => $fullAddress,
            'notes' => $request->notes,
            'ordered_at' => now(),
            'payment_method' => $request->payment_method,
            'payment_status' => $request->payment_method === 'cod' ? 'unpaid' : 'paid',
            'customer_latitude' => $customerLat,
            'customer_longitude' => $customerLng,
            'delivery_zone' => $deliveryZone,
        ]);

        foreach ($cartItems as $item) {
            $unitPrice = $item->variation
                ? (float) $item->variation->effective_price
                : (float) ($item->product->effective_price ?? $item->product->price);
            $itemSubtotal = $unitPrice * $item->quantity;
            $productName = $item->product->name;
            if ($item->variation) {
                $productName .= ' (' . $item->variation->name . ')';
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'variation_id' => $item->variation_id,
                'size_id' => $item->size_id,
                'product_name' => $productName,
                'price' => $unitPrice,
                'quantity' => $item->quantity,
                'subtotal' => $itemSubtotal,
            ]);

            if ($item->size_id) {
                $size = $item->product->sizes->firstWhere('id', $item->size_id);
                if ($size) {
                    $item->product->sizes()->updateExistingPivot($item->size_id, [
                        'stock' => max(0, $size->pivot->stock - $item->quantity),
                    ]);
                }
            }

            if ($item->variation) {
                $item->variation->update([
                    'stock' => max(0, $item->variation->stock - $item->quantity),
                ]);
            }

            $item->product->update([
                'stock' => max(0, $item->product->stock - $item->quantity),
            ]);
        }

        CartItem::where('user_id', Auth::id())->delete();

        $this->createNotification(
            Auth::id(),
            'Order Placed Successfully',
            'Your order ' . $order->order_number . ' has been placed.',
            'order',
            route('orders.show', $order)
        );

        return redirect()->route('orders.show', $order)->with('success', 'Order placed successfully!');
    }
}
