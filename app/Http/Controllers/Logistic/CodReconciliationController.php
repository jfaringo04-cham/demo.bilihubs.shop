<?php

namespace App\Http\Controllers\Logistic;

use App\Http\Controllers\Controller;
use App\Models\Logistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CodReconciliationController extends Controller
{
    /**
     * Display the COD financial reconciliation summary
     * and individual rider cash balances.
     */
    public function index(Request $request)
    {
        $logistic = Logistic::query()
            ->where('owner_user_id', $request->user()->id)
            ->firstOrFail();

        // Only pending and confirmed remittances are active.
        // Rejected remittances are excluded to avoid double-counting.
        $activeRemittances = DB::table('cod_remittance_payments as cri')
            ->join('cod_remittances as cr', 'cr.id', '=', 'cri.cod_remittance_id')
            ->where('cr.logistic_id', $logistic->id)
            ->whereIn('cr.status', ['pending', 'confirmed'])
            ->select('cri.payment_id')
            ->selectRaw("
                MAX(
                    CASE
                        WHEN cr.status = 'confirmed' THEN 2
                        ELSE 1
                    END
                ) as status_rank
            ")
            ->groupBy('cri.payment_id');

        $balances = DB::table('payments as p')
            ->join('shipments as s', 's.id', '=', 'p.shipment_id')
            ->join('users as u', 'u.id', '=', 'p.collected_by')
            ->leftJoinSub($activeRemittances, 'active', function ($join) {
                $join->on('active.payment_id', '=', 'p.id');
            })
            ->where('s.logistic_id', $logistic->id)
            ->whereColumn('s.rider_id', 'p.collected_by')
            ->where('s.status', 'delivered')
            ->where('p.method', 'cod')
            ->where('p.status', 'paid')
            ->whereNotNull('p.paid_at')
            ->where('p.amount_minor', '>', 0)
            ->groupBy('u.id', 'u.name')
            ->select(
                'u.id as rider_id',
                'u.name as rider_name'
            )
            ->selectRaw('COUNT(p.id) as payment_count')
            ->selectRaw('COALESCE(SUM(p.amount_minor), 0) as collected_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN active.status_rank IS NULL THEN p.amount_minor ELSE 0 END), 0) as unsubmitted_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN active.status_rank = 1 THEN p.amount_minor ELSE 0 END), 0) as pending_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN active.status_rank = 2 THEN p.amount_minor ELSE 0 END), 0) as confirmed_minor')
            ->orderBy('u.name')
            ->get();

        // Overdue COD is unsubmitted cash past 24 hours.
        // Pending/confirmed remittances are excluded.
        // Rejected remittances remain eligible for monitoring.
        $overdueRows = DB::table('payments as p')
            ->join('shipments as s', 's.id', '=', 'p.shipment_id')
            ->where('s.logistic_id', $logistic->id)
            ->whereColumn('s.rider_id', 'p.collected_by')
            ->where('s.status', 'delivered')
            ->where('p.method', 'cod')
            ->where('p.status', 'paid')
            ->whereNotNull('p.paid_at')
            ->where('p.paid_at', '<=', now()->subHours(
                \App\Services\CodRemittanceDeadlineService::DEADLINE_HOURS
            ))
            ->where('p.amount_minor', '>', 0)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('cod_remittance_payments as cri')
                    ->join(
                        'cod_remittances as cr',
                        'cr.id',
                        '=',
                        'cri.cod_remittance_id'
                    )
                    ->whereColumn('cri.payment_id', 'p.id')
                    ->whereIn('cr.status', [
                        'pending',
                        'confirmed',
                    ]);
            })
            ->groupBy('p.collected_by')
            ->select('p.collected_by')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('SUM(p.amount_minor) as total_minor')
            ->get();

        $overdueStats = [
            'rider_count' => $overdueRows->count(),
            'payment_count' => (int) $overdueRows->sum('payment_count'),
            'total_minor' => (int) $overdueRows->sum('total_minor'),
        ];

        $totals = [
            'collected_minor' => $balances->sum('collected_minor'),
            'unsubmitted_minor' => $balances->sum('unsubmitted_minor'),
            'pending_minor' => $balances->sum('pending_minor'),
            'confirmed_minor' => $balances->sum('confirmed_minor'),
        ];

        return view(
            'logistic.cod-reconciliation',
            compact('logistic', 'balances', 'totals', 'overdueStats')
        );
    }

    /**
     * Display individual COD payments for a selected rider.
     *
     * Only payments associated with the authenticated
     * logistics owner's company can be accessed.
     */
    public function showRider(Request $request, int $rider)
    {
        $logistic = Logistic::query()
            ->where('owner_user_id', $request->user()->id)
            ->firstOrFail();

        // Determine the active remittance status per payment.
        $activeRemittances = DB::table('cod_remittance_payments as cri')
            ->join('cod_remittances as cr', 'cr.id', '=', 'cri.cod_remittance_id')
            ->where('cr.logistic_id', $logistic->id)
            ->whereIn('cr.status', ['pending', 'confirmed'])
            ->select('cri.payment_id')
            ->selectRaw("
                MAX(
                    CASE
                        WHEN cr.status = 'confirmed' THEN 2
                        ELSE 1
                    END
                ) as status_rank
            ")
            ->groupBy('cri.payment_id');

        // Apply the same payment eligibility rules as index().
        $baseQuery = DB::table('payments as p')
            ->join('shipments as s', 's.id', '=', 'p.shipment_id')
            ->join('users as u', 'u.id', '=', 'p.collected_by')
            ->leftJoinSub($activeRemittances, 'active', function ($join) {
                $join->on('active.payment_id', '=', 'p.id');
            })
            ->where('s.logistic_id', $logistic->id)
            ->where('s.rider_id', $rider)
            ->where('p.collected_by', $rider)
            ->where('s.status', 'delivered')
            ->where('p.method', 'cod')
            ->where('p.status', 'paid')
            ->whereNotNull('p.paid_at')
            ->where('p.amount_minor', '>', 0);

        // Verify that this rider has eligible COD payments
        // belonging to the authenticated logistics company.
        $riderInfo = (clone $baseQuery)
            ->select('u.id', 'u.name')
            ->first();

        abort_unless($riderInfo, 404);

        // Calculate the selected rider's COD balances.
        $totals = (clone $baseQuery)
            ->selectRaw('COUNT(p.id) as payment_count')
            ->selectRaw('COALESCE(SUM(p.amount_minor), 0) as collected_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN active.status_rank IS NULL THEN p.amount_minor ELSE 0 END), 0) as unsubmitted_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN active.status_rank = 1 THEN p.amount_minor ELSE 0 END), 0) as pending_minor')
            ->selectRaw('COALESCE(SUM(CASE WHEN active.status_rank = 2 THEN p.amount_minor ELSE 0 END), 0) as confirmed_minor')
            ->first();

        // Supported payment filters.
        $filter = $request->query('status', 'all');

        if (!in_array(
            $filter,
            ['all', 'unsubmitted', 'pending', 'confirmed'],
            true
        )) {
            $filter = 'all';
        }

        // Retrieve the individual COD payment records.
        $paymentsQuery = (clone $baseQuery)
            ->select([
                'p.id as payment_id',
                'p.shipment_id',
                'p.amount_minor',
                'p.paid_at',
                'active.status_rank',
            ]);

        // Apply the selected status filter.
        if ($filter === 'unsubmitted') {
            $paymentsQuery->whereNull('active.status_rank');
        } elseif ($filter === 'pending') {
            $paymentsQuery->where('active.status_rank', 1);
        } elseif ($filter === 'confirmed') {
            $paymentsQuery->where('active.status_rank', 2);
        }

        // Latest collected payments first.
        $payments = $paymentsQuery
            ->orderByDesc('p.paid_at')
            ->orderByDesc('p.id')
            ->paginate(15)
            ->withQueryString();

        return view(
            'logistic.cod-rider-details',
            compact(
                'logistic',
                'riderInfo',
                'totals',
                'payments',
                'filter'
            )
        );
    }
}
