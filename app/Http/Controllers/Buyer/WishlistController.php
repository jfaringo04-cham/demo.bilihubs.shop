<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Display the authenticated buyer's wishlist.
     */
    public function index(Request $request)
{
    $wishlists = Wishlist::query()
        ->where('user_id', $request->user()->id)
        ->with([
            'product' => function ($query) {
                $query
                    ->with(['category', 'images'])
                    ->withSoldCount();
            },
        ])
        ->latest()
        ->paginate(12);

    return view('buyer.wishlist.index', compact('wishlists'));
}

    /**
     * Add/remove a product from the authenticated buyer's wishlist.
     */
    public function toggle(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($wishlist) {
            $wishlist->delete();
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
        }

        $wishlisted = $wishlist === null;
        $message = $wishlisted
            ? 'Product added to wishlist.'
            : 'Product removed from wishlist.';

        if ($request->input('return_to') === 'product' && !$request->expectsJson()) {
            return redirect()->route('products.show', $product)->with('success', $message);
        }

        return response()->json([
            'success' => true,
            'wishlisted' => $wishlisted,
            'message' => $message,
        ]);
    }
}
