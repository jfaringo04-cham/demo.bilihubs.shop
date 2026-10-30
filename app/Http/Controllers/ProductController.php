<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Voucher;
use App\Services\ComplianceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'seller', 'sizes'])
            ->where('status', 'published')
            ->where('compliance_status', 'approved');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        /*
         * Category filtering:
         * - Main category: include products assigned directly to it
         *   and products assigned to any of its subcategories.
         * - Subcategory: show only that subcategory.
         */
        $selectedCategory = null;

        if ($request->filled('category')) {
            $selectedCategory = Category::with(['parent', 'subcategories'])
                ->find($request->category);

            if ($selectedCategory) {
                if ($selectedCategory->parent_id === null) {
                    $categoryIds = $selectedCategory->subcategories
                        ->pluck('id')
                        ->prepend($selectedCategory->id)
                        ->values()
                        ->all();

                    $query->whereIn('category_id', $categoryIds);
                } else {
                    $query->where('category_id', $selectedCategory->id);
                }
            }
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

        /*
         * Only the official BiliHub main categories are shown in the sidebar.
         * This prevents old/legacy category records from appearing as separate
         * top-level entries.
         */
        $officialMainSlugs = array_keys(
            config('categories.subcategories', [])
        );

        $categories = Category::query()
            ->whereNull('parent_id')
            ->whereIn('slug', $officialMainSlugs)
            ->with([
                'subcategories' => function ($subQuery) {
                    $subQuery->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();

        return view(
            'products.index',
            compact(
                'products',
                'categories',
                'selectedCategory'
            )
        );
    }

    public function show(Product $product)
    {
        $user = Auth::user();

        $isOwner = $user && $user->id === $product->user_id;
        $isAdmin = $user && $user->isAdmin();

        if (
            (
                $product->status !== 'published' ||
                $product->compliance_status !== 'approved'
            )
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

        $availableSellerVouchers = collect();

        if ($product->seller_id) {
            $availableSellerVouchers = Voucher::query()
                ->with([
                    'seller',
                    'products:id',
                ])
                ->currentlyActive()
                ->where('owner_type', 'seller')
                ->where('seller_id', $product->seller_id)
                ->whereHas('seller', function ($query) {
                    $query->where('status', 'approved');
                })
                ->where(function ($query) {
                    $query
                        ->whereNull('usage_limit')
                        ->orWhereColumn(
                            'usage_count',
                            '<',
                            'usage_limit'
                        );
                })
                ->where(function ($query) use ($product) {
                    $query
                        ->where('product_scope', 'all')
                        ->orWhere(function ($selected) use ($product) {
                            $selected
                                ->where('product_scope', 'selected')
                                ->whereHas(
                                    'products',
                                    function ($products) use ($product) {
                                        $products->where(
                                            'products.id',
                                            $product->id
                                        );
                                    }
                                );
                        });
                })
                ->orderBy('ends_at')
                ->orderBy('id')
                ->get();

            if (Auth::check()) {
                $userId = (int) Auth::id();

                $availableSellerVouchers =
                    $availableSellerVouchers
                        ->filter(function ($voucher) use ($userId) {
                            if ($voucher->per_user_limit === null) {
                                return true;
                            }

                            $used = $voucher->redemptions()
                                ->where('user_id', $userId)
                                ->where('status', 'redeemed')
                                ->count();

                            return $used < (int) $voucher->per_user_limit;
                        })
                        ->values();
            }
        }

        $reviews = $product->reviews()
            ->with('user')
            ->latest()
            ->paginate(10);

        $relatedProducts = Product::where(
            'category_id',
            $product->category_id
        )
            ->where('id', '!=', $product->id)
            ->where('status', 'published')
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
                'variationsJson',
                'availableSellerVouchers'
            )
        );
    }
}
