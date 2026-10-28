@extends('admin.layout')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Logistics Withdrawals</h1>
            <p class="text-muted mb-0">Review and record payouts requested by logistics providers.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">Pending Requests</div><div class="fs-3 fw-bold">{{ $stats['pending_count'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">Pending Amount</div><div class="fs-3 fw-bold">₱{{ number_format($stats['pending_minor'] / 100, 2) }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">Total Paid</div><div class="fs-3 fw-bold">₱{{ number_format($stats['paid_minor'] / 100, 2) }}</div></div></div></div>
    </div>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        @foreach(['pending' => 'Pending', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
            <a href="{{ route('admin.logistic-withdrawals.index', ['status' => $value]) }}"
               class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Request</th>
                        <th>Logistics</th>
                        <th>Amount</th>
                        <th>Payout Account</th>
                        <th>Status</th>
                        <th style="min-width: 280px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($withdrawals as $withdrawal)
                        <tr>
                            <td>
                                <strong>#{{ $withdrawal->id }}</strong>
                                <div class="small text-muted">{{ $withdrawal->requested_at?->format('M d, Y h:i A') }}</div>
                            </td>
                            <td>
                                <strong>{{ $withdrawal->logistic?->company_name ?: 'Unknown Logistics' }}</strong>
                                <div class="small text-muted">{{ $withdrawal->logistic?->owner?->email }}</div>
                            </td>
                            <td class="fw-semibold">₱{{ number_format($withdrawal->amount_minor / 100, 2) }}</td>
                            <td>
                                <div>{{ strtoupper(str_replace('_', ' ', $withdrawal->payment_method)) }}</div>
                                <div class="small">{{ $withdrawal->account_name }}</div>
                                <div class="small text-muted">{{ $withdrawal->account_number }}</div>
                                @if($withdrawal->bank_name)<div class="small text-muted">{{ $withdrawal->bank_name }}</div>@endif
                            </td>
                            <td>{{ ucfirst($withdrawal->status) }}</td>
                            <td>
                                @if($withdrawal->status === 'pending')
                                    <details class="mb-2">
                                        <summary class="btn btn-sm btn-success">Mark Paid</summary>
                                        <form method="POST" action="{{ route('admin.logistic-withdrawals.paid', $withdrawal) }}" class="border rounded p-3 mt-2">
                                            @csrf
                                            <input name="transaction_reference" class="form-control form-control-sm mb-2" placeholder="Transaction Reference" maxlength="200" required>
                                            <textarea name="admin_notes" rows="2" class="form-control form-control-sm mb-2" placeholder="Admin notes (optional)"></textarea>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="payout_verified" value="1" id="verify-logistic-{{ $withdrawal->id }}" required>
                                                <label class="form-check-label small" for="verify-logistic-{{ $withdrawal->id }}">I actually sent the payout and verified the reference.</label>
                                            </div>
                                            <button class="btn btn-sm btn-success w-100">Mark Withdrawal Paid</button>
                                        </form>
                                    </details>

                                    <details>
                                        <summary class="btn btn-sm btn-outline-danger">Reject</summary>
                                        <form method="POST" action="{{ route('admin.logistic-withdrawals.reject', $withdrawal) }}" class="border rounded p-3 mt-2">
                                            @csrf
                                            <textarea name="admin_notes" rows="2" maxlength="2000" class="form-control form-control-sm mb-2" placeholder="Reason" required></textarea>
                                            <button class="btn btn-sm btn-danger w-100">Reject & Return to Wallet</button>
                                        </form>
                                    </details>
                                @elseif($withdrawal->status === 'paid')
                                    <div><strong>Reference:</strong> {{ $withdrawal->transaction_reference }}</div>
                                    <div class="small text-muted">{{ $withdrawal->processed_at?->format('M d, Y h:i A') }}</div>
                                @else
                                    <div class="small text-danger">{{ $withdrawal->admin_notes }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">No logistics withdrawals found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($withdrawals->hasPages())<div class="card-footer bg-white">{{ $withdrawals->links() }}</div>@endif
    </div>

    <div class="alert alert-info mt-4">
        Mark a withdrawal paid only after the external GCash, Maya, or bank transfer has actually been sent.
    </div>
</div>
@endsection
