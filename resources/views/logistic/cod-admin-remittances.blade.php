@extends('layouts.logistic', ['title' => 'COD to Admin', 'logistic' => $logistic])

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">COD Transfer to Admin</h1>
            <p class="text-muted mb-0">
                Forward confirmed rider COD cash to the BiliHub Admin Wallet.
            </p>
        </div>
        <span class="badge text-bg-light border px-3 py-2">
            Separate from logistics shipping earnings
        </span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Available COD Custody</div>
                    <div class="fs-3 fw-bold text-success">
                        ₱{{ number_format($wallet->available_balance_minor / 100, 2) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Pending Admin Confirmation</div>
                    <div class="fs-3 fw-bold text-warning">
                        ₱{{ number_format($wallet->pending_balance_minor / 100, 2) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total COD in Custody</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format(
                            ($wallet->available_balance_minor + $wallet->pending_balance_minor) / 100,
                            2
                        ) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Submit COD to Admin</h2>
                    <form method="POST" action="{{ route('logistic.cod-admin-remittances.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Amount (₱)</label>
                            <input
                                type="number"
                                name="amount"
                                min="0.01"
                                step="0.01"
                                max="{{ $wallet->available_balance_minor / 100 }}"
                                value="{{ old('amount') }}"
                                class="form-control"
                                required>
                            <div class="form-text">
                                Available: ₱{{ number_format($wallet->available_balance_minor / 100, 2) }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transfer Method</label>
                            <select name="transfer_method" class="form-select" required>
                                <option value="">Select method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transfer Reference</label>
                            <input
                                name="transfer_reference"
                                value="{{ old('transfer_reference') }}"
                                maxlength="150"
                                class="form-control"
                                placeholder="Receipt / transaction reference">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea
                                name="notes"
                                rows="3"
                                maxlength="1000"
                                class="form-control">{{ old('notes') }}</textarea>
                        </div>

                        <button
                            class="btn btn-primary w-100"
                            @disabled($wallet->available_balance_minor <= 0)>
                            Submit Transfer for Admin Confirmation
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Transfer History</h2>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($remittances as $remittance)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-break">
                                            {{ $remittance->reference }}
                                        </div>
                                        @if($remittance->transfer_reference)
                                            <small class="text-muted">
                                                {{ $remittance->transfer_reference }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="fw-semibold">
                                        ₱{{ number_format($remittance->amount_minor / 100, 2) }}
                                    </td>
                                    <td>{{ strtoupper(str_replace('_', ' ', $remittance->transfer_method ?? '—')) }}</td>
                                    <td>
                                        <span class="badge {{ $remittance->status === 'confirmed' ? 'text-bg-success' : ($remittance->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">
                                            {{ ucfirst($remittance->status) }}
                                        </span>
                                        @if($remittance->rejection_reason)
                                            <div class="small text-danger mt-1">
                                                {{ $remittance->rejection_reason }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        {{ $remittance->submitted_at?->format('M d, Y h:i A') ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No transfers to Admin yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($remittances->hasPages())
                    <div class="card-footer bg-white">
                        {{ $remittances->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">COD Custody Ledger</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th class="text-end">Movement</th>
                        <th class="text-end">Custody After</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->created_at?->format('M d, Y h:i A') }}</td>
                            <td>{{ $transaction->reference ?: '—' }}</td>
                            <td>{{ $transaction->description }}</td>
                            <td class="text-end fw-semibold">
                                {{ $transaction->amount_minor >= 0 ? '+' : '-' }}₱{{ number_format(abs($transaction->amount_minor) / 100, 2) }}
                            </td>
                            <td class="text-end">
                                ₱{{ number_format($transaction->balance_after_minor / 100, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No COD custody transactions yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
