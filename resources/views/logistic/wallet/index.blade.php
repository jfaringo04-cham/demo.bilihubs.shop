@extends('layouts.logistic', ['title' => 'Wallet', 'logistic' => $logistic])

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Logistics Wallet</h1>
            <p class="text-muted mb-0">
                Shipping-fee earnings released after the buyer confirms receipt.
            </p>
        </div>
        <span class="badge text-bg-light border px-3 py-2">
            ₱{{ number_format($shipmentCreditMinor / 100, 2) }} per standard ₱50 shipment
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Available Balance</div>
                    <div class="display-6 fw-bold">₱{{ number_format($wallet->available_balance_minor / 100, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Total Earned</div>
                    <div class="display-6 fw-bold">₱{{ number_format($totalEarnedMinor / 100, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Earned Today</div>
                    <div class="display-6 fw-bold">₱{{ number_format($todayEarnedMinor / 100, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Wallet Transactions</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Tracking</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->created_at?->format('M d, Y h:i A') }}</td>
                            <td>{{ $transaction->reference ?: '—' }}</td>
                            <td>{{ $transaction->shipment?->tracking_number ?: '—' }}</td>
                            <td>{{ $transaction->description ?: 'Logistics shipment earning' }}</td>
                            <td class="text-end fw-semibold text-success">
                                +₱{{ number_format($transaction->amount_minor / 100, 2) }}
                            </td>
                            <td class="text-end">
                                ₱{{ number_format($transaction->balance_after_minor / 100, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                No logistics earnings yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
            <div class="card-footer bg-white">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

    <div class="alert alert-info mt-4 mb-0">
        Standard ₱50 shipping split: ₱15 pickup rider, ₱25 final rider, and ₱10 logistics.
        The combined payout never exceeds the shipment's actual shipping fee.
    </div>
</div>
@endsection
