@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Reviews</h2>
  </div>

  @if($reviews->count() > 0)
    <div class="row g-4">
      @foreach($reviews as $review)
        <div class="col-md-6">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="card-title">{{ $review->product->name ?? 'Unknown Product' }}</h5>
                  <div class="mb-2">
                    @for($i = 1; $i <= 5; $i++)
                      @if($i <= $review->rating)
                        <i class="bi bi-star-fill text-warning"></i>
                      @else
                        <i class="bi bi-star text-muted"></i>
                      @endif
                    @endfor
                  </div>
                  @if($review->comment)
                    <p class="card-text">{{ $review->comment }}</p>
                  @endif
                  <small class="text-muted">{{ $review->created_at->format('M d, Y') }}</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @else
    <div class="text-center py-5">
      <i class="bi bi-star display-1 text-muted"></i>
      <p class="text-muted mt-3">No reviews yet.</p>
      <a href="{{ route('products.index') }}" class="btn btn-bili-hub">Start Shopping</a>
    </div>
  @endif
</div>
@endsection


