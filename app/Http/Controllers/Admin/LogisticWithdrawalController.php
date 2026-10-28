<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogisticWithdrawal;
use App\Services\LogisticWalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LogisticWithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $allowed = ['all', 'pending', 'paid', 'rejected'];

        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $query = LogisticWithdrawal::query()
            ->with(['logistic.owner', 'wallet'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $withdrawals = $query->paginate(20)->withQueryString();

        $stats = [
            'pending_count' => LogisticWithdrawal::where('status', 'pending')->count(),
            'pending_minor' => (int) LogisticWithdrawal::where('status', 'pending')->sum('amount_minor'),
            'paid_minor' => (int) LogisticWithdrawal::where('status', 'paid')->sum('amount_minor'),
        ];

        return view(
            'admin.logistic-withdrawals.index',
            compact('withdrawals', 'status', 'stats')
        );
    }

    public function markPaid(
        Request $request,
        LogisticWithdrawal $withdrawal,
        LogisticWalletService $walletService
    ) {
        $data = $request->validate([
            'transaction_reference' => [
                'required',
                'string',
                'max:200',
                Rule::unique('logistic_withdrawals', 'transaction_reference')
                    ->ignore($withdrawal->id),
            ],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'payout_verified' => ['required', 'accepted'],
        ]);

        $walletService->markWithdrawalPaid(
            $withdrawal,
            trim($data['transaction_reference']),
            $data['admin_notes'] ?? null,
            auth()->id()
        );

        return back()->with('success', 'Logistics withdrawal marked as paid.');
    }

    public function reject(
        Request $request,
        LogisticWithdrawal $withdrawal,
        LogisticWalletService $walletService
    ) {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:2000'],
        ]);

        $walletService->rejectWithdrawal(
            $withdrawal,
            $data['admin_notes'],
            auth()->id()
        );

        return back()->with(
            'success',
            'Logistics withdrawal rejected and funds returned to the wallet.'
        );
    }
}
