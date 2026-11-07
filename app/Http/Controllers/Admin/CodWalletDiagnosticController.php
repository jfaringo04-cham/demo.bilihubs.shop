<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminWallet;
use App\Models\AdminWalletTransaction;
use App\Models\LogisticAdminRemittance;
use App\Services\CodCustodyService;
use Illuminate\Http\JsonResponse;

class CodWalletDiagnosticController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Read-only inspection. Never call firstOrCreate().
        $wallets = AdminWallet::query()
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn ($wallet) => [
                'wallet_id' => $wallet->id,
                'balance_minor' => (int) $wallet->available_balance_minor,
            ]);

        $ledgerSummary = AdminWalletTransaction::query()
            ->selectRaw('admin_wallet_id, type, COUNT(*) as count, SUM(amount_minor) as total_minor')
            ->groupBy('admin_wallet_id', 'type')
            ->orderBy('admin_wallet_id')
            ->get();

        $confirmed = LogisticAdminRemittance::query()
            ->where('status', 'confirmed')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($transfer) {
                $credits = AdminWalletTransaction::query()
                    ->where('logistic_admin_remittance_id', $transfer->id)
                    ->where('type', CodCustodyService::ADMIN_CREDIT)
                    ->get();

                return [
                    'transfer_id' => $transfer->id,
                    'reference' => $transfer->reference,
                    'status' => $transfer->status,
                    'amount_minor' => (int) $transfer->amount_minor,
                    'confirmed_at' => $transfer->confirmed_at,
                    'credit_count' => $credits->count(),
                    'credit_sum_minor' => (int) $credits->sum('amount_minor'),
                    'credit_wallet_ids' => $credits
                        ->pluck('admin_wallet_id')
                        ->unique()
                        ->values(),
                    'credit_matches_transfer' =>
                        $credits->count() === 1
                        && (int) $credits->sum('amount_minor')
                            === (int) $transfer->amount_minor,
                ];
            });

        return response()->json([
            'admin_wallets' => $wallets,
            'ledger_summary' => $ledgerSummary,
            'ledger_transaction_count' =>
                AdminWalletTransaction::query()->count(),
            'confirmed_transfers_checked' => $confirmed,
        ])->header('Cache-Control', 'no-store, private');
    }
}
