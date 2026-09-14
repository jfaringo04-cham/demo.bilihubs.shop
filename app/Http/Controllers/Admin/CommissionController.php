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

    private function ensureCommissionsExist()
    {
        $delivered = Order::where('status', 'delivered')->with('items.product')->get();

        foreach ($delivered as $order) {
            $bySeller = [];

            foreach ($order->items as $item) {
                if (!$item->product) {
                    continue;
                }
                $sellerId = $item->product->user_id;
                if (!$sellerId) {
                    continue;
                }
                $bySeller[$sellerId] = ($bySeller[$sellerId] ?? 0) + (float) $item->subtotal;
            }

            foreach ($bySeller as $sellerId => $sellerTotal) {
                Commission::firstOrCreate(
                    ['order_id' => $order->id, 'seller_id' => $sellerId],
                    [
                        'order_total' => $sellerTotal,
                        'rate' => $this->defaultRate,
                        'amount' => $sellerTotal * ($this->defaultRate / 100),
                        'status' => 'pending',
                    ]
                );
            }
        }
    }

    public function index(Request $request)
    {
        $this->ensureCommissionsExist();

        $query = Commission::with(['order', 'seller']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('seller')) {
            $query->where('seller_id', $request->seller);
        }

        $commissions = $query->latest()->paginate(20);

        $totalEarned = Commission::sum('amount');
        $totalPending = Commission::where('status', 'pending')->sum('amount');
        $totalPaid = Commission::where('status', 'paid')->sum('amount');

        $sellers = User::where('role', 'seller')->get();

        return view('admin.commissions', compact(
            'commissions', 'sellers', 'totalEarned', 'totalPending', 'totalPaid'
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
