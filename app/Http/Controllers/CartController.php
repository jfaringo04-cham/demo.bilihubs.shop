<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = CartItem::where('user_id', Auth::id())
            ->with(['product', 'size'])
            ->get();

        $total = $cartItems->sum(function ($item) {
            $price = $item->variation
                ? (float) $item->variation->effective_price
                : (float) ($item->product->effective_price ?? $item->product->price);
            return $price * $item->quantity;
        });

        return view('cart.index', compact('cartItems', 'total'));
    }

    public function store(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'size_id' => 'nullable|exists:sizes,id',
            'variation_id' => 'nullable|exists:product_variations,id',
        ]);

        if ($request->filled('variation_id')) {
            $variation = $product->variations()->where('id', $request->variation_id)->first();
            if (!$variation) {
                return back()->with('error', 'Selected variation is invalid.');
            }
            if ($variation->stock < $request->quantity) {
                return back()->with('error', 'Selected variation does not have enough stock.');
            }
        }

        if ($request->filled('size_id')) {
            $size = $product->sizes->firstWhere('id', $request->size_id);
            if (!$size) {
                return back()->with('error', 'Selected size is not available for this product.');
            }
            if (($size->pivot->stock ?? 0) < $request->quantity) {
                return back()->with('error', 'Selected size "' . $size->name . '" only has ' . ($size->pivot->stock ?? 0) . ' item(s) in stock. You requested ' . $request->quantity . '.');
            }
        }

        $cartItem = CartItem::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->where('size_id', $request->size_id)
            ->where('variation_id', $request->variation_id)
            ->first();

        if ($cartItem) {
            $cartItem->increment('quantity', $request->quantity);
        } else {
            CartItem::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'size_id' => $request->size_id,
                'variation_id' => $request->variation_id,
                'quantity' => $request->quantity,
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Product added to cart.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $product = $cartItem->product;

        if ($cartItem->variation) {
            if ($cartItem->variation->stock < $request->quantity) {
                return back()->with('error', 'Selected variation does not have enough stock.');
            }
        }

        if ($cartItem->size_id) {
            $size = $product->sizes->firstWhere('id', $cartItem->size_id);
            if ($size && ($size->pivot->stock ?? 0) < $request->quantity) {
                return back()->with('error', 'Selected size "' . $size->name . '" only has ' . ($size->pivot->stock ?? 0) . ' item(s) in stock.');
            }
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return redirect()->route('cart.index')->with('success', 'Cart updated.');
    }

    public function destroy(CartItem $cartItem)
    {
        $cartItem->delete();

        return redirect()->route('cart.index')->with('success', 'Item removed from cart.');
    }

    public function buyNow(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'size_id' => 'nullable|exists:sizes,id',
            'variation_id' => 'nullable|exists:product_variations,id',
        ]);

        $hasAvailableSize = $product->sizes->count() == 0;

        if (!$hasAvailableSize && $request->filled('size_id')) {
            $size = $product->sizes->firstWhere('id', $request->size_id);
            if ($size) {
                $hasAvailableSize = ($size->pivot->stock ?? 0) >= $request->quantity;
            }
        } elseif (!$hasAvailableSize) {
            $hasAvailableSize = $product->sizes->contains(function ($size) {
                return $size->pivot->stock > 0;
            });
        }

        if ($product->stock <= 0 || !$hasAvailableSize) {
            return back()->with('error', 'This product is currently out of stock.');
        }

        $variation = null;
        if ($request->filled('variation_id')) {
            $variation = $product->variations()->where('id', $request->variation_id)->first();
            if (!$variation) {
                return back()->with('error', 'Selected variation is invalid.');
            }
            if ($variation->stock < $request->quantity) {
                return back()->with('error', 'Selected variation does not have enough stock.');
            }
        }

        if ($request->filled('size_id')) {
            $size = $product->sizes->firstWhere('id', $request->size_id);
            if (!$size) {
                return back()->with('error', 'Selected size is not available for this product.');
            }
            if (($size->pivot->stock ?? 0) < $request->quantity) {
                return back()->with('error', 'Selected size "' . $size->name . '" only has ' . ($size->pivot->stock ?? 0) . ' item(s) in stock. You requested ' . $request->quantity . '.');
            }
        }

        CartItem::where('user_id', Auth::id())->delete();

        $cartItem = CartItem::create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
            'size_id' => $request->size_id,
            'variation_id' => $variation ? $variation->id : null,
            'quantity' => $request->quantity,
            'is_buy_now' => true,
        ]);

        return redirect()->route('checkout.index')->with('success', 'Proceeding to checkout for ' . $product->name . '.');
    }
}
