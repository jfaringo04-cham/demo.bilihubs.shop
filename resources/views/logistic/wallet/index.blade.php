@extends('layouts.logistic', ['title' => 'Wallet', 'logistic' => $logistic])

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Logistics Wallet</h1>
            <p class="text-muted mb-0">Shipping earnings and withdrawal requests.</p>
        </div>
        <span class="badge text-bg-light border px-3 py-2">
            ₱{{ number_format($shipmentCreditMinor / 100, 2) }} per standard ₱50 shipment
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
        @foreach([
            ['Available Balance', $wallet->available_balance_minor],
            ['Pending Withdrawal', $wallet->pending_balance_minor],
            ['Total Earned', $totalEarnedMinor],
            ['Total Withdrawn', $totalWithdrawnMinor],
        ] as [$label, $amount])
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-2">{{ $label }}</div>
                        <div class="fs-3 fw-bold">₱{{ number_format($amount / 100, 2) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Withdraw Earnings</h2>

                    @if($hasPendingWithdrawal)
                        <div class="alert alert-warning">
                            You already have a pending withdrawal. Wait for Admin review before requesting another.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('logistic.wallet.withdraw') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Amount (₱)</label>
                            <input
                                type="number"
                                name="amount"
                                step="0.01"
                                min="0.01"
                                value="{{ old('amount') }}"
                                class="form-control"
                                required
                                @disabled($hasPendingWithdrawal)>
                            <div class="form-text">
                                Available: ₱{{ number_format($wallet->available_balance_minor / 100, 2) }} ·
                                Minimum: ₱{{ number_format($minimumWithdrawalMinor / 100, 2) }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select" required @disabled($hasPendingWithdrawal)>
                                <option value="">Select method</option>
                                <option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option>
                                <option value="maya" @selected(old('payment_method') === 'maya')>Maya</option>
                                <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Name</label>
                            <input name="account_name" value="{{ old('account_name') }}" class="form-control" required @disabled($hasPendingWithdrawal)>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account / Mobile Number</label>
                            <input name="account_number" value="{{ old('account_number') }}" class="form-control" required @disabled($hasPendingWithdrawal)>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bank Name <span class="text-muted">(bank transfer only)</span></label>
                            <input name="bank_name" value="{{ old('bank_name') }}" class="form-control" @disabled($hasPendingWithdrawal)>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="logistic_notes" rows="2" class="form-control" @disabled($hasPendingWithdrawal)>{{ old('logistic_notes') }}</textarea>
                        </div>

                        <button
                            class="btn btn-primary w-100"
                            @disabled($hasPendingWithdrawal || $wallet->available_balance_minor < $minimumWithdrawalMinor)>
                            Request Withdrawal
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Recent Withdrawals</h2>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($withdrawals as $withdrawal)
                                <tr>
                                    <td>#{{ $withdrawal->id }}</td>
                                    <td>₱{{ number_format($withdrawal->amount_minor / 100, 2) }}</td>
                                    <td>{{ strtoupper(str_replace('_', ' ', $withdrawal->payment_method)) }}</td>
                                    <td>
                                        <span class="badge {{ $withdrawal->status === 'paid' ? 'text-bg-success' : ($withdrawal->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">
                                            {{ ucfirst($withdrawal->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $withdrawal->transaction_reference ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No withdrawals yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
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
                        <th class="text-end">Amount / Event</th>
                        <th class="text-end">Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        @php($paidAmount = $transaction->type === 'withdrawal_paid' ? (int) data_get($transaction->meta, 'amount_minor', 0) : 0)
                        <tr>
                            <td>{{ $transaction->created_at?->format('M d, Y h:i A') }}</td>
                            <td>{{ $transaction->reference ?: '—' }}</td>
                            <td>{{ $transaction->shipment?->tracking_number ?: '—' }}</td>
                            <td>{{ $transaction->description ?: 'Logistics wallet transaction' }}</td>
                            <td class="text-end fw-semibold">
                                @if($transaction->type === 'withdrawal_paid')
                                    Paid ₱{{ number_format($paidAmount / 100, 2) }}
                                @else
                                    {{ $transaction->amount_minor >= 0 ? '+' : '-' }}₱{{ number_format(abs($transaction->amount_minor) / 100, 2) }}
                                @endif
                            </td>
                            <td class="text-end">₱{{ number_format($transaction->balance_after_minor / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">No logistics wallet transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
            <div class="card-footer bg-white">{{ $transactions->links() }}</div>
        @endif
    </div>

    <div class="alert alert-info mt-4 mb-0">
        Standard ₱50 shipping split: ₱15 pickup rider, ₱25 final rider, and ₱10 logistics.
        Wallet earnings are released only after the buyer confirms receipt.
    </div>
</div>
@endsection
