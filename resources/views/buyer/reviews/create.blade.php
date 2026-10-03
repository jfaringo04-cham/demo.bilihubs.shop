@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Write a Review</h2>
            <p class="text-muted mb-0">
                Order #{{ $order->order_number ?? $order->id }}
            </p>
        </div>

        <a href="{{ route('orders.show', $order) }}"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
            Back to Order
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="row g-4">

        @foreach($order->items as $item)

            @php
                $product = $item->product;
                $existingReview = $existingReviews->get($item->product_id);
            @endphp

            @if($product)
                <div class="col-12">

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">

                            <div class="row g-4">

                                {{-- Product --}}
                                <div class="col-md-4">
                                    <div class="d-flex gap-3">

                                        <img
                                            src="{{ $product->image_url }}"
                                            alt="{{ $product->name }}"
                                            class="rounded border"
                                            style="width: 90px; height: 90px; object-fit: cover;"
                                        >

                                        <div>
                                            <h5 class="mb-1">
                                                {{ $product->name }}
                                            </h5>

                                            <small class="text-muted">
                                                Quantity: {{ $item->quantity }}
                                            </small>

                                            @if($existingReview)
                                                <div class="mt-2">
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>
                                                        Reviewed
                                                    </span>
                                                </div>
                                            @endif
                                        </div>

                                    </div>
                                </div>

                                {{-- Review Form --}}
                                <div class="col-md-8">

                                    <form method="POST"
                                          action="{{ route('buyer.reviews.store', $order) }}">

                                        @csrf

                                        <input type="hidden"
                                               name="product_id"
                                               value="{{ $product->id }}">

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">
                                                Your Rating
                                            </label>

                                            <div class="d-flex gap-2">

                                                @for($i = 1; $i <= 5; $i++)

                                                    <input
                                                        type="radio"
                                                        class="btn-check"
                                                        name="rating"
                                                        value="{{ $i }}"
                                                        id="rating-{{ $item->id }}-{{ $i }}"
                                                        autocomplete="off"
                                                        {{ (int) old(
                                                            'rating',
                                                            $existingReview?->rating
                                                        ) === $i ? 'checked' : '' }}
                                                        required
                                                    >

                                                    <label
                                                        class="btn btn-outline-warning"
                                                        for="rating-{{ $item->id }}-{{ $i }}"
                                                    >
                                                        <i class="bi bi-star-fill"></i>
                                                        {{ $i }}
                                                    </label>

                                                @endfor

                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label
                                                for="comment-{{ $item->id }}"
                                                class="form-label fw-semibold"
                                            >
                                                Comment
                                            </label>

                                            <textarea
                                                name="comment"
                                                id="comment-{{ $item->id }}"
                                                class="form-control"
                                                rows="3"
                                                maxlength="1000"
                                                placeholder="Share your experience with this product..."
                                            >{{ old('comment', $existingReview?->comment) }}</textarea>
                                        </div>

                                        <button
                                            type="submit"
                                            class="btn btn-bili-hub"
                                        >
                                            @if($existingReview)
                                                <i class="bi bi-pencil-square me-1"></i>
                                                Update Review
                                            @else
                                                <i class="bi bi-star me-1"></i>
                                                Submit Review
                                            @endif
                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>
                    </div>

                </div>
            @endif

        @endforeach

    </div>

</div>
@endsection