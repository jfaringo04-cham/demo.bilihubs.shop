@extends('seller.layout')

@section('page-title', 'Wallet')

@section('content')
<div class="dashboard-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="mb-1">Seller Wallet</h2>
            <p class="text-muted mb-0">
                Approved settlement earnings appear here before withdrawal.
            </p>
        </div>
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
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Available Balance</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format($wallet->available_balance_minor / 100, 2) }}
                    </div>
                    <div class="small text-success">Ready for withdrawal</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Pending Withdrawal</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format($wallet->pending_balance_minor / 100, 2) }}
                    </div>
                    <div class="small text-muted">Reserved while admin processes payout</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Earned</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format($totalEarnedMinor / 100, 2) }}
                    </div>
                    <div class="small text-muted">Settlement credits</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Withdrawn</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format($totalWithdrawnMinor / 100, 2) }}
                    </div>
                    <div class="small text-muted">Verified external payouts</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Withdraw Funds</h5>

                    @if($hasPendingWithdrawal)
                        <div class="alert alert-warning">
                            You already have a pending withdrawal.
                            Wait until admin marks it paid or rejected before requesting another.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('seller.wallet.withdraw') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Amount (₱)</label>
                            <input
                                type="text"
                                name="amount"
                                class="form-control"
                                value="{{ old('amount') }}"
                                placeholder="{{ number_format($minimumWithdrawalMinor / 100, 2, '.', '') }}"
                                required
                                @disabled($hasPendingWithdrawal)>
                            <div class="form-text">
                                Available: ₱{{ number_format($wallet->available_balance_minor / 100, 2) }}
                                · Minimum: ₱{{ number_format($minimumWithdrawalMinor / 100, 2) }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select
                                name="payment_method"
                                class="form-select"
                                required
                                @disabled($hasPendingWithdrawal)>
                                <option value="">Select method</option>
                                <option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option>
                                <option value="maya" @selected(old('payment_method') === 'maya')>Maya</option>
                                <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Name</label>
                            <input
                                type="text"
                                name="account_name"
                                class="form-control"
                                maxlength="150"
                                value="{{ old('account_name') }}"
                                required
                                @disabled($hasPendingWithdrawal)>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account / Mobile Number</label>
                            <input
                                type="text"
                                name="account_number"
                                class="form-control"
                                maxlength="150"
                                value="{{ old('account_number') }}"
                                required
                                @disabled($hasPendingWithdrawal)>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bank Name</label>
                            <input
                                type="text"
                                name="bank_name"
                                class="form-control"
                                maxlength="150"
                                value="{{ old('bank_name') }}"
                                placeholder="Required only for Bank Transfer"
                                @disabled($hasPendingWithdrawal)>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea
                                name="seller_notes"
                                class="form-control"
                                rows="2"
                                maxlength="1000"
                                @disabled($hasPendingWithdrawal)>{{ old('seller_notes') }}</textarea>
                        </div>

                        <button
                            class="btn btn-primary w-100"
                            type="submit"
                            @disabled(
                                $wallet->available_balance_minor < $minimumWithdrawalMinor
                                || $hasPendingWithdrawal
                            )>
                            {{ $hasPendingWithdrawal ? 'Pending Withdrawal in Progress' : 'Request Withdrawal' }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="alert alert-info mt-3">
                Wallet balance is internal BiliHub balance.
                Actual GCash/Maya/bank money is sent only after an admin processes your withdrawal.
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Recent Withdrawals</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($withdrawals as $withdrawal)
                                    @php
                                        $badge = match($withdrawal->status) {
                                            'paid' => 'success',
                                            'rejected' => 'danger',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <tr>
                                        <td>#{{ $withdrawal->id }}</td>
                                        <td>₱{{ number_format($withdrawal->amount_minor / 100, 2) }}</td>
                                        <td>{{ strtoupper(str_replace('_', ' ', $withdrawal->payment_method)) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $badge }}">
                                                {{ ucfirst($withdrawal->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $withdrawal->requested_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                        <td>
                                            <details>
                                                <summary class="btn btn-sm btn-outline-secondary">
                                                    View
                                                </summary>
                                                <div class="small border rounded p-2 mt-2" style="min-width: 250px;">
                                                    <div><strong>Account:</strong> {{ $withdrawal->account_name }}</div>
                                                    <div>
                                                        <strong>Number:</strong>
                                                        {{ str_repeat('•', max(0, strlen($withdrawal->account_number) - 4)) . substr($withdrawal->account_number, -4) }}
                                                    </div>
                                                    @if($withdrawal->bank_name)
                                                        <div><strong>Bank:</strong> {{ $withdrawal->bank_name }}</div>
                                                    @endif
                                                    @if($withdrawal->transaction_reference)
                                                        <div class="mt-1">
                                                            <strong>Payout Reference:</strong>
                                                            {{ $withdrawal->transaction_reference }}
                                                        </div>
                                                    @endif
                                                    @if($withdrawal->processed_at)
                                                        <div>
                                                            <strong>Processed:</strong>
                                                            {{ $withdrawal->processed_at->format('M d, Y h:i A') }}
                                                        </div>
                                                    @endif
                                                    @if($withdrawal->admin_notes)
                                                        <div class="mt-1">
                                                            <strong>Admin Notes:</strong>
                                                            {{ $withdrawal->admin_notes }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No withdrawal requests yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Wallet Transactions</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Amount / Event</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $transaction)
                                    @php
                                        $paidWithdrawalAmount = $transaction->type === 'withdrawal_paid'
                                            ? (int) data_get($transaction->meta, 'amount_minor', 0)
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $transaction->created_at?->format('M d, Y h:i A') }}</td>
                                        <td>
                                            {{ $transaction->description }}
                                            @if($transaction->reference)
                                                <div class="small text-muted">{{ $transaction->reference }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($transaction->type === 'withdrawal_paid')
                                                <span class="text-success fw-semibold">
                                                    Paid ₱{{ number_format($paidWithdrawalAmount / 100, 2) }}
                                                </span>
                                            @else
                                                <span class="{{ $transaction->amount_minor >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $transaction->amount_minor >= 0 ? '+' : '-' }}₱{{ number_format(abs($transaction->amount_minor) / 100, 2) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>₱{{ number_format($transaction->balance_after_minor / 100, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            No wallet transactions yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
