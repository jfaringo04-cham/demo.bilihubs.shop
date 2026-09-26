<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Review;

class HomeController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'seller'])
            ->where('compliance_status', 'approved')
            ->where('status', 'published')
            ->take(8)
            ->get();
        $categories = Category::all();

        $flashDeals = Product::with(['category', 'seller'])
            ->where('compliance_status', 'approved')
            ->where('status', 'published')
            ->where('discount_percent', '>', 0)
            ->where(function($q) {
                $q->whereNull('discount_starts_at')
                    ->orWhere('discount_starts_at', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('discount_ends_at')
                    ->orWhere('discount_ends_at', '>=', now());
            })
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withSum('orderItems as sold_count', 'quantity')
            ->orderBy('discount_ends_at', 'asc')
            ->take(8)
            ->get();

        $countdownEnd = $flashDeals->min('discount_ends_at');
        if (!$countdownEnd) {
            $countdownEnd = now()->addDays(1);
        }

        $trendingProducts = Product::with(['category', 'seller', 'images'])
            ->trending(8)
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get();

        if ($trendingProducts->isEmpty()) {
            $trendingProducts = Product::with(['category', 'seller', 'images'])
                ->where('compliance_status', 'approved')
                ->where('status', 'published')
                ->withSum('orderItems as sold_count', 'quantity')
                ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE product_id = products.id) > 0')
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->orderByDesc('sold_count')
                ->limit(8)
                ->get();
        }

        $testimonials = Review::with(['user', 'product', 'order'])
            ->where('rating', '>=', 4)
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->where(function($q) {
                $q->whereNull('order_id')
                  ->orWhereHas('order', function($orderQ) {
                      $orderQ->whereIn('status', ['completed', 'delivered']);
                  });
            })
            ->orderByDesc('rating')
            ->latest()
            ->limit(6)
            ->get();

        $reviewsCount = Review::whereNotNull('comment')
            ->where('comment', '!=', '')
            ->count();

        $avgRating = Review::avg('rating');

        return view('home', compact('products', 'categories', 'flashDeals', 'countdownEnd', 'trendingProducts', 'testimonials', 'reviewsCount', 'avgRating'));
    }

    public function about()
    {
        return view('about');
    }
}
