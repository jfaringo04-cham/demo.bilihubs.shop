@php
$layout = 'layouts.app';
$messageRoutePrefix = 'buyer';

if (auth()->check()) {
    if (auth()->user()->isRider()) {
        $layout = 'rider.layout';
        $messageRoutePrefix = 'rider';
    } elseif (auth()->user()->isSeller()) {
        $layout = 'seller.layout';
        $messageRoutePrefix = 'seller';
    } elseif (auth()->user()->isLogisticOwner()) {
        $messageRoutePrefix = 'logistic';
    } elseif (auth()->user()->isAdmin()) {
        $messageRoutePrefix = 'admin';
    } elseif (auth()->user()->isCustomer()) {
        $messageRoutePrefix = 'buyer';
    }
}
@endphp
@extends($layout)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Messages</h1>
  @if ($logisticsContact ?? null)
    <a href="{{ route('rider.messages.show', $logisticsContact) }}" class="btn btn-outline-primary">
      <i class="bi bi-chat-dots me-1" aria-hidden="true"></i> Message Logistics
    </a>
  @endif
</div>

@if ($messageRoutePrefix === 'rider')
  <p class="text-muted">
    To contact a seller or buyer, open your assigned <a href="{{ route('rider.pickups') }}">pickup</a>
    or <a href="{{ route('rider.orders') }}">delivery</a>.
  </p>
@endif

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>User</th><th>Role</th><th>Last Activity</th><th>Unread</th><th>Action</th></tr>
      </thead>
      <tbody>
        @forelse($conversations as $convUser)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                  {{ substr($convUser->name, 0, 1) }}
                </div>
                <strong>{{ $convUser->name }}</strong>
              </div>
            </td>
            <td>
              @php
                $displayRole = $convUser->isSeller()
                    ? 'Seller'
                    : ($convUser->isRider()
                        ? 'Rider'
                        : ($convUser->isLogisticOwner()
                            ? 'Logistics'
                            : ($convUser->isAdmin() ? 'Admin' : 'Buyer')));
              @endphp
              <span class="badge bg-info text-capitalize">{{ $displayRole }}</span>
            </td>
            <td>@if($convUser->last_activity)
                {{ \Illuminate\Support\Carbon::parse($convUser->last_activity)->format('M d, Y H:i') }}
              @else
                —
              @endif</td>
            <td>{{ $convUser->unread_count > 0 ? $convUser->unread_count : '—' }}</td>
            <td><a href="{{ route($messageRoutePrefix . '.messages.show', $convUser) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-chat-dots"></i> Open</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No conversations yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
