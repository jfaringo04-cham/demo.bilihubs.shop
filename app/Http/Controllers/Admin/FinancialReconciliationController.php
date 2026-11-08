<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Commission;
use App\Models\CodRemittance;
use App\Models\Order;
use App\Models\PlatformRevenueEntry;
use Illuminate\Support\Facades\DB;

class FinancialReconciliationController extends Controller
{
    public function index()
    {
        // Payment totals: count each payment once.
        $totalCollectedMinor = (int) Payment::query()
            ->where('status', 'paid')
            ->sum('amount_minor');

        // Count only confirmed COD remittance allocations.
        $confirmedPaymentIds = DB::table('cod_remittance_payments')
            ->join(
                'cod_remittances',
                'cod_remittances.id',
                '=',
                'cod_remittance_payments.cod_remittance_id'
            )
            ->where('cod_remittances.status', 'confirmed')
            ->distinct()
            ->pluck('cod_remittance_payments.payment_id');

        $confirmedCodMinor = (int) Payment::query()
            ->where('status', 'paid')
            ->whereIn('id', $confirmedPaymentIds)
            ->sum('amount_minor');

        // Existing commission records.
        $totalCommissionMinor = (int) Commission::sum('amount_minor');

        $pendingCommissionMinor = (int) Commission::query()
            ->where('status', 'pending')
            ->sum('amount_minor');

        $paidCommissionMinor = (int) Commission::query()
            ->where('status', 'paid')
            ->sum('amount_minor');

        /*
        |--------------------------------------------------------------------------
        | PLATFORM REVENUE LEDGER
        |--------------------------------------------------------------------------
        |
        | Only PHP entries are included in these totals.
        | Earned entries increase revenue.
        | Reversal entries reduce revenue.
        | No historical records are generated here.
        |
        */

        $ledgerTotals = PlatformRevenueEntry::query()
            ->where('currency', 'PHP')
            ->whereIn('revenue_type', [
                'service_fee',
                'seller_commission',
            ])
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN revenue_type = 'service_fee'
                        AND entry_type = 'earned'
                        THEN amount_minor ELSE 0
                    END
                ), 0) AS service_fee_earned_minor,

                COALESCE(SUM(
                    CASE
                        WHEN revenue_type = 'service_fee'
                        AND entry_type = 'reversal'
                        THEN amount_minor ELSE 0
                    END
                ), 0) AS service_fee_reversed_minor,

                COALESCE(SUM(
                    CASE
                        WHEN revenue_type = 'seller_commission'
                        AND entry_type = 'earned'
                        THEN amount_minor ELSE 0
                    END
                ), 0) AS commission_earned_minor,

                COALESCE(SUM(
                    CASE
                        WHEN revenue_type = 'seller_commission'
                        AND entry_type = 'reversal'
                        THEN amount_minor ELSE 0
                    END
                ), 0) AS commission_reversed_minor
            ")
            ->first();

        $serviceFeeEarnedMinor =
            (int) $ledgerTotals->service_fee_earned_minor;

        $serviceFeeReversedMinor =
            (int) $ledgerTotals->service_fee_reversed_minor;

        $commissionEarnedMinor =
            (int) $ledgerTotals->commission_earned_minor;

        $commissionReversedMinor =
            (int) $ledgerTotals->commission_reversed_minor;

        $serviceFeeNetMinor =
            $serviceFeeEarnedMinor - $serviceFeeReversedMinor;

        $commissionNetMinor =
            $commissionEarnedMinor - $commissionReversedMinor;

        $totalPlatformRevenueMinor =
            $serviceFeeNetMinor + $commissionNetMinor;

        $recentRemittances = CodRemittance::query()
            ->with(['rider', 'logistic'])
            ->latest()
            ->limit(15)
            ->get();

        $recentPayments = Payment::query()
            ->with(['order', 'shipment'])
            ->latest()
            ->limit(20)
            ->get();

        // Read-only order-level financial breakdown.
        $recentOrders = Order::query()
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();

        // Commission totals grouped by order.
        $commissionsByOrder = Commission::query()
            ->whereIn('order_id', $recentOrders->pluck('id'))
            ->selectRaw(
                'order_id, SUM(amount_minor) AS commission_total_minor'
            )
            ->groupBy('order_id')
            ->pluck('commission_total_minor', 'order_id');

        return view('admin.financial-reconciliation', compact(
            'totalCollectedMinor',
            'confirmedCodMinor',
            'totalCommissionMinor',
            'pendingCommissionMinor',
            'paidCommissionMinor',
            'serviceFeeEarnedMinor',
            'serviceFeeReversedMinor',
            'commissionEarnedMinor',
            'commissionReversedMinor',
            'serviceFeeNetMinor',
            'commissionNetMinor',
            'totalPlatformRevenueMinor',
            'recentRemittances',
            'recentPayments',
            'recentOrders',
            'commissionsByOrder'
        ));
    }
}
