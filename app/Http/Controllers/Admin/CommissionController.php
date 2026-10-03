<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    protected float $defaultRate = 10;

    public function ensureCommissionsExist()
    {
        $completedOrders = Order::whereIn('status', [
    'delivered',
    'completed',
])
    ->with('items.product')
    ->get();

        foreach ($completedOrders as $order) {
            $bySeller = [];

            foreach ($order->items as $item) {
                if (!$item->product) {
                    continue;
                }

                $sellerId = $item->product->user_id;

                if (!$sellerId) {
                    continue;
                }

                // Integer centavos are the source of truth.
                $itemSubtotalMinor = (int) $item->subtotal_minor;

                $bySeller[$sellerId] = ($bySeller[$sellerId] ?? 0) + $itemSubtotalMinor;
            }

            foreach ($bySeller as $sellerId => $sellerTotalMinor) {
                $commissionAmountMinor = (int) round(
                    $sellerTotalMinor * ($this->defaultRate / 100)
                );

                Commission::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'seller_id' => $sellerId,
                    ],
                    [
                        // Legacy peso fields kept temporarily for UI compatibility.
                        'order_total_minor' => $sellerTotalMinor,

                        // Percentage, not money.
                        'rate' => $this->defaultRate,
                        'amount_minor' => $commissionAmountMinor,

                        'status' => 'pending',
                    ]
                );
            }
        }
    }

    public function index(Request $request)
    {
        $this->ensureCommissionsExist();

        $query = Commission::with(['order', 'seller.seller']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('seller')) {
            $query->where('seller_id', $request->seller);
        }

        $commissions = $query->latest()->paginate(20);

        // Sum integer centavos, then convert to pesos only for display.
        $totalEarned = Commission::sum('amount_minor') / 100;
        $totalPending = Commission::where('status', 'pending')->sum('amount_minor') / 100;
        $totalPaid = Commission::where('status', 'paid')->sum('amount_minor') / 100;

        $sellers = User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))
    ->with('seller')
    ->get();

        return view('admin.commissions', compact(
            'commissions',
            'sellers',
            'totalEarned',
            'totalPending',
            'totalPaid'
        ));
    }

    public function markPaid(Commission $commission)
    {
        $commission->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Commission marked as paid.');
    }
}


