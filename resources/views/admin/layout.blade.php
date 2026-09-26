<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Bili Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  @vite([
    'resources/css/app.css',
    'resources/css/shared-layout.css',
    'resources/css/components/footer.css',
    'resources/css/admin/admin-layout.css',
    'resources/js/app.js',
  ])
  @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">
  <nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container-fluid px-4">
      <a class="navbar-brand fw-bold text-violet-500 d-flex align-items-center gap-2" href="{{ route('home') }}">
        <img src="{{ asset('images/bilihublogo.png') }}" alt="Bili Hub" style="height: 42px; width: auto;">
        <span class="fs-5">Bili Hub</span>
      </a>
      <span class="badge bg-violet-100 text-violet-600 px-3 py-2 rounded-pill">Admin Panel</span>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav ms-auto align-items-center gap-3">
          <li class="nav-item">
            <a class="nav-link position-relative" href="{{ route('admin.notifications.index') }}" title="Notifications">
              <i class="bi bi-bell fs-5"></i>
              @php
                $unreadCount = \App\Models\Notification::where('user_id', Auth::id())->where('is_read', false)->count();
              @endphp
              @if($unreadCount > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill cart-badge" style="font-size: 0.65rem; min-width: 18px; height: 18px;">
                  {{ $unreadCount }}
                </span>
              @endif
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="adminUserDropdown" role="button" data-bs-toggle="dropdown">
              <div class="rounded-full bg-violet-100 text-violet-600 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-person-fill"></i>
              </div>
              <span class="d-none d-md-inline fw-medium">{{ Auth::user()->name }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminUserDropdown">
              <li><h6 class="dropdown-header fw-semibold">{{ Auth::user()->email }}</h6></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2 text-violet-500"></i>Dashboard</a></li>
              <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2 text-slate-400"></i>Account Settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                  @csrf
                  <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                </form>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <div class="row">
      <nav id="adminSidebar" class="col-md-3 col-lg-2 d-md-block admin-sidebar collapse show">
        <div class="position-sticky pt-3">
          <div class="px-3 mb-3">
            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Main Menu</small>
          </div>
          <nav class="nav flex-column px-3 admin-nav">
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="nav-link {{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}" href="{{ route('admin.registrations.index') }}">
              <i class="bi bi-clipboard-check"></i> Registrations
            </a>
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
              <i class="bi bi-people"></i> Users
            <a class="nav-link {{ request()->routeIs('admin.buyers') ? 'active' : '' }}" href="{{ route('admin.buyers') }}">
  <i class="bi bi-person-check"></i> Buyers
</a>
            </a>
            <a class="nav-link {{ request()->routeIs('admin.sellers') ? 'active' : '' }}" href="{{ route('admin.sellers') }}">
              <i class="bi bi-shop"></i> Sellers
            </a>
            <a class="nav-link {{ request()->routeIs('admin.products') ? 'active' : '' }}" href="{{ route('admin.products') }}">
              <i class="bi bi-box"></i> Products
            </a>
            <a class="nav-link {{ request()->routeIs('admin.riders') ? 'active' : '' }}" href="{{ route('admin.riders') }}">
              <i class="bi bi-bicycle"></i> Riders
            </a>
            <a class="nav-link {{ request()->routeIs('admin.logistics.*') ? 'active' : '' }}" href="{{ route('admin.logistics.index') }}">
              <i class="bi bi-truck"></i> Logistics
            </a>
            <a class="nav-link {{ request()->routeIs('admin.shipments.*') ? 'active' : '' }}" href="{{ route('admin.shipments.index') }}">
              <i class="bi bi-box"></i> Shipments
            </a>
          </nav>

          <div class="px-3 mb-3 mt-4">
            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Monitoring</small>
          </div>
          <nav class="nav flex-column px-3 admin-nav">
            <a class="nav-link {{ request()->routeIs('admin.compliance.*') ? 'active' : '' }}" href="{{ route('admin.compliance.index') }}">
              <i class="bi bi-shield-check"></i> Compliance
            </a>
            <a class="nav-link {{ request()->routeIs('admin.complaints.*') ? 'active' : '' }}" href="{{ route('admin.complaints.index') }}">
              <i class="bi bi-exclamation-triangle"></i> Complaints
            </a>
            <a class="nav-link {{ request()->routeIs('admin.commissions.*') ? 'active' : '' }}" href="{{ route('admin.commissions.index') }}">
              <i class="bi bi-percent"></i> Commissions
            </a>
          </nav>

          <div class="px-3 mb-3 mt-4">
            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">System</small>
          </div>
          <nav class="nav flex-column px-3 admin-nav">
            <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.sales') }}">
              <i class="bi bi-bar-chart"></i> Reports
            </a>
            <a class="nav-link {{ request()->routeIs('admin.messages.*') ? 'active' : '' }}" href="{{ route('admin.messages.index') }}">
              <i class="bi bi-chat-dots"></i> Messages
            </a>
            <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">
              <i class="bi bi-gear"></i> Settings
            </a>
          </nav>
        </div>
      </nav>

      <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 d-flex flex-column flex-grow-1">
        @if(session('success'))
          <div class="alert alert-success alert-dismissible fade show rounded-2 border-0 shadow-sm">
            <div class="d-flex align-items-center">
              <i class="bi bi-check-circle-fill me-2"></i>
              {{ session('success') }}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show rounded-2 border-0 shadow-sm">
            <div class="d-flex align-items-center">
              <i class="bi bi-exclamation-circle-fill me-2"></i>
              {{ session('error') }}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif
        <div class="flex-grow-1">
          @yield('content')
        </div>

        <x-footer />
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
