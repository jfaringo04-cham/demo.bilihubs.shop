@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h2>Edit Profile</h2>

  <form method="POST" action="{{ route('profile.update') }}">
    @csrf
    @method('PATCH')

    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <label for="name" class="form-label">Name</label>
          <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required>
          @error('name') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" required>
          @error('email') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <label for="phone" class="form-label">Phone</label>
          <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
          @error('phone') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label for="role" class="form-label">Role</label>
          <input type="text" class="form-control" value="{{ ucfirst($user->role) }}" disabled>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label for="address" class="form-label">Address</label>
      <textarea name="address" id="address" class="form-control" rows="3">{{ old('address', $user->address) }}</textarea>
      @error('address') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <button type="submit" class="btn btn-bili-hub">Update Profile</button>
  </form>
</div>
@endsection


