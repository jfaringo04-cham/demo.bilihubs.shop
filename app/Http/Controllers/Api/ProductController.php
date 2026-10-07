<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images', 'variations', 'seller'])
    ->where('status', 'published')
    ->where('compliance_status', 'approved');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('min_price')) {
            $query->where(
                'price_minor',
                '>=',
                (int) round((float) $request->min_price * 100)
            );
        }

        if ($request->filled('max_price')) {
            $query->where(
                'price_minor',
                '<=',
                (int) round((float) $request->max_price * 100)
            );
        }

        if ($request->filled('size_id')) {
            $query->whereHas('sizes', function ($q) use ($request) {
                $q->where('sizes.id', $request->size_id);
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['price', 'created_at', 'name', 'rating'];

        if (in_array($sortBy, $allowedSorts)) {
            $sortColumn = $sortBy === 'price' ? 'price_minor' : $sortBy;
            $query->orderBy($sortColumn, $sortOrder);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product)
    {
        if (
    $product->status !== 'published' ||
    $product->compliance_status !== 'approved'
) {
    return response()->json([
        'message' => 'Product not found',
    ], 404);
}

        $product->load([
            'category',
            'images',
            'variations',
            'seller:id,user_id,name,slug,logo_path,status',
        ]);

        $sizes = $product->sizes()
    ->wherePivot('stock', '>', 0)
    ->select('sizes.id', 'sizes.name')
    ->get();

        $variations = $product->variations()
            ->where('stock', '>', 0)
            ->get()
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price' => $variant->effective_price_minor / 100,
                    'stock' => $variant->stock,
                    'image_url' => $variant->image_url,
                    'size' => $variant->size,
                ];
            });

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price_minor / 100,
                'effective_price' => $product->effective_price_minor / 100,
                'discount_percent' => $product->discount_percent,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'video_url' => $product->video_url,
                'secondary_image_url' => $product->secondary_image_url,
                'category' => $product->category,
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->image_url,
                        'is_primary' => $image->is_primary,
                    ];
                }),
                'variations' => $variations,
                'sizes' => $sizes,
                'seller' => $product->seller,
                'rating' => $product->rating ?? 0,
                'reviews_count' => $product->reviews_count ?? 0,
                'is_favorite' => false,
            ],
        ]);
    }

   public function categories()
{
    $categories = Category::orderBy('name')
        ->get(['id', 'name', 'slug', 'description', 'image', 'parent_id']);

    return response()->json([
        'data' => $categories,
    ]);
}

    public function sizes()
{
    $sizes = Size::orderBy('name')
        ->get(['id', 'name', 'slug']);

    return response()->json([
        'data' => $sizes,
    ]);
}
}
