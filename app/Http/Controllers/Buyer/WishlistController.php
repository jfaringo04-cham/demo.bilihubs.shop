<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
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
    public function toggle(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($wishlist) {
            $wishlist->delete();

            return response()->json([
                'success' => true,
                'wishlisted' => false,
                'message' => 'Product removed from wishlist.',
            ]);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'success' => true,
            'wishlisted' => true,
            'message' => 'Product added to wishlist.',
        ]);
    }
}