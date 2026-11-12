<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rider Dashboard - Bili Hub</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  @vite([
  'resources/css/app.css',
  'resources/css/shared-layout.css',
  'resources/css/components/footer.css',
  'resources/css/rider/rider-layout.css',
  'resources/js/app.js',
  'resources/js/rider/layout.js',
  ])
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="rider-notification-read-url" content="{{ url('/rider/notifications/__id__/read') }}">
  @stack('styles')
</head>

<body class="d-flex flex-column min-vh-100">
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
            <ul class="dropdown-menu dropdown-menu-end rider-notification-menu" aria-labelledby="riderNotificationDropdown">
              <li>
                <h6 class="dropdown-header">Notifications</h6>
              </li>
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
                  <div class="text-muted small rider-notification-message">{{ Str::limit($notification->message, 110) }}</div>
                </a>
              </li>
              @empty
              <li><span class="dropdown-item text-muted">No notifications yet.</span></li>
              @endforelse
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item text-center rider-notification-view-all" href="{{ route('rider.notifications') }}">View All Notifications</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="riderUserDropdown" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle"></i> {{ Auth::user()->name }}
            </a>
            <ul class="dropdown-menu dropdown-menu-end overflow-auto" aria-labelledby="riderUserDropdown"
              style="max-height: min(70vh, 36rem);">
              <li><h6 class="dropdown-header">Deliveries</h6></li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.dashboard') ? 'active' : '' }}"
                  href="{{ route('rider.dashboard') }}" @if (request()->routeIs('rider.dashboard')) aria-current="page" @endif>
                  <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i> Dashboard
                </a>
              </li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.pickups', 'rider.pickups.*') ? 'active' : '' }}"
                  href="{{ route('rider.pickups') }}" @if (request()->routeIs('rider.pickups')) aria-current="page" @endif>
                  <i class="bi bi-box-seam me-1" aria-hidden="true"></i> Available Pickups
                </a>
              </li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.orders', 'rider.orders.*', 'rider.shipments.*') ? 'active' : '' }}"
                  href="{{ route('rider.orders') }}" @if (request()->routeIs('rider.orders')) aria-current="page" @endif>
                  <i class="bi bi-receipt me-1" aria-hidden="true"></i> My Deliveries
                </a>
              </li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.deliveries') ? 'active' : '' }}"
                  href="{{ route('rider.deliveries') }}" @if (request()->routeIs('rider.deliveries')) aria-current="page" @endif>
                  <i class="bi bi-bell me-1" aria-hidden="true"></i> Delivery Notifications
                </a>
              </li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.history') ? 'active' : '' }}"
                  href="{{ route('rider.history') }}" @if (request()->routeIs('rider.history')) aria-current="page" @endif>
                  <i class="bi bi-clock-history me-1" aria-hidden="true"></i> Delivery History
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Earnings</h6></li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.wallet.index', 'rider.wallet.*') ? 'active' : '' }}"
                  href="{{ route('rider.wallet.index') }}" @if (request()->routeIs('rider.wallet.index')) aria-current="page" @endif>
                  <i class="bi bi-wallet2 me-1" aria-hidden="true"></i> Wallet
                </a>
              </li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.profit') ? 'active' : '' }}"
                  href="{{ route('rider.profit') }}" @if (request()->routeIs('rider.profit')) aria-current="page" @endif>
                  <i class="bi bi-graph-up me-1" aria-hidden="true"></i> Earnings Summary
                </a>
              </li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.cod-remittances.index', 'rider.cod-remittances.*') ? 'active' : '' }}"
                  href="{{ route('rider.cod-remittances.index') }}" @if (request()->routeIs('rider.cod-remittances.index')) aria-current="page" @endif>
                  <i class="bi bi-cash-coin me-1" aria-hidden="true"></i> COD Remittances
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Account</h6></li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.messages.index', 'rider.messages.*') ? 'active' : '' }}"
                  href="{{ route('rider.messages.index') }}" @if (request()->routeIs('rider.messages.index')) aria-current="page" @endif>
                  <i class="bi bi-chat-dots me-1" aria-hidden="true"></i> Messages
                </a>
              </li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.addresses', 'rider.addresses.*') ? 'active' : '' }}"
                  href="{{ route('rider.addresses') }}" @if (request()->routeIs('rider.addresses')) aria-current="page" @endif>
                  <i class="bi bi-geo-alt me-1" aria-hidden="true"></i> Addresses
                </a>
              </li>
              <li>
                <a class="dropdown-item {{ request()->routeIs('rider.account', 'rider.account.*') ? 'active' : '' }}"
                  href="{{ route('rider.account') }}" @if (request()->routeIs('rider.account')) aria-current="page" @endif>
                  <i class="bi bi-person me-1" aria-hidden="true"></i> Account Settings
                </a>
              </li>
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

  <main class="flex-grow-1">
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

  <x-footer />

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @if(config('services.googlemaps.key'))
  <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.googlemaps.key') }}&libraries=places"></script>
  @else
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  @endif

  @vite('resources/js/rider/location.js')
  @stack('scripts')

</body>

</html>