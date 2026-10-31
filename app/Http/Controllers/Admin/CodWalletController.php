<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogisticAdminRemittance;
use App\Services\CodCustodyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CodWalletController extends Controller
{
    public function index(CodCustodyService $service)
    {
        $wallet = $service->adminWallet();

        $pending = LogisticAdminRemittance::query()
            ->with('logistic')
            ->where('status', 'pending')
            ->latest()
            ->get();

        $remittances = LogisticAdminRemittance::query()
            ->with('logistic')
            ->latest()
            ->paginate(20);

        $transactions = $wallet->transactions()
            ->latest()
            ->take(30)
            ->get();

        $totalReceivedMinor = (int) $wallet->transactions()
            ->where('type', CodCustodyService::ADMIN_CREDIT)
            ->sum('amount_minor');

        $pendingIncomingMinor = (int) LogisticAdminRemittance::query()
            ->where('status', 'pending')
            ->sum('amount_minor');

        return view('admin.cod-wallet', compact(
            'wallet',
            'pending',
            'remittances',
            'transactions',
            'totalReceivedMinor',
            'pendingIncomingMinor'
        ));
    }

    public function confirm(
        LogisticAdminRemittance $remittance,
        CodCustodyService $service
    ) {
        $service->confirmAdminTransfer(
            $remittance,
            (int) Auth::id()
        );

        return back()->with(
            'success',
            'COD transfer confirmed and credited to Admin Wallet.'
        );
    }

    public function reject(
        Request $request,
        LogisticAdminRemittance $remittance,
        CodCustodyService $service
    ) {
        $validated = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $service->rejectAdminTransfer(
            $remittance,
            (int) Auth::id(),
            $validated['rejection_reason']
        );

        return back()->with(
            'success',
            'COD transfer rejected and returned to logistics custody.'
        );
    }
}
