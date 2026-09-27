<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use App\Models\Category;
use App\Models\Logistic;
use App\Models\Notification;
use App\Models\Seller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function createNotification(
        $userId,
        $title,
        $message,
        $type = 'order',
        $link = null
    ) {
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
        /*
        |--------------------------------------------------------------------------
        | DASHBOARD SUMMARY
        |--------------------------------------------------------------------------
        */

        $totalBuyers = User::where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'buyer');
            })
            ->count();

        $totalSellers = User::where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'seller');
            })
            ->count();

        $totalRiders = User::where('status', User::STATUS_ACTIVE)
            ->where('logistic_status', 'approved')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->count();

        $totalProducts = Product::count();

        $totalOrders = Order::count();

        $totalLogistics = Logistic::where('status', 'active')
            ->whereHas('owner', function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            })
            ->count();

        $totalRevenue = 0;

        if ($totalOrders > 0) {
            $totalRevenue = Order::where('status', 'delivered')
                ->sum('total_minor') / 100;
        }

        /*
        |--------------------------------------------------------------------------
        | PENDING REGISTRATIONS
        |--------------------------------------------------------------------------
        */

        $pendingRegistrations = User::where(
            'status',
            User::STATUS_PENDING
        )
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [
                    'buyer',
                    'seller',
                    'logistics',
                ]);
            })
            ->latest()
            ->take(5)
            ->get();

        $pendingRegistrationsCount = User::where(
            'status',
            User::STATUS_PENDING
        )
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [
                    'buyer',
                    'seller',
                    'logistics',
                ]);
            })
            ->count();

        /*
        |--------------------------------------------------------------------------
        | RESUBMITTED PRODUCTS
        |--------------------------------------------------------------------------
        */

        $resubmittedProducts = Product::where(
            'compliance_status',
            'pending'
        )
            ->latest()
            ->take(5)
            ->get();

        $resubmittedProductsCount = Product::where(
            'compliance_status',
            'pending'
        )->count();

        /*
        |--------------------------------------------------------------------------
        | FLAGGED PRODUCTS
        |--------------------------------------------------------------------------
        */

        $flaggedProducts = Product::whereIn(
            'compliance_status',
            [
                'flagged',
                'auto_flagged',
            ]
        )
            ->latest()
            ->take(5)
            ->get();

        $flaggedProductsCount = Product::whereIn(
            'compliance_status',
            [
                'flagged',
                'auto_flagged',
            ]
        )->count();

        /*
        |--------------------------------------------------------------------------
        | RECENT ORDERS
        |--------------------------------------------------------------------------
        */

        $recentOrders = Order::with('user')
            ->latest()
            ->take(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | RECENT PRODUCTS
        |--------------------------------------------------------------------------
        */

        $topProducts = Product::with('seller')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | ADMIN DASHBOARD VIEW
        |--------------------------------------------------------------------------
        */

        return view('admin.dashboard', compact(
            'totalBuyers',
            'totalSellers',
            'totalRiders',
            'totalProducts',
            'totalOrders',
            'totalRevenue',
            'totalLogistics',

            'pendingRegistrations',
            'pendingRegistrationsCount',

            'resubmittedProducts',
            'resubmittedProductsCount',

            'flaggedProducts',
            'flaggedProductsCount',

            'recentOrders',
            'topProducts'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | RIDER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function riders()
    {
        $riders = User::whereHas('roles', function ($query) {
            $query->where('name', 'rider');
        })
            ->where('logistic_status', 'approved')
            ->whereNotIn('status', [
                User::STATUS_PENDING,
                User::STATUS_REJECTED,
                User::STATUS_DEACTIVATED,
            ])
            ->with('logistic')
            ->withCount([
                'orders as active_deliveries_count' => function ($query) {
                    $query->whereIn('delivery_status', [
                        'assigned_to_rider',
                        'out_for_delivery',
                    ]);
                },
            ])
            ->get();

        return view('admin.riders', compact('riders'));
    }

    /*
    |--------------------------------------------------------------------------
    | BUYER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function buyers(Request $request)
    {
        $query = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'buyer');
            })
            ->whereNotIn('status', [
                User::STATUS_PENDING,
                User::STATUS_REJECTED,
                User::STATUS_DEACTIVATED,
            ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $buyers = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.buyers', compact('buyers'));
    }

    /*
    |--------------------------------------------------------------------------
    | SELLER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function sellers(Request $request)
    {
        $query = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'seller');
            })
            ->whereNotIn('status', [
                User::STATUS_PENDING,
                User::STATUS_REJECTED,
                User::STATUS_DEACTIVATED,
            ])
            ->withCount('products');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sellers = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.sellers', compact('sellers'));
    }

    /*
    |--------------------------------------------------------------------------
    | TS-52 PRODUCT MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function products(Request $request)
    {
        $query = Product::query()
            ->with([
                'seller',
                'category',
                'subcategory',
            ]);

        /*
        | Search by product name or SKU
        */
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        /*
        | Filter by main category
        */
        if ($request->filled('category')) {
            $query->where(
                'category_id',
                $request->category
            );
        }

        /*
        | Filter using products.seller_id
        |
        | Product::seller() points to the sellers table,
        | so seller_id must be used instead of legacy user_id.
        */
        if ($request->filled('seller')) {
            $query->where(
                'seller_id',
                $request->seller
            );
        }

        /*
        | Filter by product publication status
        | draft / published
        */
        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        /*
        | Filter by compliance status
        */
        if ($request->filled('compliance_status')) {
            $query->where(
                'compliance_status',
                $request->compliance_status
            );
        }

        /*
        | Paginated product results
        */
        $products = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        /*
        | Main categories only.
        | Subcategories have a parent_id.
        */
        $categories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        /*
        | Seller records for seller filter.
        */
        $sellers = Seller::query()
            ->orderBy('name')
            ->get();

        return view('admin.products', compact(
            'products',
            'categories',
            'sellers'
        ));
    }

        /*
    |--------------------------------------------------------------------------
    | TS-54 ORDER MONITORING
    |--------------------------------------------------------------------------
    */

    public function orders(Request $request)
    {
        $query = Order::query()
            ->with([
                'user',
                'rider',
                'sellerOrders.seller',
                'sellerOrders.logistic',
                'sellerOrders.items.product',
                'sellerOrders.items.variant',
            ]);

        // Search by order number, buyer name, or buyer email.
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Order status filter.
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Payment method filter.
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Payment status filter.
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Delivery status filter.
        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        // Date range.
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders', compact('orders'));
    }

    public function showOrder(Order $order)
    {
        $order->load([
            'user',
            'rider',
            'collector',
            'payments',
            'sellerOrders.seller',
            'sellerOrders.logistic',
            'sellerOrders.items.product',
            'sellerOrders.items.variant',
            'sellerOrders.shipment',
        ]);

        return view('admin.orders-show', compact('order'));
    }
}