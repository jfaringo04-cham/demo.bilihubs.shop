<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
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
     * Sales Report
     */
    public function sales(Request $request)
    {
        ['from' => $from, 'to' => $to] = $this->dateRange($request);

        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        | These are buyer checkout orders.
        */
        $orders = Order::query()
            ->with(['user', 'items.product'])
            ->when(
                $from,
                fn ($query) => $query->where('created_at', '>=', $from)
            )
            ->when(
                $to,
                fn ($query) => $query->where('created_at', '<=', $to)
            )
            ->latest()
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Sales Summary
        |--------------------------------------------------------------------------
        | Delivered and completed orders count toward completed sales.
        | Monetary values remain integer centavos until display.
        */
        $deliveredOrders = $orders->whereIn('status', [
    'delivered',
    'completed',
]);

        $totalSales = $deliveredOrders->sum(
            fn ($order) => (int) $order->total_minor
        ) / 100;

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
        | Count only items belonging to delivered seller orders.
        | Date range follows the parent buyer order.
        */
        $topProducts = OrderItem::query()
            ->selectRaw('
                product_id,
                SUM(quantity) as total_quantity,
                SUM(subtotal_minor) as total_revenue_minor
            ')
            ->whereHas('sellerOrder', function ($query) use ($from, $to) {
                $query->where('status', 'delivered')
                    ->whereHas('order', function ($orderQuery) use ($from, $to) {
                        $orderQuery
                            ->when(
                                $from,
                                fn ($q) => $q->where('created_at', '>=', $from)
                            )
                            ->when(
                                $to,
                                fn ($q) => $q->where('created_at', '<=', $to)
                            );
                    });
            })
            ->with('product.seller')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->take(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Top Sellers
        |--------------------------------------------------------------------------
        | seller_orders.seller_id references sellers.id.
        |
        | Rank sellers using delivered seller orders, not User::orders().
        */
        $topSellers = Seller::query()
            ->whereHas('orders', function ($query) use ($from, $to) {
                $query->where('status', 'delivered')
                    ->whereHas('order', function ($orderQuery) use ($from, $to) {
                        $orderQuery
                            ->when(
                                $from,
                                fn ($q) => $q->where('created_at', '>=', $from)
                            )
                            ->when(
                                $to,
                                fn ($q) => $q->where('created_at', '<=', $to)
                            );
                    });
            })
            ->withCount([
                'orders as delivered_orders_count' => function ($query) use ($from, $to) {
                    $query->where('status', 'delivered')
                        ->whereHas('order', function ($orderQuery) use ($from, $to) {
                            $orderQuery
                                ->when(
                                    $from,
                                    fn ($q) => $q->where('created_at', '>=', $from)
                                )
                                ->when(
                                    $to,
                                    fn ($q) => $q->where('created_at', '<=', $to)
                                );
                        });
                },
            ])
            ->withSum([
                'orders as delivered_sales_minor' => function ($query) use ($from, $to) {
                    $query->where('status', 'delivered')
                        ->whereHas('order', function ($orderQuery) use ($from, $to) {
                            $orderQuery
                                ->when(
                                    $from,
                                    fn ($q) => $q->where('created_at', '>=', $from)
                                )
                                ->when(
                                    $to,
                                    fn ($q) => $q->where('created_at', '<=', $to)
                                );
                        });
                },
            ], 'total_minor')
            ->orderByDesc('delivered_sales_minor')
            ->take(10)
            ->get();

        return view('admin.reports-sales', compact(
            'orders',
            'totalSales',
            'orderCount',
            'deliveredCount',
            'byStatus',
            'topProducts',
            'topSellers',
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

        /*
        |--------------------------------------------------------------------------
        | Commission Summary
        |--------------------------------------------------------------------------
        */
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

        $orders = Order::query()
            ->with('user')
            ->when(
                $from,
                fn ($query) => $query->where('created_at', '>=', $from)
            )
            ->when(
                $to,
                fn ($query) => $query->where('created_at', '<=', $to)
            )
            ->latest()
            ->get();

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
                    $order->status,
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