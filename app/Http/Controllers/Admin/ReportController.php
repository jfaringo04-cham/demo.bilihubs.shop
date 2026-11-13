<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Build the selected report date range.
     */
    private function dateRange(Request $request): array
    {
        return [
            'from' => $request->filled('from')
                ? $request->from . ' 00:00:00'
                : null,

            'to' => $request->filled('to')
                ? $request->to . ' 23:59:59'
                : null,
        ];
    }

    /**
     * Apply the common Sales Report filters to a buyer Order query.
     */
    private function applySalesFilters(
        Builder $query,
        Request $request,
        ?string $from,
        ?string $to
    ): Builder {
        $sellerId = $request->integer('seller_id');
        $productId = $request->integer('product_id');

        return $query
            ->when(
                $from,
                fn (Builder $q) => $q->where('created_at', '>=', $from)
            )
            ->when(
                $to,
                fn (Builder $q) => $q->where('created_at', '<=', $to)
            )
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('status', $request->string('status')->toString())
            )
            ->when(
                $sellerId > 0,
                fn (Builder $q) => $q->whereHas(
                    'sellerOrders',
                    fn (Builder $sellerOrder) => $sellerOrder->where('seller_id', $sellerId)
                )
            )
            ->when(
                $productId > 0,
                fn (Builder $q) => $q->whereHas(
                    'items',
                    fn (Builder $item) => $item->where('product_id', $productId)
                )
            );
    }

    /**
     * Sales Report
     */
    public function sales(Request $request)
    {
        ['from' => $from, 'to' => $to] = $this->dateRange($request);

        $sellerId = $request->integer('seller_id');
        $productId = $request->integer('product_id');
        $sort = $request->input('sort', 'sales_desc');

        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */
        $ordersQuery = $this->applySalesFilters(
            Order::query()->with(['user', 'items.product', 'sellerOrders']),
            $request,
            $from,
            $to
        );

        match ($sort) {
            'sales_asc' => $ordersQuery->orderBy('total_minor')->latest('id'),
            'orders_desc' => $ordersQuery->latest('created_at'),
            default => $ordersQuery->orderByDesc('total_minor')->latest('id'),
        };

        $orders = $ordersQuery->get();

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */
        $deliveredOrders = $orders->whereIn('status', [
            'delivered',
            'completed',
        ]);

        /*
         * When filtering by seller/product, compute the sales amount from the
         * matching seller portion/item instead of counting the full multi-seller
         * buyer checkout total.
         */
        $salesBreakdown = [];

        if ($productId > 0) {
            $totalSalesMinor = OrderItem::query()
                ->where('product_id', $productId)
                ->when(
                    $sellerId > 0,
                    fn (Builder $q) => $q->whereHas(
                        'sellerOrder',
                        fn (Builder $so) => $so->where('seller_id', $sellerId)
                    )
                )
                ->whereHas('sellerOrder', function (Builder $sellerOrder) use ($request, $from, $to) {
                    $sellerOrder
                        ->where('status', 'delivered')
                        ->whereHas('order', function (Builder $order) use ($request, $from, $to) {
                            $this->applySalesFilters($order, $request, $from, $to);
                        });
                })
                ->sum('subtotal_minor');
            $salesBreakdown = ['Delivered item subtotals' => (int) $totalSalesMinor];
        } else {
            $salesOrders = $sellerId > 0
                ? $orders->flatMap(fn (Order $order) => $order->sellerOrders)
                    ->where('seller_id', $sellerId)
                    ->where('status', 'delivered')
                : $deliveredOrders;

            $totalSalesMinor = $salesOrders->sum(fn ($order) => (int) $order->total_minor);
            $salesBreakdown = [
                'Product subtotal' => $salesOrders->sum(fn ($order) => (int) $order->subtotal_minor),
                'Shipping fees' => $salesOrders->sum(fn ($order) => (int) $order->shipping_minor),
                'Service fees' => $salesOrders->sum(fn ($order) => (int) $order->service_fee_minor),
                'Discounts' => -$salesOrders->sum(fn ($order) => (int) $order->discount_minor),
            ];

            if ($sellerId <= 0) {
                $taxMinor = $salesOrders->sum(fn ($order) => (int) $order->tax_minor);
                if ($taxMinor !== 0) {
                    $salesBreakdown['Tax'] = $taxMinor;
                }
            }

            $differenceMinor = $totalSalesMinor - array_sum($salesBreakdown);
            if ($differenceMinor !== 0) {
                $salesBreakdown['Difference in stored totals'] = $differenceMinor;
            }
        }

        $totalSales = $totalSalesMinor / 100;
        $orderCount = $orders->count();
        $deliveredCount = $deliveredOrders->count();

        $byStatus = $orders
            ->groupBy('status')
            ->map
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Top Products
        |--------------------------------------------------------------------------
        */
        $topProductsQuery = OrderItem::query()
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity,
                SUM(subtotal_minor) as total_revenue_minor
            ')
            ->when(
                $productId > 0,
                fn (Builder $q) => $q->where('product_id', $productId)
            )
            ->whereHas('sellerOrder', function (Builder $query) use (
                $request,
                $from,
                $to,
                $sellerId
            ) {
                $query
                    ->where('status', 'delivered')
                    ->when(
                        $sellerId > 0,
                        fn (Builder $q) => $q->where('seller_id', $sellerId)
                    )
                    ->whereHas('order', function (Builder $orderQuery) use ($request, $from, $to) {
                        $this->applySalesFilters($orderQuery, $request, $from, $to);
                    });
            })
            ->with('product.seller')
            ->groupBy('product_id');

        match ($sort) {
            'sales_asc' => $topProductsQuery->orderBy('total_revenue_minor'),
            'orders_desc' => $topProductsQuery->orderByDesc('total_quantity'),
            default => $topProductsQuery->orderByDesc('total_revenue_minor'),
        };

        $topProducts = $topProductsQuery
            ->take(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Top Sellers
        |--------------------------------------------------------------------------
        */
        $sellerScope = function (Builder $query) use ($request, $from, $to, $productId) {
            $query
                ->where('status', 'delivered')
                ->when(
                    $productId > 0,
                    fn (Builder $q) => $q->whereHas(
                        'items',
                        fn (Builder $item) => $item->where('product_id', $productId)
                    )
                )
                ->whereHas('order', function (Builder $orderQuery) use ($request, $from, $to) {
                    $this->applySalesFilters($orderQuery, $request, $from, $to);
                });
        };

        $topSellersQuery = Seller::query()
            ->when(
                $sellerId > 0,
                fn (Builder $q) => $q->whereKey($sellerId)
            )
            ->whereHas('orders', $sellerScope)
            ->withCount([
                'orders as delivered_orders_count' => $sellerScope,
            ])
            ->withSum([
                'orders as delivered_sales_minor' => $sellerScope,
            ], 'total_minor');

        match ($sort) {
            'sales_asc' => $topSellersQuery->orderBy('delivered_sales_minor'),
            'orders_desc' => $topSellersQuery->orderByDesc('delivered_orders_count'),
            default => $topSellersQuery->orderByDesc('delivered_sales_minor'),
        };

        $topSellers = $topSellersQuery
            ->take(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Filter options
        |--------------------------------------------------------------------------
        */
        $sellers = Seller::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'seller_id']);

        $statuses = Order::query()
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('admin.reports-sales', compact(
            'orders',
            'totalSales',
            'salesBreakdown',
            'orderCount',
            'deliveredCount',
            'byStatus',
            'topProducts',
            'topSellers',
            'sellers',
            'products',
            'statuses',
            'from',
            'to'
        ));
    }

    /**
     * Commission Report
     */
    public function commission(Request $request)
    {
        app(CommissionController::class)->ensureCommissionsExist();
        ['from' => $from, 'to' => $to] = $this->dateRange($request);

        $commissions = Commission::query()
            ->with(['order', 'seller.seller'])
            ->when(
                $from,
                fn ($query) => $query->whereHas(
                    'order',
                    fn ($order) => $order->where('created_at', '>=', $from)
                )
            )
            ->when(
                $to,
                fn ($query) => $query->whereHas(
                    'order',
                    fn ($order) => $order->where('created_at', '<=', $to)
                )
            )
            ->latest()
            ->get();

        $totalCommission = $commissions->sum(
            fn ($commission) => (int) $commission->amount_minor
        ) / 100;

        $totalPaid = $commissions
            ->where('status', 'paid')
            ->sum(
                fn ($commission) => (int) $commission->amount_minor
            ) / 100;

        $totalPending = $commissions
            ->where('status', 'pending')
            ->sum(
                fn ($commission) => (int) $commission->amount_minor
            ) / 100;

        $bySeller = $commissions
            ->groupBy('seller_id')
            ->map(function ($group) {
                $seller = $group->first()->seller;

                $amountMinor = $group->sum(
                    fn ($commission) => (int) $commission->amount_minor
                );

                return [
                    'seller' => $seller,
                    'amount' => $amountMinor / 100,
                    'count' => $group->count(),
                ];
            });

        return view('admin.reports-commission', compact(
            'commissions',
            'totalCommission',
            'totalPaid',
            'totalPending',
            'bySeller',
            'from',
            'to'
        ));
    }

    /**
     * Export Sales CSV
     */
    public function exportSales(Request $request)
    {
        ['from' => $from, 'to' => $to] = $this->dateRange($request);

        $ordersQuery = $this->applySalesFilters(
            Order::query()->with('user'),
            $request,
            $from,
            $to
        );

        match ($request->input('sort', 'sales_desc')) {
            'sales_asc' => $ordersQuery->orderBy('total_minor')->latest('id'),
            'orders_desc' => $ordersQuery->latest('created_at'),
            default => $ordersQuery->orderByDesc('total_minor')->latest('id'),
        };

        $orders = $ordersQuery->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' =>
                'attachment; filename="sales-report-' .
                date('Y-m-d') .
                '.csv"',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Order #',
                'Customer',
                'Status',
                'Total',
                'Date',
            ]);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number,
                    $order->user->name ?? 'N/A',
                    str_replace('_', ' ', $order->status),
                    number_format(
                        ((int) $order->total_minor) / 100,
                        2,
                        '.',
                        ''
                    ),
                    $order->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    /**
     * Export Commission CSV
     */
    public function exportCommission(Request $request)
    {
        ['from' => $from, 'to' => $to] = $this->dateRange($request);

        $commissions = Commission::query()
            ->with(['order', 'seller.seller'])
            ->when(
                $from,
                fn ($query) => $query->whereHas(
                    'order',
                    fn ($order) => $order->where('created_at', '>=', $from)
                )
            )
            ->when(
                $to,
                fn ($query) => $query->whereHas(
                    'order',
                    fn ($order) => $order->where('created_at', '<=', $to)
                )
            )
            ->latest()
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' =>
                'attachment; filename="commission-report-' .
                date('Y-m-d') .
                '.csv"',
        ];

        $callback = function () use ($commissions) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Commission ID',
                'Order #',
                'Seller',
                'Order Total',
                'Rate',
                'Commission',
                'Status',
                'Date',
            ]);

            foreach ($commissions as $commission) {
                fputcsv($file, [
                    $commission->id,
                    $commission->order->order_number ?? 'N/A',
                    $commission->seller?->seller?->name
                        ?? $commission->seller?->name
                        ?? 'N/A',
                    number_format(
                        ((int) $commission->order_total_minor) / 100,
                        2,
                        '.',
                        ''
                    ),
                    $commission->rate,
                    number_format(
                        ((int) $commission->amount_minor) / 100,
                        2,
                        '.',
                        ''
                    ),
                    $commission->status,
                    $commission->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }
}
