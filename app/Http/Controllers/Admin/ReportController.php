<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Commission;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    private function dateRange(Request $request)
    {
        return [
            'from' => $request->filled('from') ? $request->from . ' 00:00:00' : null,
            'to' => $request->filled('to') ? $request->to . ' 23:59:59' : null,
        ];
    }

    public function sales(Request $request)
    {
        [$from, $to] = array_values($this->dateRange($request));

        $orders = Order::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->with('items.product')
            ->get();

        // Integer centavos are the source of truth.
        $totalSales = $orders->where('status', 'delivered')->sum(function ($order) {
            return (int) $order->total_minor;
        }) / 100;

        $orderCount = $orders->count();
        $deliveredCount = $orders->where('status', 'delivered')->count();

        $byStatus = $orders->groupBy('status')->map->count();

        $topProducts = Product::with('seller')
            ->withSum('orderItems', 'quantity')
            ->orderByDesc('order_items_sum_quantity')
            ->take(10)
            ->get();

        $topSellers = User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))
            ->withCount(['orders as delivered_orders_count' => function ($q) {
                $q->where('status', 'delivered');
            }])
            ->get()
            ->sortByDesc('delivered_orders_count')
            ->take(10);

        return view('admin.reports-sales', compact(
            'orders', 'totalSales', 'orderCount', 'deliveredCount', 'byStatus',
            'topProducts', 'topSellers', 'from', 'to'
        ));
    }

    public function commission(Request $request)
    {
        [$from, $to] = array_values($this->dateRange($request));

        $commissions = Commission::query()
            ->with(['order', 'seller'])
            ->when($from, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('created_at', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('created_at', '<=', $to)))
            ->get();

        // Sum integer centavos, convert to pesos only for display.
        $totalCommission = $commissions->sum(fn ($commission) => (int) $commission->amount_minor) / 100;

        $totalPaid = $commissions
            ->where('status', 'paid')
            ->sum(fn ($commission) => (int) $commission->amount_minor) / 100;

        $totalPending = $commissions
            ->where('status', 'pending')
            ->sum(fn ($commission) => (int) $commission->amount_minor) / 100;

        $bySeller = $commissions->groupBy('seller_id')->map(function ($group) {
            $seller = $group->first()->seller;

            $amountMinor = $group->sum(fn ($commission) => (int) $commission->amount_minor);

            return [
                'seller' => $seller,
                'amount' => $amountMinor / 100,
                'count' => $group->count(),
            ];
        });

        return view('admin.reports-commission', compact(
            'commissions', 'totalCommission', 'totalPaid', 'totalPending', 'bySeller', 'from', 'to'
        ));
    }

    public function exportSales(Request $request)
    {
        [$from, $to] = array_values($this->dateRange($request));

        $orders = Order::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales-report-' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Order #', 'Customer', 'Status', 'Total', 'Date']);

            foreach ($orders as $order) {
                $totalMinor = (int) $order->total_minor;

                fputcsv($file, [
                    $order->order_number,
                    $order->user->name ?? 'N/A',
                    $order->status,
                    number_format($totalMinor / 100, 2, '.', ''),
                    $order->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportCommission(Request $request)
    {
        [$from, $to] = array_values($this->dateRange($request));

        $commissions = Commission::query()
            ->with(['order', 'seller'])
            ->when($from, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('created_at', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('created_at', '<=', $to)))
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="commission-report-' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($commissions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Commission ID', 'Order #', 'Seller', 'Order Total', 'Rate', 'Commission', 'Status', 'Date']);

            foreach ($commissions as $c) {
                $orderTotalMinor = (int) $c->order_total_minor;

                $amountMinor = (int) $c->amount_minor;

                fputcsv($file, [
                    $c->id,
                    $c->order->order_number ?? 'N/A',
                    $c->seller->name ?? 'N/A',
                    number_format($orderTotalMinor / 100, 2, '.', ''),
                    $c->rate,
                    number_format($amountMinor / 100, 2, '.', ''),
                    $c->status,
                    $c->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

