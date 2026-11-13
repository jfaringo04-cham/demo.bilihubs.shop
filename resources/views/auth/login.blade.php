@extends('layouts.app')

@push('styles')
@vite('resources/css/auth/login.css')
@endpush

@section('content')
<div class="auth-page auth-page--login">
    <div class="auth-container">

        <!-- Left Visual Panel -->
        <div class="auth-visual">
            <img
                src="{{ asset('images/hero-login.webp') }}"
                alt="BiliHub Marketplace"
                class="auth-visual-image"
            >

            <div class="auth-visual-overlay">
                <h2 class="text-white fw-bold mb-1" style="font-size: 1.5rem;">
                    Shop. Sell. Deliver.
                </h2>

                <p class="text-white small mb-0">
                    Everything you need in one marketplace.
                </p>
            </div>
        </div>

        <!-- Right Login Form -->
        <div class="auth-form-panel">

            <div class="text-center mb-2 d-none d-lg-block">
                <img
                    src="{{ asset('images/bilihublogo.png') }}"
                    alt="BiliHub"
                    class="auth-logo"
                >
            </div>

            <div class="text-center mb-2 d-lg-none">
                <img
                    src="{{ asset('images/bilihublogo.png') }}"
                    alt="BiliHub"
                    class="auth-logo-mobile"
                >
            </div>

            <h2
                class="fw-bold text-center mb-1"
                style="color: var(--color-text-dark);"
            >
                Welcome Back
            </h2>

            <p class="text-center text-muted small mb-3">
                Sign in to your BiliHub account.
            </p>

            @if(session('status'))
                <div
                    class="alert alert-success alert-dismissible fade show rounded-2 border-0"
                    role="alert"
                >
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        {{ session('status') }}
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>
            @endif

            @if(session('error'))
                <div
                    class="alert alert-danger alert-dismissible fade show rounded-2 border-0"
                    role="alert"
                >
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        {{ session('error') }}
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('login') }}"
                id="loginForm"
            >
                @csrf

                <!-- Email -->
                <div class="mb-3">
                    <label
                        for="email"
                        class="form-label fw-medium"
                        style="color: #334159;"
                    >
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-control auth-input @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        placeholder="Enter your email address"
                        autocomplete="email"
                        required
                        autofocus
                    >

                    @error('email')
                        <div class="invalid-feedback d-block mt-1 small">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label
                        for="password"
                        class="form-label fw-medium"
                        style="color: #334159;"
                    >
                        Password
                    </label>

                    <div class="input-group">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="form-control auth-input pe-5 @error('password') is-invalid @enderror"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                            minlength="8"
                        >

                        <button
                            type="button"
                            class="btn btn-outline-secondary toggle-password"
                            id="togglePassword"
                            aria-label="Toggle password visibility"
                        >
                            <i
                                class="bi bi-eye-slash"
                                id="passwordToggleIcon"
                            ></i>
                        </button>
                    </div>

                    @error('password')
                        <div class="invalid-feedback d-block mt-1 small">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Remember Me + Forgot Password -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

                    <div class="d-flex align-items-center">
                        <input
                            type="checkbox"
                            name="remember"
                            id="remember"
                            class="form-check-input me-2"
                            {{ old('remember') ? 'checked' : '' }}
                        >

                        <label
                            for="remember"
                            class="form-check-label mb-0"
                            style="color: #334159;"
                        >
                            Remember me
                        </label>
                    </div>

                    <a
                        href="{{ route('password.request') }}"
                        class="small fw-medium"
                        style="
                            color: var(--color-deep-purple);
                            text-decoration: none;
                        "
                    >
                        Forgot Password?
                    </a>
                </div>

                @if($errors->has('email') && !$errors->has('password'))
                    <div
                        class="alert alert-danger alert-dismissible fade show rounded-2 small mb-3"
                        role="alert"
                    >
                        <i class="bi bi-exclamation-circle me-1"></i>

                        {{ $errors->first('email') }}

                        <button
                            type="button"
                            class="btn-close btn-sm"
                            data-bs-dismiss="alert"
                            aria-label="Close"
                        ></button>
                    </div>
                @endif

                <!-- Sign In -->
                <button
                    type="submit"
                    class="btn btn-primary w-100 rounded-xl py-2 fw-semibold"
                    id="loginButton"
                >
                    <span class="btn-text">
                        Sign In
                    </span>

                    <span
                        class="spinner-border spinner-border-sm d-none"
                        role="status"
                        aria-hidden="true"
                    ></span>
                </button>

                <!-- Divider -->
                <div class="d-flex align-items-center my-3">
                    <div class="border-top flex-grow-1"></div>

                    <span class="mx-3 text-muted small">
                        OR
                    </span>

                    <div class="border-top flex-grow-1"></div>
                </div>

                <!-- Social Login -->
                <div class="d-grid gap-2 mb-3">

                    <a
                        href="{{ route('auth.google') }}"
                        class="btn btn-outline-secondary rounded-xl py-2 d-flex align-items-center justify-content-center gap-2"
                    >
                        <i class="bi bi-google"></i>
                        Continue with Google
                    </a>

                    <a
                        href="{{ route('auth.facebook') }}"
                        class="btn btn-outline-secondary rounded-xl py-2 d-flex align-items-center justify-content-center gap-2"
                    >
                        <i class="bi bi-facebook"></i>
                        Continue with Facebook
                    </a>
                </div>

                <!-- Registration Links -->
                <div class="text-center">

                    <p class="mb-1 small">
                        New to BiliHub?

                        <a
                            href="{{ route('register') }}"
                            class="fw-semibold"
                            style="color: var(--color-deep-purple);"
                        >
                            Create an account
                        </a>
                    </p>

                    <p class="mb-0 small">
                        Want to deliver with us?

                        <a
                            href="{{ route('apply.rider') }}"
                            class="fw-semibold"
                            style="color: var(--color-deep-purple);"
                        >
                            Become a Rider
                        </a>
                    </p>

                </div>

            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/auth/login.js')
@endpush