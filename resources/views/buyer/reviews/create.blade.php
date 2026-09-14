@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Write a Review</h2>
    <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Orders</a>
  </div>

  <div class="card" style="max-width: 800px;">
    <div class="card-body">
      <form method="POST" action="{{ route('buyer.reviews.store', $order) }}">
        @csrf
        <div class="mb-3">
          <label class="form-label">Product</label>
          <input type="text" class="form-control" value="{{ $product->name ?? 'Multiple Products' }}" disabled>
        </div>
        <div class="mb-3">
          <label class="form-label">Rating</label>
          <div class="rating">
            @for($i = 5; $i >= 1; $i--)
              <input type="radio" name="rating" value="{{ $i }}" id="star{{ $i }}" {{ old('rating') == $i ? 'checked' : '' }} class="d-none">
              <label for="star{{ $i }}" class="bi bi-star-fill text-warning" style="font-size: 1.5rem; cursor: pointer;"></label>
            @endfor
          </div>
          @error('rating') <div class="text-danger">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
          <label for="comment" class="form-label">Comment (optional)</label>
          <textarea name="comment" id="comment" class="form-control" rows="4">{{ old('comment') }}</textarea>
        </div>
        <button type="submit" class="btn btn-bili-hub">Submit Review</button>
      </form>
    </div>
  </div>
</div>
@endsection


