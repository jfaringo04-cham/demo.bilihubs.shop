<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rider Dashboard - Bili Hub</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <style>
    body { background-color: #f8f9fa; display: flex; flex-direction: column; min-height: 100vh; }
    main { flex: 1; }
    #map, #rider-map { height: 400px; border-radius: 10px; }
    .proof-section { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 15px; }
    .icon-badge { position: relative; display: inline-flex; align-items: center; }
    .table-container { margin: 0 10px; }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid px-3">
      <a class="navbar-brand me-4" href="{{ route('rider.dashboard') }}">
        <img src="{{ asset('images/bilihublogo.png') }}" alt="Bili Hub" style="height: 42px; width: auto;">
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#riderNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="riderNavbar">
        <ul class="navbar-nav me-auto">
          </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown">
            <a class="nav-link icon-badge dropdown-toggle" href="#" id="riderNotificationDropdown" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-bell fs-5"></i>
              @php
                $unreadCount = \App\Models\Notification::where('user_id', Auth::id())->where('is_read', false)->count();
              @endphp
              @if($unreadCount > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill cart-badge notification-badge">
                  {{ $unreadCount }}
                </span>
              @endif
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="riderNotificationDropdown" style="min-width: 320px; max-width: 400px;">
              <li><h6 class="dropdown-header">Notifications</h6></li>
              @php
                $recentNotifications = \App\Models\Notification::where('user_id', Auth::id())->latest()->take(10)->get();
              @endphp
              @forelse($recentNotifications as $notification)
                <li>
                  <a class="dropdown-item {{ $notification->is_read ? '' : 'fw-bold' }}" href="{{ $notification->link ?: '#' }}" onclick="markAsRead({{ $notification->id }}, this)">
                    <div class="d-flex w-100 justify-content-between">
                      <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                      @if(!$notification->is_read)
                        <small class="text-primary">New</small>
                      @endif
                    </div>
                    <div class="mt-1">{{ $notification->title }}</div>
                    <div class="text-muted small">{{ Str::limit($notification->message, 80) }}</div>
                  </a>
                </li>
              @empty
                <li><span class="dropdown-item text-muted">No notifications yet.</span></li>
              @endforelse
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-center" href="{{ route('rider.deliveries') }}">View All Notifications</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="riderUserDropdown" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle"></i> {{ Auth::user()->name }}
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="riderUserDropdown">
              <li><a class="dropdown-item" href="{{ route('rider.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
              <li><a class="dropdown-item" href="{{ route('rider.deliveries') }}"><i class="bi bi-bell"></i> Delivery Notifications</a></li>
              <li><a class="dropdown-item" href="{{ route('rider.pickups') }}"><i class="bi bi-box-seam"></i> Available Pickups</a></li>
              <li><a class="dropdown-item" href="{{ route('rider.orders') }}"><i class="bi bi-receipt"></i> My Deliveries</a></li>
              <li><a class="dropdown-item" href="{{ route('rider.addresses') }}"><i class="bi bi-geo-alt"></i> Addresses</a></li>
              <li><a class="dropdown-item" href="{{ route('rider.history') }}"><i class="bi bi-clock-history"></i> Delivery History</a></li>
              <li><a class="dropdown-item" href="{{ route('rider.profit') }}"><i class="bi bi-graph-up"></i> Profit</a></li>
              <li><a class="dropdown-item" href="{{ route('rider.messages.index') }}"><i class="bi bi-chat-dots"></i> Messages</a></li>
              <li><a class="dropdown-item" href="{{ route('rider.account') }}"><i class="bi bi-person"></i> Account</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                  @csrf
                  <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <main>
    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    @yield('content')
  </main>

  <footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
      <p class="mb-0">&copy; {{ date('Y') }} Bili Hub. All rights reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @if(config('services.googlemaps.key'))
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.googlemaps.key') }}&libraries=places"></script>
  @else
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  @endif
  @stack('scripts')
  <script>
    function markAsRead(notificationId, element) {
      fetch('/rider/notifications/' + notificationId + '/read', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Content-Type': 'application/json',
        },
      }).then(() => {
        if (element) {
          element.classList.remove('fw-bold');
          const newBadge = element.querySelector('.text-primary');
          if (newBadge) newBadge.remove();
        }
        const badge = document.querySelector('.notification-badge');
        if (badge) {
          const currentCount = parseInt(badge.textContent || '0');
          if (currentCount > 1) {
            badge.textContent = currentCount - 1;
          } else {
            badge.remove();
          }
        }
      }).catch(() => {});
    }
  </script>
</body>
</html>
