<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seller Center - Bili Hub</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  @vite([
    'resources/css/shared.css',
    'resources/css/seller/dashboard.css',
    'resources/css/app.css',
    'resources/css/shared-layout.css',
    'resources/css/components/footer.css',
    'resources/css/seller/seller-layout.css',
    'resources/js/app.js',
    'resources/js/seller/layout.js',
  ])
  @stack('styles')
</head>
<body>
  <!-- Mobile Top Navbar -->
  <nav class="navbar navbar-light bg-white d-lg-none border-bottom">
    <div class="container-fluid px-3">
      <button class="btn btn-light" type="button" id="mobileSidebarToggle" aria-label="Toggle navigation">
        <i class="bi bi-list fs-5"></i>
      </button>
      <a class="navbar-brand" href="{{ route('home') }}">
        <img src="{{ asset('images/bilihublogo.png') }}" alt="BiliHub" style="height: 42px; width: auto;">
      </a>
      <div class="dropdown ms-auto">
        <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
          @if(Auth::user()->logo_url)
              <img src="{{ Auth::user()->logo_url }}" alt="Logo" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
          @else
              <i class="bi bi-person-circle fs-4"></i>
          @endif
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="{{ route('seller.account') }}">Account</a></li>
          <li>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
              @csrf
              <button class="dropdown-item" type="submit">Logout</button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="d-flex">
    <!-- Sidebar -->
    <nav class="seller-sidebar" id="sellerSidebar">
      <div class="sidebar-body d-flex flex-column h-100">
        <div class="sidebar-header p-4 border-bottom">
          <a href="{{ route('home') }}" class="d-flex align-items-center gap-2 text-decoration-none">
            <img src="{{ asset('images/bilihublogo.png') }}" alt="BiliHub" style="height: 36px; width: auto;">
            <span class="fs-5 fw-bold" style="color: var(--color-deep-purple);">Seller Center</span>
          </a>
        </div>

        <ul class="sidebar-nav flex-fill px-3 py-3">
          <li class="sidebar-item {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
            <a href="{{ route('seller.dashboard') }}" class="sidebar-link">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
          </li>
          <li class="sidebar-item {{ request()->routeIs('seller.products*') ? 'active' : '' }}">
            <a href="{{ route('seller.products') }}" class="sidebar-link">
              <i class="bi bi-box"></i> Products
            </a>
          </li>
          <li class="sidebar-item {{ request()->routeIs('seller.orders*') ? 'active' : '' }}">
            <a href="{{ route('seller.orders') }}" class="sidebar-link">
              <i class="bi bi-receipt"></i> Orders
            </a>
          </li>
          <li class="sidebar-item {{ request()->routeIs('seller.notifications') ? 'active' : '' }}">
            <a href="{{ route('seller.notifications') }}" class="sidebar-link">
              <i class="bi bi-bell"></i> Notifications
            </a>
          </li>
          <li class="sidebar-item {{ request()->routeIs('seller.messages*') ? 'active' : '' }}">
            <a href="{{ route('seller.messages.index') }}" class="sidebar-link">
              <i class="bi bi-chat-dots"></i> Messages
            </a>
          </li>

          <li class="sidebar-divider">Analytics</li>

          <li class="sidebar-item {{ request()->routeIs('seller.reports') ? 'active' : '' }}">
            <a href="{{ route('seller.reports') }}" class="sidebar-link">
              <i class="bi bi-bar-chart"></i> Reports
            </a>
          </li>

          <li class="sidebar-divider">Management</li>

          <li class="sidebar-item {{ request()->routeIs('seller.account') ? 'active' : '' }}">
            <a href="{{ route('seller.account') }}" class="sidebar-link">
              <i class="bi bi-gear"></i> Settings
            </a>
          </li>
        </ul>

        <div class="sidebar-footer p-4 border-top">
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-link">
              <i class="bi bi-box-arrow-right"></i> Logout
            </button>
          </form>
        </div>
      </div>
    </nav>

    <!-- Sidebar overlay for mobile -->
    <div class="seller-sidebar-overlay" id="sellerSidebarOverlay"></div>

    <!-- Main Content -->
    <div class="seller-main">
      <!-- Top Header -->
      <header class="seller-header bg-white border-bottom px-4 py-3 d-none d-lg-block">
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="mb-0 fw-semibold" style="color: var(--color-text-dark);">
            @yield('page-title', 'Dashboard')
          </h5>

          <div class="d-flex align-items-center gap-3">
            <form class="d-none d-md-block" action="{{ route('seller.products') }}" method="GET">
              <div class="input-group input-group-sm" style="width: 240px;">
                <input type="search" name="search" class="form-control" placeholder="Search products..." value="{{ request('search') }}">
                <button class="btn btn-outline-secondary" type="submit">
                  <i class="bi bi-search"></i>
                </button>
              </div>
            </form>

            <a href="{{ route('seller.notifications') }}" class="position-relative text-decoration-none">
              <i class="bi bi-bell fs-5" style="color: var(--color-text-dark);"></i>
              @php
                $seller = Auth::user();
                $unreadNotifCount = \App\Models\Notification::where('user_id', $seller->id)->where('is_read', false)->count();
                $pendingOrderCount = \App\Models\Order::whereHas('items.product', function ($query) use ($seller) {
                  $query->where('user_id', $seller->id);
                })->whereIn('status', ['pending', 'processing'])->count();
                $totalUnread = $unreadNotifCount + $pendingOrderCount;
              @endphp
              @if($totalUnread > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill" style="background-color: var(--color-primary);">
                  {{ $totalUnread }}
                </span>
              @endif
            </a>

            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                @if(Auth::user()->logo_url)
                      <img src="{{ Auth::user()->logo_url }}"
                         alt="Shop Logo"
                         class="rounded-circle"
                         style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0;">
                @else
                  <i class="bi bi-person-circle fs-4" style="color: #6b7280;"></i>
                @endif
                <span class="d-none d-md-inline ms-2 fw-medium">{{ Auth::user()->name }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('seller.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                <li><a class="dropdown-item" href="{{ route('seller.products') }}"><i class="bi bi-box me-2"></i>Manage Inventory</a></li>
                <li><a class="dropdown-item" href="{{ route('seller.orders') }}"><i class="bi bi-receipt me-2"></i>Orders</a></li>
                <li><a class="dropdown-item" href="{{ route('seller.notifications') }}"><i class="bi bi-bell me-2"></i>Notifications</a></li>
                <li><a class="dropdown-item" href="{{ route('seller.messages.index') }}"><i class="bi bi-chat-dots me-2"></i>Messages</a></li>
                <li><a class="dropdown-item" href="{{ route('seller.account') }}"><i class="bi bi-shop me-2"></i>Account</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                  </form>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content Area -->
      <main class="seller-content">
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

      <!-- Footer -->
      <x-footer />
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')

</body>
</html>
