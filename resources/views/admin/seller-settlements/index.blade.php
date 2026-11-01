@extends('admin.layout')

@section('title', 'Seller Settlements')

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
            <h2 class="fw-bold mb-1">Seller Settlements</h2>
            <p class="text-muted mb-0">
                Review seller earnings and credit approved net payouts into seller wallets.
            </p>
        </div>
        <span class="badge bg-secondary">Admin Financial Management</span>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            [
                'title' => 'For Review',
                'amount' => $stats['eligible_minor'],
                'subtitle' => $stats['eligible_count'] . ' settlement(s)',
                'color' => 'warning',
            ],
            [
                'title' => 'Awaiting Wallet Credit',
                'amount' => $stats['approved_minor'],
                'subtitle' => 'Approved but not yet credited',
                'color' => 'primary',
            ],
            [
                'title' => 'In Seller Wallet',
                'amount' => $stats['wallet_credited_minor'],
                'subtitle' => 'Credited to seller wallets',
                'color' => 'success',
            ],
            [
                'title' => 'Legacy Paid',
                'amount' => $stats['paid_minor'],
                'subtitle' => 'Old direct payout records',
                'color' => 'secondary',
            ],
        ] as $card)
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted mb-1">{{ $card['title'] }}</div>
                    <div class="fs-3 fw-bold">
                        ₱{{ number_format($card['amount'] / 100, 2) }}
                    </div>
                    <span class="badge bg-{{ $card['color'] }}">{{ $card['subtitle'] }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h5 class="fw-bold mb-0">Settlement Records</h5>
                <div class="d-flex gap-2 flex-wrap">
                    @foreach([
                        'all' => 'All',
                        'eligible' => 'For Review',
                        'approved' => 'Approved',
                        'wallet_credited' => 'In Wallet',
                        'paid' => 'Legacy Paid',
                    ] as $value => $label)
                    <a href="{{ route('admin.seller-settlements.index', ['status' => $value]) }}"
                        class="btn btn-sm {{ $status === $value ? 'btn-dark' : 'btn-outline-secondary' }}">
                        {{ $label }}
                    </a>
                    @endforeach
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Settlement</th>
                            <th>Seller</th>
                            <th>Seller Order</th>
                            <th>Subtotal</th>
                            <th>Commission</th>
                            <th>Net Payout</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settlements as $settlement)
                        @php
                            $badge = match ($settlement->status) {
                                'eligible' => 'warning',
                                'approved' => 'primary',
                                'wallet_credited' => 'success',
                                'paid' => 'secondary',
                                default => 'secondary',
                            };

                            $label = match ($settlement->status) {
                                'eligible' => 'For Review',
                                'wallet_credited' => 'In Seller Wallet',
                                'paid' => 'Legacy Paid',
                                default => ucfirst($settlement->status),
                            };

                            $payout = ($payouts ?? collect())->get($settlement->id);
                        @endphp
                        <tr>
                            <td><strong>#{{ $settlement->id }}</strong></td>
                            <td>{{ $settlement->seller?->name ?? 'Unknown Seller' }}</td>
                            <td>#{{ $settlement->seller_order_id }}</td>
                            <td>₱{{ number_format($settlement->subtotal_minor / 100, 2) }}</td>
                            <td>₱{{ number_format($settlement->commission_minor / 100, 2) }}</td>
                            <td class="fw-bold">₱{{ number_format($settlement->net_minor / 100, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $badge }}">{{ $label }}</span>
                            </td>
                            <td>{{ $settlement->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td style="min-width: 250px;">
                                @if($settlement->status === 'eligible')
                                    <form
                                        method="POST"
                                        action="{{ route('admin.seller-settlements.approve', $settlement) }}"
                                        onsubmit="return confirm('Approve this settlement and credit the net payout to the seller wallet?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            Approve & Credit Wallet
                                        </button>
                                    </form>
                                @elseif($settlement->status === 'approved')
                                    <span class="badge bg-primary">
                                        Awaiting wallet credit
                                    </span>
                                @elseif($settlement->status === 'wallet_credited')
                                    <span class="text-success small fw-semibold">
                                        <i class="bi bi-wallet2"></i>
                                        Funds are in seller wallet
                                    </span>
                                @elseif($settlement->status === 'paid')
                                    <span class="badge bg-secondary mb-2">Legacy Paid</span>
                                    @if($payout)
                                    <div class="small">
                                        <div><strong>Method:</strong> {{ $payout->payment_method }}</div>
                                        <div><strong>Reference:</strong> {{ $payout->transaction_reference }}</div>
                                    </div>
                                    @endif
                                @else
                                    <span class="text-muted small">No action available</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                No seller settlements found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $settlements->links() }}</div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        <strong>New payout flow:</strong>
        Admin approval credits the net payout to the seller's internal BiliHub wallet.
        The seller then requests withdrawal, and the admin records the actual GCash/Maya/bank payout
        from the Seller Withdrawals page.
    </div>
</div>
@endsection
