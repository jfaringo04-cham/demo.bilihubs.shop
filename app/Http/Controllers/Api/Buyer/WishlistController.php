<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * GET /api/buyer/wishlist
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isCustomer()) {
            return $this->forbidden();
        }

        $perPage = max(
            1,
            min((int) $request->input('per_page', 20), 100)
        );

        $wishlists = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'product' => function ($query) {
                    $query->with(['category', 'images'])
                        ->withSoldCount();
                },
            ])
            ->latest()
            ->paginate($perPage);

        $items = collect($wishlists->items())
            ->map(function (Wishlist $wishlist) {
                return [
                    'id' => $wishlist->id,
                    'product_id' => $wishlist->product_id,
                    'product' => $wishlist->product
                        ? $this->formatProduct($wishlist->product)
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $wishlists->currentPage(),
                'last_page' => $wishlists->lastPage(),
                'per_page' => $wishlists->perPage(),
                'total' => $wishlists->total(),
            ],
        ]);
    }

    /**
     * POST /api/buyer/wishlist/{product}
     */
    public function store(
        Request $request,
        Product $product
    ): JsonResponse {
        if (!$request->user()->isCustomer()) {
            return $this->forbidden();
        }

        if (
            $product->status !== 'published' ||
            $product->compliance_status !== 'approved'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Product is not available.',
            ], 422);
        }

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'success' => true,
            'wishlisted' => true,
            'message' => $wishlist->wasRecentlyCreated
                ? 'Product added to wishlist.'
                : 'Product is already in your wishlist.',
            'data' => [
                'product_id' => $product->id,
                'wishlisted' => true,
            ],
        ], $wishlist->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * DELETE /api/buyer/wishlist/{product}
     */
    public function destroy(
        Request $request,
        Product $product
    ): JsonResponse {
        if (!$request->user()->isCustomer()) {
            return $this->forbidden();
        }

        Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json([
            'success' => true,
            'wishlisted' => false,
            'message' => 'Product removed from wishlist.',
            'data' => [
                'product_id' => $product->id,
                'wishlisted' => false,
            ],
        ]);
    }

    private function formatProduct(Product $product): array
    {
        $primaryImage = $product->images
            ->firstWhere('is_primary', true)
            ?? $product->images->first();

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price_minor / 100,
            'effective_price' =>
                $product->effective_price_minor / 100,
            'stock' => (int) $product->stock,
            'image_url' => $primaryImage?->url ?? '',
            'primary_image_url' => $primaryImage?->url ?? '',
            'secondary_image_url' =>
                $product->secondary_image_url ?? '',
            'rating' => $product->rating ?? 0,
            'reviews_count' => $product->reviews_count ?? 0,
            'sold_count' => $product->sold_count ?? 0,
            'category' => $product->category,
            'is_available' =>
                $product->status === 'published' &&
                $product->compliance_status === 'approved',
        ];
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Only buyers can access the wishlist.',
        ], 403);
    }
}
