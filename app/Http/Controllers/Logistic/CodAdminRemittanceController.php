<?php

namespace App\Http\Controllers\Logistic;

use App\Http\Controllers\Controller;
use App\Models\LogisticAdminRemittance;
use App\Services\CodCustodyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CodAdminRemittanceController extends Controller
{
    public function index(CodCustodyService $service)
    {
        $logistic = Auth::user()?->ownedLogistic;
        abort_unless($logistic, 403);

        $wallet = $service->walletForLogistic((int) $logistic->id);

        $remittances = LogisticAdminRemittance::query()
            ->where('logistic_id', $logistic->id)
            ->latest()
            ->paginate(20);

        $transactions = $wallet->transactions()
            ->latest()
            ->take(20)
            ->get();

        return view('logistic.cod-admin-remittances', compact(
            'logistic',
            'wallet',
            'remittances',
            'transactions'
        ));
    }

    public function store(
        Request $request,
        CodCustodyService $service
    ) {
        $logistic = Auth::user()?->ownedLogistic;
        abort_unless($logistic, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transfer_method' => [
                'required',
                'in:cash,bank_transfer,gcash,maya,other',
            ],
            'transfer_reference' => [
                'nullable',
                'string',
                'max:150',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->submitToAdmin(
            (int) $logistic->id,
            (int) Auth::id(),
            (int) round(((float) $validated['amount']) * 100),
            $validated
        );

        return back()->with(
            'success',
            'COD transfer submitted to Admin for confirmation.'
        );
    }
}
