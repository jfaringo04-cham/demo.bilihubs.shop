<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Services\RiderWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function index(RiderWalletService $walletService)
    {
        $riderUserId = (int) Auth::id();
        abort_unless($riderUserId > 0, 403);

        $wallet = $walletService->walletForRider($riderUserId);

        $transactions = $wallet->transactions()->latest()->paginate(20);
        $withdrawals = $wallet->withdrawals()->latest()->limit(10)->get();

        $totalEarnedMinor = (int) $wallet->transactions()
            ->whereIn('type', $walletService->earningTransactionTypes())
            ->sum('amount_minor');

        $totalWithdrawnMinor = (int) $wallet->withdrawals()
            ->where('status', 'paid')
            ->sum('amount_minor');

        $hasPendingWithdrawal = $wallet->withdrawals()
            ->where('status', 'pending')
            ->exists();

        $minimumWithdrawalMinor = (int) config('rider_wallet.minimum_withdrawal_minor', 10000);
        $pickupEarningMinor = (int) config('rider_wallet.pickup_earning_minor', 1500);
        $deliveryEarningMinor = (int) config('rider_wallet.delivery_earning_minor', 2500);

        return view('rider.wallet.index', compact(
            'wallet',
            'transactions',
            'withdrawals',
            'totalEarnedMinor',
            'totalWithdrawnMinor',
            'hasPendingWithdrawal',
            'minimumWithdrawalMinor',
            'pickupEarningMinor',
            'deliveryEarningMinor'
        ));
    }

    public function withdraw(Request $request, RiderWalletService $walletService)
    {
        $riderUserId = (int) Auth::id();
        abort_unless($riderUserId > 0, 403);

        $data = $request->validate([
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'payment_method' => ['required', 'in:gcash,maya,bank_transfer'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'rider_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['payment_method'] === 'bank_transfer' && empty($data['bank_name'])) {
            throw ValidationException::withMessages([
                'bank_name' => 'Bank name is required for bank transfer.',
            ]);
        }

        $walletService->requestWithdrawal(
            $riderUserId,
            $this->pesosToMinor($data['amount']),
            $data
        );

        return back()->with(
            'success',
            'Withdrawal request submitted. The amount is reserved while admin processes your payout.'
        );
    }

    private function pesosToMinor(string $value): int
    {
        $value = trim(str_replace(',', '', $value));

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a valid amount with up to 2 decimal places.',
            ]);
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $decimal = str_pad($decimal, 2, '0');

        return ((int) $whole * 100) + (int) substr($decimal, 0, 2);
    }
}
