<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Services\ComplianceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'seller', 'sizes'])
            ->where('compliance_status', 'approved');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Input is in pesos; database money is stored in centavos.
        if ($request->filled('min_price')) {
            $minPriceMinor = (int) round(
                (float) $request->min_price * 100
            );

            $query->where('price_minor', '>=', $minPriceMinor);
        }

        if ($request->filled('max_price')) {
            $maxPriceMinor = (int) round(
                (float) $request->max_price * 100
            );

            $query->where('price_minor', '<=', $maxPriceMinor);
        }

        $products = $query->paginate(12);
        $categories = Category::all();

        return view(
            'products.index',
            compact('products', 'categories')
        );
    }

    public function show(Product $product)
    {
        $user = Auth::user();

        $isOwner = $user && $user->id === $product->user_id;
        $isAdmin = $user && $user->isAdmin();

        if (
            $product->compliance_status !== 'approved'
            && !$isOwner
            && !$isAdmin
        ) {
            abort(404);
        }

        $product->load([
            'sizes',
            'variations',
        ]);

        $variationsJson = $product->variations
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,

                    // API/view display remains pesos,
                    // but source of truth is integer centavos.
                    'price' => $variant->effective_price_minor / 100,

                    'image' => $variant->image_url,
                    'stock' => (int) $variant->stock,
                ];
            })
            ->values()
            ->all();

        $reviews = $product->reviews()
            ->with('user')
            ->latest()
            ->paginate(10);

        $relatedProducts = Product::where(
                'category_id',
                $product->category_id
            )
            ->where('id', '!=', $product->id)
            ->where('compliance_status', 'approved')
            ->with('sizes')
            ->take(4)
            ->get();

        $averageRating = $product->reviews()->avg('rating');
        $totalReviews = $product->reviews()->count();

        $canReview = false;

        if (Auth::check() && Auth::user()->isCustomer()) {
            $canReview = Order::where('user_id', Auth::id())
                ->where('status', 'delivered')
                ->whereHas(
                    'items',
                    function ($query) use ($product) {
                        $query->where(
                            'product_id',
                            $product->id
                        );
                    }
                )
                ->exists();
        }

        return view(
            'products.show',
            compact(
                'product',
                'reviews',
                'relatedProducts',
                'averageRating',
                'totalReviews',
                'canReview',
                'variationsJson'
            )
        );
    }
}