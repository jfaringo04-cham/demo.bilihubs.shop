@extends('layouts.app')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-5">
          <div class="text-center mb-4">
            <i class="bi bi-shop text-sky-500 fs-1"></i>
            <h2 class="fw-bold mt-2">Welcome Back</h2>
            <p class="text-muted">Sign in to your Bili Hub account</p>
          </div>

          @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-2 border-0" role="alert">
              <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-2 border-0" role="alert">
              <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          @if(session('status'))
            <div class="alert alert-info alert-dismissible fade show rounded-2 border-0" role="alert">
              <i class="bi bi-info-circle me-1"></i> {{ session('status') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
              <label for="email" class="form-label fw-medium">Email Address</label>
              <input type="email" name="email" id="email" class="form-control rounded-xl" value="{{ old('email') }}" required autofocus>
              @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
              <label for="password" class="form-label fw-medium">Password</label>
              <input type="password" name="password" id="password" class="form-control rounded-xl" required>
              @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
              <label for="remember" class="form-check-label">Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-xl py-2.5">Sign In</button>
          </form>

          <div class="d-flex align-items-center my-4">
            <div class="border-top flex-grow-1"></div>
            <span class="mx-3 text-muted small">OR</span>
            <div class="border-top flex-grow-1"></div>
          </div>

          <div class="d-grid gap-2">
            <a href="{{ route('auth.google') }}" class="btn btn-outline-secondary rounded-xl py-2">
              <i class="bi bi-google me-2"></i>Continue with Google
            </a>
            <a href="{{ route('auth.facebook') }}" class="btn btn-outline-secondary rounded-xl py-2">
              <i class="bi bi-facebook me-2"></i>Continue with Facebook
            </a>
          </div>

          <p class="text-center mt-4 mb-0">
            Don't have an account? <a href="{{ route('register') }}" class="text-sky-500 fw-medium">Register</a> or <a href="{{ route('apply.rider') }}" class="text-sky-500 fw-medium">Apply as Rider</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
