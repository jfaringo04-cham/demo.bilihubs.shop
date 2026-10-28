<?php

namespace App\Http\Controllers\Logistic;

use App\Http\Controllers\Controller;
use App\Services\LogisticWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function index(LogisticWalletService $walletService)
    {
        $logistic = Auth::user()?->ownedLogistic;
        abort_unless($logistic, 403);

        $wallet = $walletService->walletForLogistic((int) $logistic->id);
        $transactions = $wallet->transactions()
            ->with('shipment')
            ->latest()
            ->paginate(20, ['*'], 'transactions_page');

        $withdrawals = $wallet->withdrawals()
            ->latest()
            ->take(10)
            ->get();

        $totalEarnedMinor = (int) $wallet->transactions()
            ->where('type', LogisticWalletService::TYPE_SHIPMENT_CREDIT)
            ->sum('amount_minor');

        $todayEarnedMinor = (int) $wallet->transactions()
            ->where('type', LogisticWalletService::TYPE_SHIPMENT_CREDIT)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount_minor');

        $totalWithdrawnMinor = (int) $wallet->withdrawals()
            ->where('status', 'paid')
            ->sum('amount_minor');

        $hasPendingWithdrawal = $wallet->withdrawals()
            ->where('status', 'pending')
            ->exists();

        $shipmentCreditMinor = (int) config(
            'rider_wallet.logistics_earning_minor',
            1000
        );

        $minimumWithdrawalMinor = (int) config(
            'logistic_wallet.minimum_withdrawal_minor',
            10000
        );

        return view('logistic.wallet.index', compact(
            'logistic',
            'wallet',
            'transactions',
            'withdrawals',
            'totalEarnedMinor',
            'todayEarnedMinor',
            'totalWithdrawnMinor',
            'hasPendingWithdrawal',
            'shipmentCreditMinor',
            'minimumWithdrawalMinor'
        ));
    }

    public function withdraw(Request $request, LogisticWalletService $walletService)
    {
        $logistic = Auth::user()?->ownedLogistic;
        abort_unless($logistic, 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:gcash,maya,bank_transfer'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'logistic_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amountMinor = (int) round(((float) $data['amount']) * 100);

        $walletService->requestWithdrawal(
            (int) $logistic->id,
            $amountMinor,
            $data
        );

        return back()->with('success', 'Logistics withdrawal request submitted.');
    }
}
