<?php

namespace App\Http\Controllers\Logistic;

use App\Http\Controllers\Controller;
use App\Services\LogisticWalletService;
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
            ->paginate(20);

        $totalEarnedMinor = (int) $wallet->transactions()
            ->where('type', LogisticWalletService::TYPE_SHIPMENT_CREDIT)
            ->sum('amount_minor');

        $todayEarnedMinor = (int) $wallet->transactions()
            ->where('type', LogisticWalletService::TYPE_SHIPMENT_CREDIT)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount_minor');

        $shipmentCreditMinor = (int) config(
            'rider_wallet.logistics_earning_minor',
            1000
        );

        return view('logistic.wallet.index', compact(
            'logistic',
            'wallet',
            'transactions',
            'totalEarnedMinor',
            'todayEarnedMinor',
            'shipmentCreditMinor'
        ));
    }
}
