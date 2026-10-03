@extends('layouts.app')

@push('styles')
    @vite('resources/css/buyer/home.css')
@endpush

@section('content')
<div class="container py-5" id="wishlist-page">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1">My Wishlist</h1>
            <p class="text-muted mb-0">
                Products you've saved for later.
            </p>
        </div>

        <a href="{{ route('products.index') }}" class="btn btn-outline-dark">
            <i class="bi bi-bag me-1"></i>
            Continue Shopping
        </a>
    </div>

    @if($wishlists->count())

        <div class="mb-4">
         <span
    class="text-muted"
    id="wishlist-count"
    data-count="{{ $wishlists->total() }}"
>
    {{ $wishlists->total() }}
    {{ Str::plural('saved product', $wishlists->total()) }}
</span>
        </div>

        <div class="row g-4">
            @foreach($wishlists as $wishlist)
                @if($wishlist->product)
                    <div
    class="col-12 col-sm-6 col-lg-4 col-xl-3 wishlist-item"
    data-wishlist-product="{{ $wishlist->product->id }}"
>
    <x-product-card :product="$wishlist->product" />
</div>
                @endif
            @endforeach
        </div>

        @if($wishlists->hasPages())
            <div class="d-flex justify-content-center mt-5">
                {{ $wishlists->links() }}
            </div>
        @endif

    @else

        <div class="text-center py-5 my-5">
            <div class="mb-3">
                <i class="bi bi-heart display-3 text-muted"></i>
            </div>

            <h3 class="fw-bold">Your wishlist is empty</h3>

            <p class="text-muted mb-4">
                Save products you like by clicking the heart icon.
            </p>

            <a href="{{ route('products.index') }}" class="btn btn-dark px-4">
                <i class="bi bi-bag me-1"></i>
                Browse Products
            </a>
        </div>

    @endif

</div>
@endsection

@push('scripts')
    @vite('resources/js/buyer/home.js')
@endpush