@extends('admin.layout')

@section('title', 'Seller Withdrawals')

@section('content')
<div class="container-fluid py-4">
    

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Seller Withdrawals</h2>
            <p class="text-muted mb-0">
                Process actual seller GCash, Maya, or bank payouts from wallet balances.
            </p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted">Pending Requests</div>
                    <div class="fs-3 fw-bold">{{ $stats['pending_count'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted">Pending Amount</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format($stats['pending_minor'] / 100, 2) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted">Total Paid</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format($stats['paid_minor'] / 100, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="fw-bold mb-0">Withdrawal Records</h5>
                <div class="d-flex gap-2">
                    @foreach(['all' => 'All', 'pending' => 'Pending', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $value => $label)
                        <a
                            href="{{ route('admin.seller-withdrawals.index', ['status' => $value]) }}"
                            class="btn btn-sm {{ $status === $value ? 'btn-dark' : 'btn-outline-secondary' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Seller</th>
                            <th>Amount</th>
                            <th>Payout Account</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th style="min-width: 330px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($withdrawals as $withdrawal)
                            <tr>
                                <td>#{{ $withdrawal->id }}</td>
                                <td>{{ $withdrawal->seller?->name ?? 'Unknown Seller' }}</td>
                                <td class="fw-bold">
                                    ₱{{ number_format($withdrawal->amount_minor / 100, 2) }}
                                </td>
                                <td>
                                    <div>{{ strtoupper(str_replace('_', ' ', $withdrawal->payment_method)) }}</div>
                                    <div>{{ $withdrawal->account_name }}</div>
                                    <div class="small text-muted">
                                        {{ str_repeat('•', max(0, strlen($withdrawal->account_number) - 4)) . substr($withdrawal->account_number, -4) }}
                                    </div>
                                    @if($withdrawal->bank_name)
                                        <div class="small">{{ $withdrawal->bank_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $badge = match($withdrawal->status) {
                                            'paid' => 'success',
                                            'rejected' => 'danger',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">
                                        {{ ucfirst($withdrawal->status) }}
                                    </span>
                                </td>
                                <td>{{ $withdrawal->requested_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                <td>
                                    @if($withdrawal->status === 'pending')
                                        <details class="mb-2">
                                            <summary class="btn btn-sm btn-success">
                                                Record Actual Payout
                                            </summary>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.seller-withdrawals.pay', $withdrawal) }}"
                                                class="border rounded p-3 mt-2">
                                                @csrf

                                                <div class="small text-muted mb-2">
                                                    Send exactly
                                                    <strong>₱{{ number_format($withdrawal->amount_minor / 100, 2) }}</strong>
                                                    to the seller account shown above.
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label small">Transaction Reference</label>
                                                    <input
                                                        type="text"
                                                        name="transaction_reference"
                                                        class="form-control form-control-sm"
                                                        maxlength="150"
                                                        required>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label small">Admin Notes</label>
                                                    <textarea
                                                        name="admin_notes"
                                                        rows="2"
                                                        maxlength="2000"
                                                        class="form-control form-control-sm"></textarea>
                                                </div>

                                                <div class="form-check mb-3">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        name="payout_verified"
                                                        value="1"
                                                        id="verify-{{ $withdrawal->id }}"
                                                        required>
                                                    <label
                                                        class="form-check-label small"
                                                        for="verify-{{ $withdrawal->id }}">
                                                        I actually sent the payout and verified the reference.
                                                    </label>
                                                </div>

                                                <button type="submit" class="btn btn-sm btn-success w-100">
                                                    Mark Withdrawal Paid
                                                </button>
                                            </form>
                                        </details>

                                        <details>
                                            <summary class="btn btn-sm btn-outline-danger">
                                                Reject
                                            </summary>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.seller-withdrawals.reject', $withdrawal) }}"
                                                class="border rounded p-3 mt-2">
                                                @csrf

                                                <label class="form-label small">Reason</label>
                                                <textarea
                                                    name="admin_notes"
                                                    rows="2"
                                                    maxlength="2000"
                                                    class="form-control form-control-sm mb-2"
                                                    required></textarea>

                                                <button type="submit" class="btn btn-sm btn-danger w-100">
                                                    Reject & Return to Wallet
                                                </button>
                                            </form>
                                        </details>
                                    @elseif($withdrawal->status === 'paid')
                                        <div class="small">
                                            <strong>Reference:</strong>
                                            {{ $withdrawal->transaction_reference }}
                                        </div>
                                        <div class="small text-muted">
                                            {{ $withdrawal->processed_at?->format('M d, Y h:i A') }}
                                        </div>
                                    @else
                                        <div class="small text-danger">
                                            {{ $withdrawal->admin_notes }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No seller withdrawals found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $withdrawals->links() }}
        </div>
    </div>

    <div class="alert alert-info mt-4">
        Wallet credits are internal BiliHub balances.
        Mark a withdrawal paid only after the actual GCash/Maya/bank transfer has been sent.
    </div>
</div>
@endsection
