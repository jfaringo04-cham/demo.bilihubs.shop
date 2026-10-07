<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $items = CartItem::with([
            'product:id,name,price_minor,discounted_price_minor,discount_percent,discount_starts_at,discount_ends_at,stock,status',
            'product.images',
            'variation:id,product_id,name,price_minor,discounted_price_minor,discount_percent,stock',
            'size:id,name,slug',
        ])
        ->where('user_id', Auth::id())
        ->orderBy('created_at', 'desc')
        ->get();

        $subtotalMinor = 0;

        $formattedItems = $items->map(function ($item) use (&$subtotalMinor) {
            $product = $item->product;

            $priceMinor = $item->variation
                ? (int) $item->variation->effective_price_minor
                : (int) $product->effective_price_minor;

            $itemSubtotalMinor = $priceMinor * (int) $item->quantity;
            $subtotalMinor += $itemSubtotalMinor;

            return [
                'id' => $item->id,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price_minor / 100,
                    'discount_percent' => $product->discount_percent,
                    'effective_price' => $product->effective_price_minor / 100,
                    'thumbnail' => $product->images->first()?->image_url,
                    'stock' => $product->stock,
                    'status' => $product->status,
                ],
                'variation' => $item->variation ? [
                    'id' => $item->variation->id,
                    'name' => $item->variation->name,
                    'price' => $item->variation->price_minor / 100,
                    'effective_price' => $item->variation->effective_price_minor / 100,
                    'image_url' => null,
                    'stock' => $item->variation->stock,
                ] : null,
                'size' => $item->size ? [
    'id' => $item->size->id,
    'name' => $item->size->name,
    'slug' => $item->size->slug,
] : null,
                'quantity' => $item->quantity,
                'price' => $priceMinor / 100,
                'subtotal' => $itemSubtotalMinor / 100,
                'max_quantity' => $this->getMaxQuantity($item),
            ];
        });

        $shippingFeeMinor = $subtotalMinor > 0 ? 8000 : 0;
        $serviceFeeMinor = intdiv(($subtotalMinor * 3) + 50, 100);
        $totalMinor = $subtotalMinor + $shippingFeeMinor + $serviceFeeMinor;

        return response()->json([
            'data' => [
                'items' => $formattedItems,
                'summary' => [
                    'subtotal' => $subtotalMinor / 100,
                    'shipping_fee' => $shippingFeeMinor / 100,
                    'service_fee' => $serviceFeeMinor / 100,
                    'total' => $totalMinor / 100,
                    'items_count' => $items->sum('quantity'),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'size_id' => 'nullable|exists:sizes,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::with(['variations', 'sizes'])->findOrFail($request->product_id);

        if (
    $product->status !== 'published' ||
    $product->compliance_status !== 'approved'
) {
    return response()->json([
        'message' => 'Product is not available',
    ], 422);
}

        $variation = null;

        if ($request->filled('variant_id')) {
            $variation = $product->variations()->findOrFail($request->variant_id);
            $maxStock = $variation->stock;
        } elseif ($request->filled('size_id')) {
            $variation = $product->variations()->where('size', $product->sizes()->find($request->size_id)?->name)->first();
            $maxStock = $variation ? $variation->stock : $product->stock;

            if ($variation) {
                $size = $product->sizes()->findOrFail($request->size_id);
                $maxStock = min($maxStock, $size->pivot->stock ?? $variation->stock);
            }
        } else {
            $maxStock = $product->stock;
        }

        if ($maxStock < $request->quantity) {
            return response()->json([
                'message' => 'Insufficient stock. Only ' . $maxStock . ' available.',
            ], 422);
        }

        $existing = CartItem::where('user_id', Auth::id())
            ->where('product_id', $request->product_id)
            ->where('variant_id', $request->variant_id)
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
                'variant_id' => $request->variant_id,
                'size_id' => $request->size_id,
                'quantity' => $request->quantity,
            ]);
        }

        $item->load(['product.images', 'variation', 'size']);

        return response()->json([
            'message' => 'Added to cart.',
            'data' => $item,
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

        $maxStock = $this->getMaxQuantity($cartItem);

        if ($request->quantity > $maxStock) {
            return response()->json([
                'message' => 'Maximum available quantity is ' . $maxStock,
            ], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);
        $cartItem->load(['product.images', 'variation', 'size']);

        return response()->json([
            'message' => 'Cart updated.',
            'data' => $cartItem,
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
            'variant_id' => 'nullable|exists:product_variants,id',
            'size_id' => 'nullable|exists:sizes,id',
            'quantity' => 'required|integer|min:1',
            'address_id' => 'required|exists:addresses,id',
            'payment_method' => 'required|in:cod,gcash,maya,card,bank_transfer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

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
