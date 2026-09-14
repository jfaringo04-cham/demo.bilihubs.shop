<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seller Dashboard - Bili Hub</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <style>
    body { background-color: #f8f9fa; display: flex; flex-direction: column; min-height: 100vh; }
    main { flex: 1; }
    #map, #rider-map { height: 400px; border-radius: 10px; }
    .icon-badge { position: relative; display: inline-flex; align-items: center; }
    .view-btn { position: relative; }
    .view-btn .view-text {
      display: none;
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: #333;
      color: #fff;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.75rem;
      white-space: nowrap;
      pointer-events: none;
      margin-bottom: 6px;
    }
    .view-btn .view-text::after {
      content: '';
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      border-width: 5px;
      border-style: solid;
      border-color: #333 transparent transparent transparent;
    }
    .view-btn:hover .view-text { display: block; }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid px-3">
      <!-- Logo -->
      <a class="navbar-brand me-4" href="{{ route('home') }}">
        <img src="{{ asset('images/bilihublogo.png') }}" alt="Bili Hub" style="height: 42px; width: auto;">
      </a>
      
      <!-- Toggler for mobile -->
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#sellerNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- Navbar content -->
      <div class="collapse navbar-collapse" id="sellerNavbar">
        <!-- Left: Home & Products -->
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link" href="{{ route('home') }}"><i class="bi bi-house"></i> Home</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ route('products.index') }}"><i class="bi bi-bag"></i> Products</a></li>
        </ul>

        <!-- Center: Search Bar -->
        <form class="d-flex search-container me-3" action="{{ route('products.index') }}" method="GET">
          <input class="form-control me-2" type="search" name="search" placeholder="Search products..." value="{{ request('search') }}">
          <button class="btn btn-bili-hub" type="submit"><i class="bi bi-search"></i></button>
        </form>

        <!-- Right: User Actions & Auth Links -->
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link icon-badge" href="{{ route('seller.notifications') }}" title="Notifications">
              <i class="bi bi-bell fs-5"></i>
              @php
                $seller = Auth::user();
                $unreadNotifCount = \App\Models\Notification::where('user_id', $seller->id)->where('is_read', false)->count();
                $pendingOrderCount = \App\Models\Order::whereHas('items.product', function ($query) use ($seller) {
                  $query->where('user_id', $seller->id);
                })->whereIn('status', ['pending', 'processing'])->count();
                $totalUnread = $unreadNotifCount + $pendingOrderCount;
              @endphp
              @if($totalUnread > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill cart-badge notification-badge">
                  {{ $totalUnread }}
                </span>
              @endif
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="sellerUserDropdown" role="button" data-bs-toggle="dropdown">
              @if(Auth::user()->logo)
                <img src="{{ asset('storage/' . Auth::user()->logo) }}" alt="Logo" style="width:28px;height:28px;object-fit:cover;border-radius:50%;border:1px solid #ddd;">
              @else
                <i class="bi bi-person-circle"></i>
              @endif
              <span>{{ Auth::user()->name }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sellerUserDropdown">
              <li><a class="dropdown-item" href="{{ route('seller.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
              <li><a class="dropdown-item" href="{{ route('seller.products') }}"><i class="bi bi-box"></i> Manage Inventory</a></li>
              <li><a class="dropdown-item" href="{{ route('seller.orders') }}"><i class="bi bi-receipt"></i> Orders</a></li>
              <li><a class="dropdown-item" href="{{ route('seller.notifications') }}"><i class="bi bi-bell"></i> Notifications</a></li>
              <li><a class="dropdown-item" href="{{ route('seller.messages.index') }}"><i class="bi bi-chat-dots"></i> Messages</a></li>
              <li><a class="dropdown-item" href="{{ route('seller.account') }}"><i class="bi bi-shop"></i> Account</a></li>
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

  @if(Auth::user()->status === 'suspended')
    <div class="modal fade" id="suspensionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Account Suspended</h5>
          </div>
          <div class="modal-body">
            <p class="mb-3">Your seller account has been suspended. You may submit an appeal within <strong>7 days</strong> from the suspension date.</p>
            @php
              $daysLeft = 0;
              $appealDeadline = null;
              $suspendedAt = Auth::user()->suspended_at ?? now();
              $appealDeadline = $suspendedAt->addDays(7);
              $daysLeft = now()->diffInDays($appealDeadline, false);
              if ($daysLeft < 0) $daysLeft = 0;
              $userHasAppealed = Auth::user()->appeal_submitted_at !== null;
            @endphp
            @if(Auth::user()->suspended_at)
              <p class="mb-3">Suspension Date: <strong>{{ $suspendedAt->format('M d, Y g:i A') }}</strong></p>
            @endif
            @if($daysLeft > 0)
              <p class="mb-3">You have <strong>{{ $daysLeft }} day(s) left</strong> to submit an appeal. Deadline: <strong>{{ $appealDeadline->format('M d, Y g:i A') }}</strong></p>
            @else
              <p class="mb-3 text-danger">The appeal deadline has passed. Please contact admin support.</p>
            @endif
            @if(Auth::user()->rejection_reason)
              <p class="mb-3"><strong>Reason:</strong> {{ Auth::user()->rejection_reason }}</p>
            @endif
            @if($userHasAppealed)
              <div class="alert alert-warning">
                <i class="bi bi-hourglass-split"></i> You have already submitted an appeal. Please wait for admin review.
              </div>
            @else
            <form action="{{ route('seller.account.appeal') }}" method="POST">
              @csrf
              <div class="mb-3">
                <label for="appeal_message" class="form-label">Appeal Message</label>
                <textarea name="appeal_message" id="appeal_message" class="form-control" rows="4" placeholder="Explain why your account should be reinstated..." required></textarea>
              </div>
              <div class="d-flex justify-content-between">
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-outline-light"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
                <button type="submit" class="btn btn-warning" @if($daysLeft <= 0) disabled @endif>Submit Appeal</button>
              </div>
            </form>
            @endif
          </div>
        </div>
      </div>
    </div>
  @endif

  <footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
      <p class="mb-0">&copy; {{ date('Y') }} Bili Hub. All rights reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
  @if(Auth::user()->status === 'suspended')
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('suspensionModal'));
        modal.show();
      });
    </script>
  @endif
</body>
</html>
