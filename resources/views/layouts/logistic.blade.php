@props(['title' => 'Speedy Express'])

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title }} - Speedy Express</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  @vite([
    'resources/css/logistics/logistics-layout.css',
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/js/logistics/layout.js',
  ])
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @stack('styles')
</head>
<body class="bg-slate-50">
<div class="logistic-layout">
  <!-- Sidebar -->
  <aside class="logistic-sidebar" id="logisticSidebar">
    <div class="sidebar-top">
      <a href="{{ route('logistic.dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
        @include('components.speedy-logo', ['height' => 30, 'showTagline' => false])
      </a>
    </div>

    <nav class="sidebar-menu">
      <div class="sidebar-label">MAIN</div>

      <a href="{{ route('logistic.dashboard') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.dashboard') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-speedometer2"></i></span>
        <span>Dashboard</span>
      </a>
      <a href="{{ route('logistic.home') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.home') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-house"></i></span>
        <span>Home</span>
      </a>
      <a href="{{ route('logistic.riders') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.riders') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-people"></i></span>
        <span>Riders</span>
      </a>
      <a href="{{ route('logistic.shipments') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.shipments*') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-box-seam"></i></span>
        <span>Shipments</span>
      </a>
      <a href="{{ route('logistic.sorting-area') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.sorting-area') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-clipboard-data"></i></span>
        <span>Sorting Area</span>
      </a>

      <div class="sidebar-divider"></div>

      <div class="sidebar-label">ANALYTICS</div>
      <a href="{{ route('logistic.reports') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.reports') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-graph-up-arrow"></i></span>
        <span>Reports</span>
      </a>

      <div class="sidebar-divider"></div>

      <div class="sidebar-label">MANAGEMENT</div>
      <a href="{{ route('logistic.hubs') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.hubs*') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-geo-alt"></i></span>
        <span>Hubs</span>
      </a>
      <a href="{{ route('logistic.applications') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.applications') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-person-check"></i></span>
        <span>Applications</span>
      </a>
      <a href="{{ route('logistic.notifications') }}" class="sidebar-item d-flex align-items-center {{ request()->routeIs('logistic.notifications') ? 'active' : '' }}">
        <span class="sidebar-icon"><i class="bi bi-bell"></i></span>
        <span>Notifications</span>
      </a>
    </nav>

    <div class="sidebar-bottom">
      <div class="sidebar-company">
        <span class="company-icon"><i class="bi bi-truck"></i></span>
        <div>
          <div class="company-text">{{ $logistic->company_name ?? 'Speedy Express' }}</div>
          <div class="company-subtext">Logistics Partner on BiliHub</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- Main -->
  <div class="logistic-main">
    <!-- Top Header -->
    <header class="logistic-top-header">
      <div class="header-left">
        <div class="header-title">Logistics Operations</div>
        <div class="header-subtitle">{{ $logistic->company_name ?? 'Speedy Express' }} Dashboard</div>
      </div>
      <div class="header-right">
        <form class="d-flex" role="search">
          <div class="search-box">
            <input type="search" placeholder="Search shipments, riders..." name="search" value="{{ request('search') }}">
            <i class="bi bi-search"></i>
          </div>
        </form>
        <a href="{{ route('logistic.notifications') }}" class="notification-bell" title="Notifications">
          <i class="bi bi-bell fs-5"></i>
          @php
            $unreadCount = \App\Models\Notification::where('user_id', Auth::id())->where('is_read', false)->count();
          @endphp
          @if($unreadCount > 0)
            <span class="notification-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
          @endif
        </a>
        <div class="dropdown">
          <a href="#" class="user-dropdown" id="logisticUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="user-avatar">
              <i class="bi bi-person-fill"></i>
            </div>
            <span class="d-none d-md-inline fw-medium">{{ Auth::user()->name }}</span>
            <i class="bi bi-chevron-down fs-8 text-muted"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="logisticUserDropdown">
            <li><h6 class="dropdown-header fw-semibold">{{ Auth::user()->email }}</h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('logistic.account') }}"><i class="bi bi-gear me-2"></i>Account Settings</a></li>
            <li><a class="dropdown-item" href="{{ route('logistic.messages.index') }}"><i class="bi bi-chat-dots me-2"></i>Messages</a></li>
            <li><a class="dropdown-item" href="{{ route('logistic.hubs') }}"><i class="bi bi-geo-alt me-2"></i>Hubs</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
              </form>
            </li>
          </ul>
        </div>
        <button class="logistic-sidebar-toggle d-lg-none" type="button" id="sidebarToggle">
          <i class="bi bi-list fs-4"></i>
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="logistic-content">
      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-2 border-0">
          <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-2 border-0">
          <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            {{ session('error') }}
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @yield('content')
    </main>

    <!-- Footer -->
    <footer class="logistic-footer">
      <div class="logistic-footer-top">
        <div class="container-fluid px-4 py-3">
          <div class="row g-3 align-items-start">
            <div class="col-lg-3">
              @include('components.speedy-logo', ['height' => 28])
              <p class="text-muted small mb-0 mt-1">
                Reliable logistics connecting sellers, buyers, and communities.
              </p>
            </div>
            <div class="col-6 col-lg-2">
              <h6 class="footer-heading-small">LOGISTICS</h6>
              <ul class="list-unstyled">
                <li><a href="{{ route('logistic.dashboard') }}" class="footer-link-small">Dashboard</a></li>
                <li><a href="{{ route('logistic.riders') }}" class="footer-link-small">Riders</a></li>
                <li><a href="{{ route('logistic.shipments') }}" class="footer-link-small">Shipments</a></li>
                <li><a href="{{ route('logistic.sorting-area') }}" class="footer-link-small">Sorting Area</a></li>
                <li><a href="{{ route('logistic.reports') }}" class="footer-link-small">Reports</a></li>
              </ul>
            </div>
            <div class="col-6 col-lg-2">
              <h6 class="footer-heading-small">COMPANY</h6>
              <ul class="list-unstyled">
                <li><a href="{{ route('logistic.about') }}" class="footer-link-small">About Speedy Express</a></li>
                <li><a href="#" class="footer-link-small">How It Works</a></li>
                <li><a href="#" class="footer-link-small">Contact Us</a></li>
              </ul>
            </div>
            <div class="col-6 col-lg-2">
              <h6 class="footer-heading-small">SUPPORT</h6>
              <ul class="list-unstyled">
                <li><a href="#" class="footer-link-small">Help Center</a></li>
                <li><a href="#" class="footer-link-small">FAQs</a></li>
              </ul>
            </div>
            <div class="col-6 col-lg-2">
              <h6 class="footer-heading-small">ACCOUNT</h6>
              <ul class="list-unstyled">
                <li><a href="{{ route('logistic.account') }}" class="footer-link-small">Account Settings</a></li>
                <li><a href="{{ route('logistic.notifications') }}" class="footer-link-small">Notifications</a></li>
                <li><a href="{{ route('logistic.hubs') }}" class="footer-link-small">My Hubs</a></li>
                <li>
                  <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="footer-link-small p-0 bg-transparent border-0" style="cursor: pointer;">Logout</button>
                  </form>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div class="logistic-footer-bottom">
        <div class="container-fluid px-4 py-2">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 small">
            <span class="text-muted">&copy; {{ date('Y') }} Speedy Express. All rights reserved.</span>
            <div class="d-flex gap-2">
              <a href="#" class="footer-link-small text-muted">Privacy Policy</a>
              <span class="text-muted">·</span>
              <a href="#" class="footer-link-small text-muted">Terms & Conditions</a>
              <span class="text-muted ms-2 d-none d-md-inline">Logistics Partner on BiliHub</span>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')

</body>
</html>
