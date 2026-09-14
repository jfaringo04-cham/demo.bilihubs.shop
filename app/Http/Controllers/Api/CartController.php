<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $items = CartItem::with([
            'product:id,name,price,sale_price,discount_percent,thumbnail,stock,status',
            'product.images',
            'variation:id,name,price,image_url,stock',
            'size:id,name,code',
        ])
        ->where('user_id', Auth::id())
        ->orderBy('created_at', 'desc')
        ->get();

        $subtotal = 0;
        $formattedItems = $items->map(function ($item) use (&$subtotal) {
            $product = $item->product;
            $price = $item->variation ? $item->variation->effective_price : ($item->product->effective_price ?? $product->price);
            $itemSubtotal = $price * $item->quantity;
            $subtotal += $itemSubtotal;

            return [
                'id' => $item->id,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'sale_price' => $product->sale_price,
                    'discount_percent' => $product->discount_percent,
                    'effective_price' => $product->effective_price,
                    'thumbnail' => $product->thumbnail,
                    'stock' => $product->stock,
                    'status' => $product->status,
                ],
                'variation' => $item->variation ? [
                    'id' => $item->variation->id,
                    'name' => $item->variation->name,
                    'price' => $item->variation->price,
                    'effective_price' => $item->variation->effective_price,
                    'image_url' => $item->variation->image_url,
                    'stock' => $item->variation->stock,
                ] : null,
                'size' => $item->size ? [
                    'id' => $item->size->id,
                    'name' => $item->size->name,
                    'code' => $item->size->code,
                ] : null,
                'quantity' => $item->quantity,
                'price' => $price,
                'subtotal' => $itemSubtotal,
                'max_quantity' => $this->getMaxQuantity($item),
            ];
        });

        $shippingFee = $subtotal > 0 ? 80 : 0;
        $serviceFee = round($subtotal * 0.03, 2);
        $total = $subtotal + $shippingFee + $serviceFee;

        return response()->json([
            'data' => [
                'items' => $formattedItems,
                'summary' => [
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shippingFee,
                    'service_fee' => $serviceFee,
                    'total' => $total,
                    'items_count' => $items->sum('quantity'),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:product_variations,id',
            'size_id' => 'nullable|exists:sizes,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::with('variations.size')->findOrFail($request->product_id);

        if ($product->status !== 'active' || $product->compliance_status !== 'approved' || !$product->is_approved) {
            return response()->json(['message' => 'Product is not available'], 422);
        }

        $variation = null;
        if ($request->filled('variation_id')) {
            $variation = $product->variations()->findOrFail($request->variation_id);
            $maxStock = $variation->stock;
            $price = $variation->effective_price;
        } elseif ($request->filled('size_id')) {
            $variation = $product->variations()->where('size_id', $request->size_id)->first();
            $maxStock = $variation ? $variation->stock : $product->stock;
            // Check size stock
            if ($variation) {
                $size = $product->sizes()->findOrFail($request->size_id);
                $maxStock = min($maxStock, $size->pivot->stock ?? $variation->stock);
            }
            $price = $variation ? $variation->effective_price : $product->effective_price;
        } else {
            $maxStock = $product->stock;
            $price = $product->effective_price;
        }

        if ($maxStock < $request->quantity) {
            return response()->json([
                'message' => 'Insufficient stock. Only ' . $maxStock . ' available.',
            ], 422);
        }

        // Check if item already in cart
        $existing = CartItem::where('user_id', Auth::id())
            ->where('product_id', $request->product_id)
            ->where('variation_id', $request->variation_id)
            ->where('size_id', $request->size_id)
            ->first();

        if ($existing) {
            $newQuantity = $existing->quantity + $request->quantity;
            if ($newQuantity > $maxStock) {
                return response()->json([
                    'message' => 'Cannot add more. Maximum quantity reached.',
                ], 422);
            }
            $existing->update(['quantity' => $newQuantity]);
            $item = $existing;
        } else {
            $item = CartItem::create([
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'variation_id' => $request->variation_id,
                'size_id' => $request->size_id,
                'quantity' => $request->quantity,
            ]);
        }

        return response()->json([
            'message' => 'Added to cart.',
            'data' => $item->load('product:id,name,price,effective_price,thumbnail', 'variation:id,name,effective_price,image_url', 'size:id,name,code'),
        ], 201);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        if ($cartItem->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = $cartItem->product;
        $maxStock = $this->getMaxQuantity($cartItem);

        if ($request->quantity > $maxStock) {
            return response()->json([
                'message' => 'Maximum available quantity is ' . $maxStock,
            ], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Cart updated.',
            'data' => $cartItem->load('product:id,name,price,effective_price,thumbnail', 'variation:id,name,effective_price,image_url', 'size:id,name,code'),
        ]);
    }

    public function destroy(CartItem $cartItem)
    {
        if ($cartItem->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Removed from cart.',
        ]);
    }

    public function clear(Request $request)
    {
        CartItem::where('user_id', Auth::id())->delete();

        return response()->json([
            'message' => 'Cart cleared.',
        ]);
    }

    public function buyNow(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variation_id' => 'nullable|exists:product_variations,id',
            'size_id' => 'nullable|exists:sizes,id',
            'quantity' => 'required|integer|min:1',
            'address_id' => 'required|exists:addresses,id',
            'payment_method' => 'required|in:cod,gcash,maya,card,bank_transfer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create temporary cart item and process order
        return $this->store($request->merge(['quantity' => $request->quantity]));
    }

    private function getMaxQuantity(CartItem $item): int
    {
        if ($item->variation) {
            $stock = $item->variation->stock;
            if ($item->size_id) {
                $size = $item->product->sizes()->find($item->size_id);
                if ($size) {
                    $stock = min($stock, $size->pivot->stock ?? $stock);
                }
            }
            return $stock;
        }

        if ($item->size_id) {
            $size = $item->product->sizes()->find($item->size_id);
            if ($size) {
                return $size->pivot->stock ?? $item->product->stock;
            }
        }

        return $item->product->stock;
    }
}