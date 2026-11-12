@extends('admin.layout')

@section('content')
<div class="pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Refund Disputes</h1>
</div>

@if($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<nav class="nav nav-pills mb-3" aria-label="Filter refund disputes">
  @foreach(['open' => 'Open', 'under_review' => 'Under Review', 'resolved' => 'Resolved'] as $value => $label)
    <a class="nav-link {{ $status === $value ? 'active' : '' }}"
       href="{{ route('admin.refund-disputes.index', ['status' => $value], false) }}"
       @if($status === $value) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>

@forelse($disputes as $refund)
  <article class="card mb-3">
    <div class="card-body">
      <h2 class="h5">{{ $refund->sellerOrder->order->order_number }}</h2>
      <p>{{ $refund->sellerOrder->seller?->name ?? 'Seller' }} · {{ $refund->dispute_status_label }}</p>
      <dl class="row mb-2">
        <dt class="col-sm-4">Refund amount</dt>
        <dd class="col-sm-8">₱{{ number_format($refund->amount_minor / 100, 2) }}</dd>
        <dt class="col-sm-4">Transfer reference</dt>
        <dd class="col-sm-8">{{ $refund->transfer_reference ?: 'Unavailable' }}</dd>
        <dt class="col-sm-4">Refund marked sent</dt>
        <dd class="col-sm-8">{{ $refund->sent_at }}</dd>
        <dt class="col-sm-4">Reported missing</dt>
        <dd class="col-sm-8">{{ $refund->reported_at }}</dd>
        @if($refund->dispute_reviewed_at)
          <dt class="col-sm-4">Review started</dt>
          <dd class="col-sm-8">{{ $refund->dispute_reviewed_at }}</dd>
        @endif
        @if($refund->dispute_proof_requested_at)
          <dt class="col-sm-4">Proof requested</dt>
          <dd class="col-sm-8">{{ $refund->dispute_proof_requested_at }}</dd>
        @endif
        @if($refund->dispute_proof_submitted_at)
          <dt class="col-sm-4">Proof submitted</dt>
          <dd class="col-sm-8">{{ $refund->dispute_proof_submitted_at }} ·
            <a href="{{ route('admin.refund-disputes.proof', $refund, false) }}" target="_blank" rel="noopener">View transfer proof</a>
          </dd>
        @endif
        @if($refund->received_at)
          <dt class="col-sm-4">Buyer confirmed receipt</dt>
          <dd class="col-sm-8">{{ $refund->received_at }}</dd>
        @endif
        @if($refund->dispute_resolved_at)
          <dt class="col-sm-4">Admin resolution</dt>
          <dd class="col-sm-8">{{ $refund->dispute_resolved_at }} · {{ $refund->dispute_resolution }}</dd>
        @endif
      </dl>

      @if($refund->dispute_status_label !== 'Resolved')
        <div class="d-flex gap-2 mb-3">
          @unless($refund->dispute_reviewed_at)
            <form method="POST" action="{{ route('admin.refund-disputes.update', $refund, false) }}">
              @csrf
              <button name="action" value="review" class="btn btn-outline-primary btn-sm">Start review</button>
            </form>
          @endunless
          @unless($refund->dispute_proof_requested_at)
            <form method="POST" action="{{ route('admin.refund-disputes.update', $refund, false) }}">
              @csrf
              <button name="action" value="request_proof" class="btn btn-outline-primary btn-sm">Request transfer proof</button>
            </form>
          @endunless
        </div>
        <form method="POST" action="{{ route('admin.refund-disputes.update', $refund, false) }}">
          @csrf
          <label for="resolution-{{ $refund->id }}" class="form-label">Resolution visible to buyer and seller</label>
          <textarea id="resolution-{{ $refund->id }}" class="form-control mb-2" name="resolution"
                    maxlength="2000" rows="2" required></textarea>
          <button name="action" value="resolve" class="btn btn-primary btn-sm">Resolve dispute</button>
        </form>
      @endif
    </div>
  </article>
@empty
  <p class="text-muted">No refund disputes in this view.</p>
@endforelse

{{ $disputes->links() }}
@endsection
