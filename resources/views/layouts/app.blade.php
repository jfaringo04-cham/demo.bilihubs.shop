<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bili Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <style>
    body { font-family: 'Inter', sans-serif; }
    .navbar { border-bottom: 1px solid #e2e8f0; }
    .nav-link { font-weight: 500; color: #475569; }
    .nav-link:hover { color: #0ea5e9; }
    .btn-primary { background-color: #0ea5e9; border-color: #0ea5e9; }
    .btn-primary:hover { background-color: #0284c7; border-color: #0284c7; }
    .dropdown-menu { border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); }
    .badge { border-radius: 8px; }
    .form-control, .form-select { border-radius: 12px; border-color: #e2e8f0; }
    .form-control:focus, .form-select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
  </style>
</head>
<body class="bg-slate-50">
  <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container-fluid px-4">
      <!-- Logo -->
        @if(Auth::check() && Auth::user()->isLogisticOwner())
          <a class="navbar-brand fw-bold text-sky-500 d-flex align-items-center gap-2" href="{{ route('logistic.home') }}">
            <img src="{{ asset('images/bilihublogo.png') }}" alt="Speedy Express" style="height: 42px; width: auto;">
            <span class="fs-5">Speedy Express</span>
          </a>
        @else
          <a class="navbar-brand fw-bold text-sky-500 d-flex align-items-center gap-2" href="{{ route('home') }}">
            <img src="{{ asset('images/bilihublogo.png') }}" alt="Bili Hub" style="height: 42px; width: auto;">
            <span class="fs-5">Bili Hub</span>
          </a>
        @endif
      
      <!-- Toggler -->
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <!-- Left Links -->
        <ul class="navbar-nav me-auto">
          @if(Auth::check() && Auth::user()->isLogisticOwner())
            <li class="nav-item"><a class="nav-link" href="{{ route('logistic.home') }}"><i class="bi bi-house me-1"></i>Home</a></li>
          @else
            <li class="nav-item"><a class="nav-link" href="{{ route('home') }}"><i class="bi bi-house me-1"></i>Home</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('products.index') }}"><i class="bi bi-bag me-1"></i>Products</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#about"><i class="bi bi-info-circle me-1"></i>About</a></li>
          @endif
        </ul>

        <!-- Search -->
        @if(!Auth::check() || !Auth::user()->isLogisticOwner())
        <form class="d-flex search-container me-3" action="{{ route('products.index') }}" method="GET">
          <input class="form-control me-2" type="search" name="search" placeholder="Search products..." value="{{ request('search') }}">
          <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        @endif

        <!-- Right Actions -->
        <ul class="navbar-nav ms-auto align-items-center gap-2">
          @auth
            @php
              $unreadCount = \App\Models\Notification::where('user_id', Auth::id())->where('is_read', false)->count();
              $recentNotifications = \App\Models\Notification::where('user_id', Auth::id())->latest()->take(5)->get();
            @endphp
            <li class="nav-item dropdown">
              <a class="nav-link position-relative dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="bi bi-bell fs-5"></i>
                @if($unreadCount > 0)
                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; min-width: 18px; height: 18px;">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                  </span>
                @endif
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="min-width: 320px; max-width: 380px;">
                <li>
                  <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h6 class="mb-0 fw-semibold">Notifications</h6>
                    @if($unreadCount > 0)
                      <a href="{{ Auth::user()->isLogisticOwner() ? route('logistic.notifications') : (Auth::user()->isRider() ? route('rider.notifications') : (Auth::user()->isSeller() ? route('seller.notifications') : route('buyer.notifications.index'))) }}" class="btn btn-sm btn-link text-sky-500 p-0">Mark all read</a>
                    @endif
                  </div>
                </li>
                @if($recentNotifications->isNotEmpty())
                  @foreach($recentNotifications as $notification)
                    <li>
                      <a class="dropdown-item d-flex align-items-start gap-2 {{ !$notification->is_read ? 'bg-sky-50' : '' }}" href="{{ $notification->link ?? '#' }}" data-notification-id="{{ $notification->id }}">
                        <div class="flex-shrink-0 mt-1">
                          <i class="bi {{ $notification->type === 'order' ? 'bi-cart-check' : ($notification->type === 'delivery' ? 'bi-truck' : ($notification->type === 'shipment' ? 'bi-box-seam' : ($notification->type === 'product' ? 'bi-bag' : 'bi-bell'))) }} text-sky-500"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                          <div class="fw-medium small">{{ $notification->title }}</div>
                          <div class="small text-muted text-truncate">{{ $notification->message }}</div>
                          <div class="text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                        @if(!$notification->is_read)
                          <div class="flex-shrink-0">
                            <span class="badge bg-sky-500 rounded-pill" style="font-size: 0.6rem;">New</span>
                          </div>
                        @endif
                      </a>
                    </li>
                  @endforeach
                @else
                  <li>
                    <div class="dropdown-item text-center text-muted py-4">
                      <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                      <span>No notifications yet</span>
                    </div>
                  </li>
                @endif
                <li><hr class="dropdown-divider"></li>
                <li>
                  <a class="dropdown-item text-center text-sky-500 fw-medium" href="{{ Auth::user()->isLogisticOwner() ? route('logistic.notifications') : (Auth::user()->isRider() ? route('rider.notifications') : (Auth::user()->isSeller() ? route('seller.notifications') : route('buyer.notifications.index'))) }}">
                    View All Notifications
                  </a>
                </li>
              </ul>
            </li>

            @if(Auth::user()->isCustomer())
              <li class="nav-item">
                <a class="nav-link position-relative" href="{{ route('cart.index') }}" title="Shopping Cart">
                  <i class="bi bi-cart3 fs-5"></i>
                  @php
                    $cartCount = \App\Models\CartItem::where('user_id', Auth::id())->count();
                  @endphp
                  @if($cartCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; min-width: 18px; height: 18px;">
                      {{ $cartCount > 9 ? '9+' : $cartCount }}
                    </span>
                  @endif
                </a>
              </li>
            @endif

            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                <div class="rounded-full bg-sky-100 text-sky-600 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                  <i class="bi bi-person-fill"></i>
                </div>
                <span class="d-none d-md-inline fw-medium">{{ Auth::user()->name }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li><h6 class="dropdown-header fw-semibold">{{ Auth::user()->email }}</h6></li>
                <li><hr class="dropdown-divider"></li>
                
                @if(Auth::user()->isAdmin())
                  <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2 text-sky-500"></i>Admin Dashboard</a></li>
                  <li><hr class="dropdown-divider"></li>
                @endif

                @if(Auth::user()->isSeller())
                  <li><a class="dropdown-item" href="{{ route('seller.dashboard') }}"><i class="bi bi-shop me-2 text-sky-500"></i>Seller Panel</a></li>
                  <li><hr class="dropdown-divider"></li>
                @endif

                @if(Auth::user()->isRider())
                  <li><a class="dropdown-item" href="{{ route('rider.dashboard') }}"><i class="bi bi-bicycle me-2 text-sky-500"></i>Rider Panel</a></li>
                  <li><hr class="dropdown-divider"></li>
                @endif

                @if(Auth::user()->isLogisticOwner())
                  <li><a class="dropdown-item" href="{{ route('logistic.dashboard') }}"><i class="bi bi-truck me-2 text-sky-500"></i>Logistics Panel</a></li>
                  <li><a class="dropdown-item" href="{{ route('logistic.account') }}"><i class="bi bi-gear me-2 text-slate-400"></i>Account Settings</a></li>
                  <li><a class="dropdown-item" href="{{ route('logistic.messages.index') }}"><i class="bi bi-chat-dots me-2 text-slate-400"></i>Messages</a></li>
                  <li><hr class="dropdown-divider"></li>
                @endif

                @if(Auth::user()->isCustomer())
                  <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2 text-slate-400"></i>Profile</a></li>
                  <li><a class="dropdown-item" href="{{ route('orders.index') }}"><i class="bi bi-receipt me-2 text-slate-400"></i>Orders</a></li>
                  <li><a class="dropdown-item" href="{{ route('buyer.addresses.index') }}"><i class="bi bi-geo me-2 text-slate-400"></i>Addresses</a></li>
                  <li><a class="dropdown-item" href="{{ route('buyer.messages.index') }}"><i class="bi bi-chat-dots me-2 text-slate-400"></i>Messages</a></li>
                  <li><a class="dropdown-item" href="{{ route('buyer.support.index') }}"><i class="bi bi-chat-left-text me-2 text-slate-400"></i>Support</a></li>
                  <li><a class="dropdown-item" href="{{ route('buyer.help') }}"><i class="bi bi-question-circle me-2 text-slate-400"></i>Help</a></li>
                @else
                  <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2 text-slate-400"></i>Profile</a></li>
                @endif

                <li><hr class="dropdown-divider"></li>
                <li>
                  <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                  </form>
                </li>
              </ul>
            </li>

            @else
            <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
            <li class="nav-item"><a class="btn btn-primary btn-sm rounded-xl px-4" href="{{ route('register') }}" style="text-decoration: none;">Register</a></li>
          @endauth
        </ul>
      </div>
    </div>
  </nav>

  <main class="min-vh-100">
    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show rounded-2 border-0 shadow-sm mx-4 mt-4">
        <div class="d-flex align-items-center">
          <i class="bi bi-check-circle-fill me-2"></i>
          {{ session('success') }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger alert-dismissible fade show rounded-2 border-0 shadow-sm mx-4 mt-4">
        <div class="d-flex align-items-center">
          <i class="bi bi-exclamation-circle-fill me-2"></i>
          {{ session('error') }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    @yield('content')
  </main>

  <footer class="bg-white border-top border-slate-200 py-4 mt-auto">
    <div class="container text-center">
      @if(Auth::check() && Auth::user()->isLogisticOwner())
        <p class="mb-0 text-muted small">&copy; {{ date('Y') }} Speedy Express. All rights reserved.</p>
      @else
        <p class="mb-0 text-muted small">&copy; {{ date('Y') }} Bili Hub. All rights reserved.</p>
      @endif
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
  <script>
    // Mark notification as read when clicked
    document.addEventListener('click', function(e) {
      const notificationLink = e.target.closest('[data-notification-id]');
      if (notificationLink) {
        const notificationId = notificationLink.getAttribute('data-notification-id');
        if (notificationId) {
          fetch('{{ route('notifications.read', ['notification' => ':id']) }}'.replace(':id', notificationId), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
          }).then(response => {
            if (response.ok) {
              // Update badge count
              const badge = document.querySelector('#notificationsDropdown .badge');
              if (badge) {
                const currentCount = parseInt(badge.textContent);
                if (currentCount > 1) {
                  badge.textContent = currentCount - 1;
                } else {
                  badge.remove();
                }
              }
              // Remove "New" badge from clicked notification
              const newBadge = notificationLink.querySelector('.badge.bg-sky-500');
              if (newBadge) newBadge.remove();
              // Remove highlight
              notificationLink.classList.remove('bg-sky-50');
            }
          });
        }
      }
    });

    // Also mark as read when dropdown opens (mark all visible)
    document.addEventListener('shown.bs.dropdown', function(e) {
      if (e.target.id === 'notificationsDropdown') {
        // Optional: mark all as read via AJAX if needed
      }
    });
  </script>
</body>
</html>
