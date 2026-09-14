@extends('layouts.app')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow">
        <div class="card-body p-4">
          <h2 class="text-center mb-4">Choose Your Account Type</h2>

          @if(session('social_user'))
            <div class="alert alert-info text-center">
              <i class="bi bi-person-circle"></i> {{ session('social_user.name') }}
              <br><small>{{ session('social_user.email') }}</small>
            </div>
          @endif>

          @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
              {{ session('error') }}
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          @endif

          <form method="POST" action="{{ route('social.role.store') }}">
            @csrf

            <div class="mb-4">
              <label class="form-label fw-bold">Register as:</label>

              <div class="role-options">
                <div class="form-check role-option">
                  <input class="form-check-input" type="radio" name="role" id="role_customer" value="customer" required>
                  <label class="form-check-label w-100" for="role_customer">
                    <div class="d-flex align-items-center gap-3">
                      <i class="bi bi-person fs-4"></i>
                      <div>
                        <strong>Buyer</strong>
                        <p class="mb-0 text-muted small">Browse and purchase products</p>
                      </div>
                    </div>
                  </label>
                </div>

                <div class="form-check role-option">
                  <input class="form-check-input" type="radio" name="role" id="role_seller" value="seller">
                  <label class="form-check-label w-100" for="role_seller">
                    <div class="d-flex align-items-center gap-3">
                      <i class="bi bi-shop fs-4"></i>
                      <div>
                        <strong>Seller</strong>
                        <p class="mb-0 text-muted small">Sell products on the platform</p>
                      </div>
                    </div>
                  </label>
                </div>
              </div>
              @error('role') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-bili-hub w-100">Continue</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.role-option {
  padding: 15px;
  border: 2px solid #dee2e6;
  border-radius: 10px;
  margin-bottom: 12px;
  transition: all 0.2s;
  cursor: pointer;
}
.role-option:hover {
  border-color: #ee4d2d;
  background-color: #fff5f5;
}
.role-option .form-check-input {
  margin-top: 12px;
}
.role-option .form-check-input:checked {
  background-color: #ee4d2d;
  border-color: #ee4d2d;
}
.role-option:has(.form-check-input:checked) {
  border-color: #ee4d2d;
  background-color: #fff5f5;
}
</style>
@endsection
