<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $subtotalMinor = $cartItems->sum(function ($item) {
            $priceMinor = $item->variation
                ? (int) $item->variation->effective_price_minor
                : (int) $item->product->effective_price_minor;

            return $priceMinor * $item->quantity;
        });

        $taxMinor = intdiv(($subtotalMinor * 10) + 50, 100);
        $shippingMinor = 5000;
        $totalMinor = $subtotalMinor + $taxMinor + $shippingMinor;

        // Convert to pesos only at the display boundary.
        $subtotal = $subtotalMinor / 100;
        $tax = $taxMinor / 100;
        $shipping = $shippingMinor / 100;
        $total = $totalMinor / 100;
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
            ->with(['product.sizes', 'product.seller', 'size', 'variation'])
            ->get();

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

        $taxMinor = intdiv(($subtotalMinor * 10) + 50, 100);
        $shippingMinor = 5000;
        $totalMinor = $subtotalMinor + $taxMinor + $shippingMinor;

        // Temporary legacy peso values for dual-write compatibility.
        $subtotal = $subtotalMinor / 100;
        $tax = $taxMinor / 100;
        $shipping = $shippingMinor / 100;
        $total = $totalMinor / 100;

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

        $order = DB::transaction(function () use (
            $request,
            $cartItems,
            $subtotal,
            $tax,
            $shipping,
            $total,
            $subtotalMinor,
            $taxMinor,
            $shippingMinor,
            $totalMinor,
            $fullAddress,
            $customerLat,
            $customerLng,
            $deliveryZone
        ) {
            $order = Order::create([
                'user_id' => Auth::id(),
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'status' => 'placed',
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'shipping_minor' => $shippingMinor,
                'total_minor' => $totalMinor,
                'shipping_address' => $fullAddress,
                'notes' => $request->notes,
                'ordered_at' => now(),
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_method === 'cod' ? 'unpaid' : 'paid',
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
            $allocatedShippingMinor = 0;
            $allocatedTaxMinor = 0;

            foreach ($itemsBySeller as $sellerId => $sellerItems) {
                $sellerIndex++;

                $sellerSubtotalMinor = $sellerItems->sum(function ($item) {
                    $unitPriceMinor = $item->variation
                        ? (int) $item->variation->effective_price_minor
                        : (int) $item->product->effective_price_minor;

                    return $unitPriceMinor * $item->quantity;
                });

                // Allocate integer centavos. The final seller receives any remainder
                // so all SellerOrder totals reconcile exactly with the parent Order.
                if ($sellerIndex === $sellerCount) {
                    $sellerShippingMinor = $shippingMinor - $allocatedShippingMinor;
                    $sellerTaxMinor = $taxMinor - $allocatedTaxMinor;
                } else {
                    $sellerShippingMinor = intdiv($shippingMinor, $sellerCount);
                    $sellerTaxMinor = $subtotalMinor > 0
                        ? intdiv($taxMinor * $sellerSubtotalMinor, $subtotalMinor)
                        : 0;

                    $allocatedShippingMinor += $sellerShippingMinor;
                    $allocatedTaxMinor += $sellerTaxMinor;
                }

                $sellerTotalMinor = $sellerSubtotalMinor + $sellerTaxMinor + $sellerShippingMinor;

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
                ]);

                foreach ($sellerItems as $item) {
                    $unitPriceMinor = $item->variation
                        ? (int) $item->variation->effective_price_minor
                        : (int) $item->product->effective_price_minor;

                    $itemSubtotalMinor = $unitPriceMinor * $item->quantity;

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
            }

            CartItem::where('user_id', Auth::id())->delete();

            return $order;
        });

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

