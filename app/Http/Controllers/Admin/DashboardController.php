<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use App\Models\Category;
use App\Models\Logistic;
use App\Models\Notification;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function createNotification($userId, $title, $message, $type = 'order', $link = null)
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    public function index()
    {
        $totalSellers = User::where('role', 'seller')->count();
        $totalProducts = Product::count();
        $totalOrders = Order::count();
        $totalRevenue = Order::where('status', 'delivered')->sum('total');
        $totalLogistics = Logistic::count();

        $pendingRegistrations = User::where('status', 'pending')
            ->whereIn('role', ['customer', 'seller', 'rider', 'logistic_owner'])
            ->latest()
            ->take(5)
            ->get();

        $pendingRegistrationsCount = User::where('status', 'pending')
            ->whereIn('role', ['customer', 'seller', 'rider', 'logistic_owner'])
            ->count();

        $resubmittedProducts = Product::where('compliance_status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $resubmittedProductsCount = Product::where('compliance_status', 'pending')->count();

        $flaggedProducts = Product::whereIn('compliance_status', ['flagged', 'auto_flagged'])
            ->latest()
            ->take(5)
            ->get();

        $flaggedProductsCount = Product::whereIn('compliance_status', ['flagged', 'auto_flagged'])->count();

        $recentOrders = Order::with('user')->latest()->take(5)->get();
        $topProducts = Product::with('seller')->orderByDesc('id')->take(5)->get();

        return view('admin.dashboard', compact(
            'totalSellers', 'totalProducts', 'totalOrders', 'totalRevenue',
            'recentOrders', 'topProducts', 'totalLogistics',
            'pendingRegistrations', 'pendingRegistrationsCount',
            'resubmittedProducts', 'resubmittedProductsCount',
            'flaggedProducts', 'flaggedProductsCount'
        ));
    }

    public function riders()
    {
        $riders = User::where('role', 'rider')
            ->withCount(['orders as active_deliveries_count' => function ($query) {
                $query->whereIn('delivery_status', ['assigned_to_rider', 'out_for_delivery']);
            }])
            ->get();

        return view('admin.riders', compact('riders'));
    }

    public function sellers()
    {
        $sellers = User::where('role', 'seller')
            ->withCount('products')
            ->with(['products' => function ($query) {
                $query->select('user_id', 'name', 'price', 'stock')->take(5);
            }])
            ->get();

        return view('admin.sellers', compact('sellers'));
    }

    public function products(Request $request)
    {
        $query = Product::with('seller', 'category')->latest();

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('seller')) {
            $query->where('user_id', $request->seller);
        }

        $products = $query->paginate(20);
        $categories = Category::all();

        return view('admin.products', compact('products', 'categories'));
    }
}
