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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  @vite([
    'resources/css/shared.css',
    'resources/css/app.css',
    'resources/css/shared-layout.css',
    'resources/css/components/footer.css',
    'resources/js/app.js',
  ])
  @stack('styles')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="notification-read-url" content="{{ route('notifications.read', ['notification' => '__id__']) }}">
</head>
<body class="bg-slate-50">
  <div class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
      <div class="container-fluid px-4">
      <!-- Logo -->
        @if(Auth::check() && Auth::user()->isLogisticOwner())
          <a class="navbar-brand fw-bold text-violet-500 d-flex align-items-center gap-2" href="{{ route('logistic.home') }}">
            <img src="{{ asset('images/bilihublogo.png') }}" alt="Speedy Express" style="height: 42px; width: auto;">
            <span class="fs-5">Speedy Express</span>
          </a>
        @else
          <a class="navbar-brand fw-bold text-violet-500 d-flex align-items-center gap-2" href="{{ route('home') }}">
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
              <li class="nav-item"><a class="nav-link" href="{{ route('logistic.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
              <li class="nav-item"><a class="nav-link" href="{{ route('logistic.riders') }}"><i class="bi bi-people me-1"></i>Riders</a></li>
              <li class="nav-item"><a class="nav-link" href="{{ route('logistic.shipments') }}"><i class="bi bi-box-seam me-1"></i>Shipments</a></li>
              <li class="nav-item"><a class="nav-link" href="{{ route('logistic.sorting-area') }}"><i class="bi bi-clipboard-data me-1"></i>Sorting Area</a></li>
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
                      <a href="{{ Auth::user()->isLogisticOwner() ? route('logistic.notifications') : (Auth::user()->isRider() ? route('rider.notifications') : (Auth::user()->isSeller() ? route('seller.notifications') : route('buyer.notifications.index'))) }}" class="btn btn-sm btn-link text-violet-500 p-0">Mark all read</a>
                    @endif
                  </div>
                </li>
                @if($recentNotifications->isNotEmpty())
                  @foreach($recentNotifications as $notification)
                    <li>
                      <a class="dropdown-item d-flex align-items-start gap-2 {{ !$notification->is_read ? 'bg-violet-50' : '' }}" href="{{ $notification->link ?? '#' }}" data-notification-id="{{ $notification->id }}">
                        <div class="flex-shrink-0 mt-1">
                          <i class="bi {{ $notification->type === 'order' ? 'bi-cart-check' : ($notification->type === 'delivery' ? 'bi-truck' : ($notification->type === 'shipment' ? 'bi-box-seam' : ($notification->type === 'product' ? 'bi-bag' : 'bi-bell'))) }} text-violet-500"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                          <div class="fw-medium small">{{ $notification->title }}</div>
                          <div class="small text-muted text-truncate">{{ $notification->message }}</div>
                          <div class="text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                        @if(!$notification->is_read)
                          <div class="flex-shrink-0">
                            <span class="badge bg-violet-500 rounded-pill" style="font-size: 0.6rem;">New</span>
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
                  <a class="dropdown-item text-center text-violet-500 fw-medium" href="{{ Auth::user()->isLogisticOwner() ? route('logistic.notifications') : (Auth::user()->isRider() ? route('rider.notifications') : (Auth::user()->isSeller() ? route('seller.notifications') : route('buyer.notifications.index'))) }}">
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
                <div class="rounded-full bg-violet-100 text-violet-600 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                  <i class="bi bi-person-fill"></i>
                </div>
                <span class="d-none d-md-inline fw-medium">{{ Auth::user()->name }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li><h6 class="dropdown-header fw-semibold">{{ Auth::user()->email }}</h6></li>
                <li><hr class="dropdown-divider"></li>
                
                @if(Auth::user()->isAdmin())
                  <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2 text-violet-500"></i>Admin Dashboard</a></li>
                  <li><hr class="dropdown-divider"></li>
                @endif

                @if(Auth::user()->isSeller())
                  <li><a class="dropdown-item" href="{{ route('seller.dashboard') }}"><i class="bi bi-shop me-2 text-violet-500"></i>Seller Panel</a></li>
                  <li><hr class="dropdown-divider"></li>
                @endif

                @if(Auth::user()->isRider())
                  <li><a class="dropdown-item" href="{{ route('rider.dashboard') }}"><i class="bi bi-bicycle me-2 text-violet-500"></i>Rider Panel</a></li>
                  <li><hr class="dropdown-divider"></li>
                @endif

                @if(Auth::user()->isLogisticOwner())
                  <li><a class="dropdown-item" href="{{ route('logistic.dashboard') }}"><i class="bi bi-truck me-2 text-violet-500"></i>Logistics Panel</a></li>
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

  <main class="flex-grow-1">
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

  <x-footer />
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')

</body>
</html>
