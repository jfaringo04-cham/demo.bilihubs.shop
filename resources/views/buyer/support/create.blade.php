@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Contact Support</h2>
    <a href="{{ route('buyer.support.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div class="card" style="max-width: 800px;">
    <div class="card-body">
      <form method="POST" action="{{ route('buyer.support.store') }}">
        @csrf
        <div class="mb-3">
          <label for="subject" class="form-label">Subject</label>
          <input type="text" name="subject" id="subject" class="form-control" value="{{ old('subject') }}" required>
          @error('subject') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
          <label for="message" class="form-label">Message</label>
          <textarea name="message" id="message" class="form-control" rows="6" required>{{ old('message') }}</textarea>
          @error('message') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <button type="submit" class="btn btn-bili-hub">Submit Ticket</button>
      </form>
    </div>
  </div>
</div>
@endsection


