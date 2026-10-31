@extends('admin.layout')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Admin COD Wallet</h1>
            <p class="text-muted mb-0">
                Platform custody for COD cash confirmed from logistics companies.
            </p>
        </div>
        <span class="badge text-bg-light border px-3 py-2">
            COD Custody · not platform profit
        </span>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

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
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Admin Wallet Balance</div>
                    <div class="fs-3 fw-bold text-success">
                        ₱{{ number_format($wallet->available_balance_minor / 100, 2) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Pending Incoming COD</div>
                    <div class="fs-3 fw-bold text-warning">
                        ₱{{ number_format($pendingIncomingMinor / 100, 2) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total COD Confirmed Received</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format($totalReceivedMinor / 100, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Pending Logistics Transfers</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Logistics</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Proof / Reference</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pending as $remittance)
                        <tr>
                            <td class="fw-semibold">{{ $remittance->reference }}</td>
                            <td>{{ $remittance->logistic?->company_name ?? ('Logistics #'.$remittance->logistic_id) }}</td>
                            <td class="fw-bold">
                                ₱{{ number_format($remittance->amount_minor / 100, 2) }}
                            </td>
                            <td>{{ strtoupper(str_replace('_', ' ', $remittance->transfer_method ?? '—')) }}</td>
                            <td>{{ $remittance->transfer_reference ?: '—' }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                    <form method="POST" action="{{ route('admin.cod-wallet.confirm', $remittance) }}">
                                        @csrf
                                        <button
                                            class="btn btn-success btn-sm"
                                            onclick="return confirm('Confirm that Admin actually received this COD cash?')">
                                            Confirm Received
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.cod-wallet.reject', $remittance) }}">
                                        @csrf
                                        <input
                                            type="text"
                                            name="rejection_reason"
                                            class="form-control form-control-sm mb-1"
                                            placeholder="Reason"
                                            maxlength="1000"
                                            required>
                                        <button class="btn btn-outline-danger btn-sm w-100">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No pending COD transfers.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Admin Wallet Ledger</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
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
                            <td>{{ $transaction->description }}</td>
                            <td class="text-end fw-semibold text-success">
                                +₱{{ number_format($transaction->amount_minor / 100, 2) }}
                            </td>
                            <td class="text-end">
                                ₱{{ number_format($transaction->balance_after_minor / 100, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No Admin Wallet transactions yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">All Logistics COD Transfers</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Logistics</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Confirmed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($remittances as $remittance)
                        <tr>
                            <td>{{ $remittance->reference }}</td>
                            <td>{{ $remittance->logistic?->company_name ?? ('Logistics #'.$remittance->logistic_id) }}</td>
                            <td>₱{{ number_format($remittance->amount_minor / 100, 2) }}</td>
                            <td>
                                <span class="badge {{ $remittance->status === 'confirmed' ? 'text-bg-success' : ($remittance->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">
                                    {{ ucfirst($remittance->status) }}
                                </span>
                            </td>
                            <td>{{ $remittance->submitted_at?->format('M d, Y h:i A') ?? '—' }}</td>
                            <td>{{ $remittance->confirmed_at?->format('M d, Y h:i A') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No logistics transfers yet.
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
@endsection
