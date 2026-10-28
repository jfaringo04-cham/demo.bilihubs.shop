@extends('layouts.app')

@if($user->isCustomer())
@push('styles')
@vite('resources/css/buyer/profile.css')
@endpush
@endif

@section('content')

@if($user->isCustomer())

@php
$initials = collect(preg_split('/\s+/', trim($user->name)))
->filter()
->take(2)
->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
->implode('');

$roleName = $user->roles
->pluck('name')
->map(fn ($role) => ucfirst($role))
->implode(', ');

$profilePhotoUrl = null;

if ($user->profile_photo_path) {
try {
/*
|--------------------------------------------------------------------------
| Primary source: S3
|--------------------------------------------------------------------------
*/
if (\Illuminate\Support\Facades\Storage::disk('s3')
->exists($user->profile_photo_path)) {

$profilePhotoUrl =
\Illuminate\Support\Facades\Storage::disk('s3')
->url($user->profile_photo_path);

/*
|--------------------------------------------------------------------------
| Legacy local fallback
|--------------------------------------------------------------------------
*/
} elseif (
\Illuminate\Support\Facades\Storage::disk('public')
->exists($user->profile_photo_path)
) {
$profilePhotoUrl =
\Illuminate\Support\Facades\Storage::disk('public')
->url($user->profile_photo_path);
}
} catch (\Throwable $e) {
report($e);
$profilePhotoUrl = null;
}
}
@endphp

<div class="container py-4 py-lg-5 buyer-profile-page">

  <div class="profile-hero mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

      <div>
        <span class="profile-role-badge mb-2">
          <i class="bi bi-person-check"></i>
          Buyer Account
        </span>

        <h1 class="h3 mb-1 fw-bold">
          My Profile
        </h1>

        <p class="text-secondary mb-0">
          Manage your account details and delivery information.
        </p>
      </div>

      <a
        href="{{ route('buyer.addresses.index') }}"
        class="btn btn-outline-primary rounded-3 px-3">
        <i class="bi bi-geo-alt me-1"></i>
        Manage Addresses
      </a>

    </div>
  </div>

  @if(session('success'))
  <div class="alert alert-success border-0 rounded-3">
    <i class="bi bi-check-circle me-2"></i>
    {{ session('success') }}
  </div>
  @endif

  <div class="row g-4">

    {{-- LEFT PROFILE CARD --}}
    <div class="col-lg-4">

      <div class="profile-card">

        @if($profilePhotoUrl)

        <img
          src="{{ $profilePhotoUrl }}"
          alt="{{ $user->name }}"
          class="profile-avatar">

        @else

        <div class="profile-avatar-fallback">
          {{ $initials ?: 'BH' }}
        </div>

        @endif

        <h2 class="h5 fw-bold mt-3 mb-1">
          {{ $user->name }}
        </h2>

        <p class="text-secondary small mb-3">
          {{ $user->email }}
        </p>

        <div class="d-flex justify-content-center gap-2 flex-wrap mb-4">

          <span class="profile-role-badge">
            <i class="bi bi-person-badge"></i>
            {{ $roleName ?: 'Buyer' }}
          </span>

          <span class="profile-status-badge">
            <i class="bi bi-check-circle-fill"></i>
            {{ ucfirst($user->status ?? 'active') }}
          </span>

        </div>

        <div class="row g-2 text-center">

          <div class="col-6">
            <div class="profile-stat">
              <strong>{{ $orderCount }}</strong>
              <span>Orders</span>
            </div>
          </div>

          <div class="col-6">
            <div class="profile-stat">
              <strong>{{ $addressCount }}</strong>
              <span>Addresses</span>
            </div>
          </div>

        </div>

        <div class="border-top mt-4 pt-4 text-start small text-secondary">

          <div class="mb-2">
            <i class="bi bi-telephone me-2 text-primary"></i>

            {{ $user->phone ?: 'No phone number yet' }}
          </div>

          <div>
            <i class="bi bi-envelope me-2 text-primary"></i>

            {{ $user->email }}
          </div>

        </div>

      </div>
    </div>

    {{-- RIGHT CONTENT --}}
    <div class="col-lg-8">

      {{-- PERSONAL INFORMATION --}}
      <form
        method="POST"
        action="{{ route('profile.update') }}"
        enctype="multipart/form-data"
        class="profile-form-card mb-4">

        @csrf
        @method('PATCH')

        <div class="mb-4">

          <h2 class="h5 profile-section-title">
            Personal Information
          </h2>

          <p class="profile-section-subtitle mb-0">
            Update the details associated with your BiliHub account.
          </p>

        </div>

        <div class="row g-3">

          <div class="col-md-6">

            <label
              for="name"
              class="form-label fw-semibold">
              Full Name
            </label>

            <input
              type="text"
              name="name"
              id="name"
              class="form-control @error('name') is-invalid @enderror"
              value="{{ old('name', $user->name) }}"
              required>

            @error('name')
            <div class="invalid-feedback">
              {{ $message }}
            </div>
            @enderror

          </div>

          <div class="col-md-6">

            <label
              for="email"
              class="form-label fw-semibold">
              Email Address
            </label>

            <input
              type="email"
              name="email"
              id="email"
              class="form-control @error('email') is-invalid @enderror"
              value="{{ old('email', $user->email) }}"
              required>

            @error('email')
            <div class="invalid-feedback">
              {{ $message }}
            </div>
            @enderror

          </div>

          <div class="col-md-6">

            <label
              for="phone"
              class="form-label fw-semibold">
              Phone Number
            </label>

            <input
              type="text"
              name="phone"
              id="phone"
              class="form-control @error('phone') is-invalid @enderror"
              value="{{ old('phone', $user->phone) }}"
              placeholder="09XXXXXXXXX">

            @error('phone')
            <div class="invalid-feedback">
              {{ $message }}
            </div>
            @enderror

          </div>

          <div class="col-md-6">

            <label class="form-label fw-semibold">
              Account Role
            </label>

            <input
              type="text"
              class="form-control bg-light"
              value="{{ $roleName ?: 'Buyer' }}"
              disabled>

          </div>

          {{-- PROFILE PHOTO --}}
          <div class="col-12">

            <label
              for="profile_photo"
              class="form-label fw-semibold">
              Profile Photo
            </label>

            <div class="profile-photo-input">

              <input
                type="file"
                name="profile_photo"
                id="profile_photo"
                class="form-control @error('profile_photo') is-invalid @enderror"
                accept="image/jpeg,image/png,image/webp">

              <div class="form-text">
                JPG, PNG, or WEBP. Maximum file size: 2 MB.
              </div>

              @error('profile_photo')
              <div class="text-danger small mt-1">
                {{ $message }}
              </div>
              @enderror

              @if($user->profile_photo_path)

              <div class="form-check mt-2">

                <input
                  class="form-check-input"
                  type="checkbox"
                  name="remove_profile_photo"
                  value="1"
                  id="remove_profile_photo">

                <label
                  class="form-check-label small"
                  for="remove_profile_photo">
                  Remove current profile photo
                </label>

              </div>

              @endif

            </div>

          </div>

        </div>

        <div class="d-flex justify-content-end mt-4">

          <button
            type="submit"
            class="profile-primary-btn">
            <i class="bi bi-check2-circle me-1"></i>
            Save Changes
          </button>

        </div>

      </form>

      {{-- CHANGE PASSWORD --}}
      <form
        method="POST"
        action="{{ route('profile.password.update') }}"
        class="profile-form-card profile-password-card mb-4">

        @csrf
        @method('PATCH')

        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">

          <div>

            <h2 class="h5 profile-section-title">
              Change Password
            </h2>

            <p class="profile-section-subtitle mb-0">
              Use your current password to set a new password for your account.
            </p>

          </div>

          <span class="profile-security-icon">
            <i class="bi bi-shield-lock"></i>
          </span>

        </div>

        @if(session('password_success'))

        <div class="alert alert-success border-0 rounded-3">

          <i class="bi bi-check-circle me-2"></i>

          {{ session('password_success') }}

        </div>

        @endif

        @if($errors->passwordUpdate->any())

        <div class="alert alert-danger border-0 rounded-3">

          <div class="fw-semibold mb-1">
            Please check your password details.
          </div>

          <ul class="mb-0 ps-3 small">

            @foreach($errors->passwordUpdate->all() as $error)
            <li>{{ $error }}</li>
            @endforeach

          </ul>

        </div>

        @endif

        <div class="row g-3">

          <div class="col-12">

            <label
              for="current_password"
              class="form-label fw-semibold">
              Current Password
            </label>

            <input
              type="password"
              name="current_password"
              id="current_password"
              class="form-control @error('current_password', 'passwordUpdate') is-invalid @enderror"
              autocomplete="current-password"
              required>

          </div>

          <div class="col-md-6">

            <label
              for="password"
              class="form-label fw-semibold">
              New Password
            </label>

            <input
              type="password"
              name="password"
              id="password"
              class="form-control @error('password', 'passwordUpdate') is-invalid @enderror"
              autocomplete="new-password"
              minlength="8"
              required>

            <div class="form-text">
              Use at least 8 characters.
            </div>

          </div>

          <div class="col-md-6">

            <label
              for="password_confirmation"
              class="form-label fw-semibold">
              Confirm New Password
            </label>

            <input
              type="password"
              name="password_confirmation"
              id="password_confirmation"
              class="form-control"
              autocomplete="new-password"
              minlength="8"
              required>

          </div>

        </div>

        <div class="d-flex justify-content-end mt-4">

          <button
            type="submit"
            class="profile-primary-btn">
            <i class="bi bi-key me-1"></i>
            Update Password
          </button>

        </div>

      </form>

      {{-- DEFAULT DELIVERY ADDRESS --}}
      <div class="profile-address-card">

        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">

          <div>

            <h2 class="h5 profile-section-title">
              Default Delivery Address
            </h2>

            <p class="profile-section-subtitle mb-0">
              Your primary address for checkout and deliveries.
            </p>

          </div>

          <span class="profile-address-icon">
            <i class="bi bi-house-door"></i>
          </span>

        </div>

        @if($defaultAddress)

        <div class="profile-address-text">

          <div class="fw-semibold text-dark mb-1">
            {{ $defaultAddress->label ?: 'Default Address' }}
          </div>

          <div>
            {{ $defaultAddress->address_line1 }}
          </div>

          @if($defaultAddress->address_line2)

          <div>
            {{ $defaultAddress->address_line2 }}
          </div>

          @endif

          <div>
            {{
                  collect([
                      $defaultAddress->barangay,
                      $defaultAddress->city,
                      $defaultAddress->province,
                      $defaultAddress->postal_code
                  ])
                  ->filter()
                  ->implode(', ')
              }}
          </div>

          @if($defaultAddress->phone)

          <div class="mt-2">
            <i class="bi bi-telephone me-1"></i>
            {{ $defaultAddress->phone }}
          </div>

          @endif

        </div>

        @else

        <div class="alert alert-light border rounded-3 mb-0">

          <div class="fw-semibold mb-1">
            No delivery address saved yet.
          </div>

          <div class="small text-secondary mb-3">
            Add an address so checkout and delivery are easier.
          </div>

          <a
            href="{{ route('buyer.addresses.create') }}"
            class="btn btn-sm btn-outline-primary rounded-3">
            <i class="bi bi-plus-lg me-1"></i>
            Add Address
          </a>

        </div>

        @endif

      </div>

    </div>
  </div>
</div>

@else

<div class="container py-4">

  <h2>
    Edit Profile
  </h2>

  @if(session('success'))

  <div class="alert alert-success">
    {{ session('success') }}
  </div>

  @endif

  <form
    method="POST"
    action="{{ route('profile.update') }}">

    @csrf
    @method('PATCH')

    <div class="row">

      <div class="col-md-6">

        <div class="mb-3">

          <label
            for="name"
            class="form-label">
            Name
          </label>

          <input
            type="text"
            name="name"
            id="name"
            class="form-control"
            value="{{ old('name', $user->name) }}"
            required>

          @error('name')
          <div class="text-danger">
            {{ $message }}
          </div>
          @enderror

        </div>

      </div>

      <div class="col-md-6">

        <div class="mb-3">

          <label
            for="email"
            class="form-label">
            Email
          </label>

          <input
            type="email"
            name="email"
            id="email"
            class="form-control"
            value="{{ old('email', $user->email) }}"
            required>

          @error('email')
          <div class="text-danger">
            {{ $message }}
          </div>
          @enderror

        </div>

      </div>

    </div>

    <div class="row">

      <div class="col-md-6">

        <div class="mb-3">

          <label
            for="phone"
            class="form-label">
            Phone
          </label>

          <input
            type="text"
            name="phone"
            id="phone"
            class="form-control"
            value="{{ old('phone', $user->phone) }}">

          @error('phone')
          <div class="text-danger">
            {{ $message }}
          </div>
          @enderror

        </div>

      </div>

      <div class="col-md-6">

        <div class="mb-3">

          <label class="form-label">
            Role
          </label>

          <input
            type="text"
            class="form-control"
            value="{{ $user->roles->pluck('name')->map(fn ($role) => ucfirst($role))->implode(', ') }}"
            disabled>

        </div>

      </div>

    </div>

    <div class="mb-3">

      <label
        for="address"
        class="form-label">
        Address
      </label>

      <textarea
        name="address"
        id="address"
        class="form-control"
        rows="3">{{ old('address', $user->address) }}</textarea>

      @error('address')
      <div class="text-danger">
        {{ $message }}
      </div>
      @enderror

    </div>

    <button
      type="submit"
      class="btn btn-bili-hub">
      Update Profile
    </button>

  </form>

</div>

@endif

@endsection