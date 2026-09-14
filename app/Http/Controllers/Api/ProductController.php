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
        $query = Product::with(['category', 'images', 'variations.size', 'seller'])
            ->where('status', 'active')
            ->where('compliance_status', 'approved')
            ->where('is_approved', true);

        // Search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Size filter
        if ($request->filled('size_id')) {
            $query->whereHas('variations.size', function ($q) use ($request) {
                $q->where('sizes.id', $request->size_id);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['price', 'created_at', 'name', 'rating'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
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
        if ($product->status !== 'active' || $product->compliance_status !== 'approved' || !$product->is_approved) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->load([
            'category',
            'images',
            'variations.size',
            'seller:id,name,business_name,logo,rating',
        ]);

        // Get available sizes with stock
        $sizes = $product->sizes()
            ->wherePivot('stock', '>', 0)
            ->select('sizes.id', 'sizes.name', 'sizes.code')
            ->get();

        // Get variations with stock
        $variations = $product->variations()
            ->where('stock', '>', 0)
            ->with('size')
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price' => $v->effective_price,
                    'stock' => $v->stock,
                    'image_url' => $v->image_url,
                    'size' => $v->size ? [
                        'id' => $v->size->id,
                        'name' => $v->size->name,
                        'code' => $v->size->code,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'effective_price' => $product->effective_price,
                'discount_percent' => $product->discount_percent,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'video_url' => $product->video_url,
                'secondary_image_url' => $product->secondary_image_url,
                'category' => $product->category,
                'images' => $product->images->map(function ($img) {
                    return [
                        'id' => $img->id,
                        'url' => $img->image_url,
                        'is_primary' => $img->is_primary,
                    ];
                }),
                'variations' => $variations,
                'sizes' => $sizes,
                'seller' => $product->seller,
                'rating' => $product->rating ?? 0,
                'reviews_count' => $product->reviews_count ?? 0,
                'is_favorite' => false, // Would check user's favorites
            ],
        ]);
    }

    public function categories()
    {
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'image']);

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function sizes()
    {
        $sizes = Size::orderBy('sort_order')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'data' => $sizes,
        ]);
    }
}