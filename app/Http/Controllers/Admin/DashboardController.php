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
            })->count();

        $totalSellers = User::where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'seller');
            })->count();

        $totalRiders = User::where('status', User::STATUS_ACTIVE)
            ->where('logistic_status', 'approved')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })->count();

        $totalProducts = Product::count();

        $totalOrders = Order::count();

        $totalLogistics = Logistic::where('status', 'active')
            ->whereHas('owner', function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            })->count();

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

        $pendingRegistrations = User::where('status', User::STATUS_PENDING)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [
                    'buyer',
                    'seller',
                    'logistics'
                ]);
            })
            ->latest()
            ->take(5)
            ->get();

        $pendingRegistrationsCount = User::where('status', User::STATUS_PENDING)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [
                    'buyer',
                    'seller',
                    'logistics'
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
                'auto_flagged'
            ]
        )
            ->latest()
            ->take(5)
            ->get();

        $flaggedProductsCount = Product::whereIn(
            'compliance_status',
            [
                'flagged',
                'auto_flagged'
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


    public function riders()
    {
        // Admin Rider Management is a monitoring list of riders that already
        // went through their Logistics provider's approval. Riders still
        // pending (or rejected) by that provider stay in the provider's
        // "Pending Riders" queue instead.
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
                        'out_for_delivery'
                    ]);
                }
            ])
            ->get();

        return view('admin.riders', compact('riders'));
    }

    public function buyers(Request $request)
{
    // Buyer Management only lists buyer accounts that already passed
    // Admin approval. Pending applications remain in Admin -> Registrations.
    // Rejected and deactivated buyers are excluded, while suspended buyers
    // remain visible so the admin can continue managing the account.
    $query = User::query()
        ->whereHas('roles', function ($query) {
            $query->where('name', 'buyer');
        })
        ->whereNotIn('status', [
            User::STATUS_PENDING,
            User::STATUS_REJECTED,
            User::STATUS_DEACTIVATED,
        ]);

    // Search buyer by name, email, or phone.
    if ($request->filled('search')) {
        $search = trim($request->search);

        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    // Filter managed buyers by account status.
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    $buyers = $query
        ->latest()
        ->paginate(10)
        ->withQueryString();

    return view('admin.buyers', compact('buyers'));
}


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

    // Search by seller name, email, or phone
    if ($request->filled('search')) {
        $search = trim($request->search);

        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    // Filter active/suspended managed sellers
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    $sellers = $query
        ->latest()
        ->paginate(10)
        ->withQueryString();

    return view('admin.sellers', compact('sellers'));
}


    public function products(Request $request)
    {
        $query = Product::with('seller', 'category')
            ->latest();

        if ($request->filled('category')) {
            $query->where(
                'category_id',
                $request->category
            );
        }

        if ($request->filled('seller')) {
            $query->where(
                'user_id',
                $request->seller
            );
        }

        $products = $query->paginate(20);

        $categories = Category::all();

        return view(
            'admin.products',
            compact(
                'products',
                'categories'
            )
        );
    }
}