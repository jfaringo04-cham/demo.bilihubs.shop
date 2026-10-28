@extends('rider.layout')
@section('content')
<div class="container-fluid py-4">
<h2>Rider Wallet</h2><p class="text-muted">Pickup: ₱{{ number_format($pickupEarningMinor/100,2) }} · Final delivery: ₱{{ number_format($deliveryEarningMinor/100,2) }} per confirmed shipment.</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="row g-3 mb-4">
@foreach([['Available',$wallet->available_balance_minor],['Pending',$wallet->pending_balance_minor],['Total Earned',$totalEarnedMinor],['Total Withdrawn',$totalWithdrawnMinor]] as [$l,$a])
<div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">{{ $l }}</div><div class="fs-3 fw-bold">₱{{ number_format($a/100,2) }}</div></div></div></div>
@endforeach
</div>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body"><h5>Withdraw Earnings</h5>
@if($hasPendingWithdrawal)<div class="alert alert-warning">You already have a pending withdrawal.</div>@endif
<form method="POST" action="{{ route('rider.wallet.withdraw') }}">@csrf
<div class="mb-3"><label>Amount (₱)</label><input name="amount" class="form-control" required @disabled($hasPendingWithdrawal)><small>Available ₱{{ number_format($wallet->available_balance_minor/100,2) }} · Minimum ₱{{ number_format($minimumWithdrawalMinor/100,2) }}</small></div>
<div class="mb-3"><label>Payment Method</label><select name="payment_method" class="form-select" required @disabled($hasPendingWithdrawal)><option value="">Select method</option><option value="gcash">GCash</option><option value="maya">Maya</option><option value="bank_transfer">Bank Transfer</option></select></div>
<div class="mb-3"><label>Account Name</label><input name="account_name" class="form-control" required @disabled($hasPendingWithdrawal)></div>
<div class="mb-3"><label>Account / Mobile Number</label><input name="account_number" class="form-control" required @disabled($hasPendingWithdrawal)></div>
<div class="mb-3"><label>Bank Name</label><input name="bank_name" class="form-control" @disabled($hasPendingWithdrawal)></div>
<div class="mb-3"><label>Notes</label><textarea name="rider_notes" class="form-control" @disabled($hasPendingWithdrawal)></textarea></div>
<button class="btn btn-primary w-100" @disabled($hasPendingWithdrawal || $wallet->available_balance_minor<$minimumWithdrawalMinor)>Request Withdrawal</button>
</form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm mb-4"><div class="card-body"><h5>Recent Withdrawals</h5><div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Amount</th><th>Method</th><th>Status</th><th>Reference</th></tr></thead><tbody>
@forelse($withdrawals as $w)<tr><td>#{{ $w->id }}</td><td>₱{{ number_format($w->amount_minor/100,2) }}</td><td>{{ strtoupper(str_replace('_',' ',$w->payment_method)) }}</td><td>{{ ucfirst($w->status) }}</td><td>{{ $w->transaction_reference ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No withdrawals yet.</td></tr>@endforelse
</tbody></table></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-body"><h5>Wallet Transactions</h5><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Description</th><th>Amount/Event</th><th>Balance</th></tr></thead><tbody>
@forelse($transactions as $t)@php($paid=$t->type==='withdrawal_paid'?(int)data_get($t->meta,'amount_minor',0):0)<tr><td>{{ $t->created_at?->format('M d, Y h:i A') }}</td><td>{{ $t->description }}<div class="small text-muted">{{ $t->reference }}</div></td><td>@if($t->type==='withdrawal_paid')Paid ₱{{ number_format($paid/100,2) }}@else{{ $t->amount_minor>=0?'+':'-' }}₱{{ number_format(abs($t->amount_minor)/100,2) }}@endif</td><td>₱{{ number_format($t->balance_after_minor/100,2) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">No transactions yet.</td></tr>@endforelse
</tbody></table></div>{{ $transactions->links() }}</div></div></div></div>
</div>
@endsection
