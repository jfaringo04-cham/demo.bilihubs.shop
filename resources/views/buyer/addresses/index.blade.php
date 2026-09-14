@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2>My Addresses</h2>
    <div class="d-flex gap-2 flex-wrap">
      <form method="POST" action="{{ route('buyer.addresses.useCurrent') }}" class="d-inline">
        @csrf
        <input type="hidden" name="label" value="Home">
        <input type="hidden" name="is_default" value="1">
        <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Add your current profile address to your address book as default?')">
          <i class="bi bi-geo-alt-fill"></i> Set into Your Current Address
        </button>
      </form>
      <a href="{{ route('buyer.addresses.create') }}" class="btn btn-bili-hub"><i class="bi bi-plus-circle"></i> Add Address</a>
    </div>
  </div>

  @if($addresses->count() > 0)
    <div class="row g-4">
      @foreach($addresses as $address)
        <div class="col-md-6">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  @if($address->is_default)
                    <span class="badge bg-success mb-2">Default</span>
                  @endif
                  @if($address->label)
                    <h5 class="card-title">{{ $address->label }}</h5>
                  @endif
                  <p class="card-text mb-1">{{ $address->address_line1 }}</p>
                  @if($address->address_line2)
                    <p class="card-text mb-1">{{ $address->address_line2 }}</p>
                  @endif
                  <p class="card-text mb-1">{{ $address->city }}{{ $address->province ? ', ' . $address->province : '' }}</p>
                  <p class="card-text mb-1">{{ $address->postal_code }}</p>
                  <p class="card-text mb-1">{{ $address->country }}</p>
                  @if($address->phone)
                    <p class="card-text mb-0"><i class="bi bi-telephone"></i> {{ $address->phone }}</p>
                  @endif
                </div>
                <div class="dropdown">
                  <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('buyer.addresses.edit', $address) }}"><i class="bi bi-pencil"></i> Edit</a></li>
                    <li>
                      <form action="{{ route('buyer.addresses.destroy', $address) }}" method="POST" onsubmit="return confirm('Delete this address?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Delete</button>
                      </form>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @else
    <div class="text-center py-5">
      <i class="bi bi-geo-alt display-1 text-muted"></i>
      <p class="text-muted mt-3">No addresses saved yet.</p>
      <a href="{{ route('buyer.addresses.create') }}" class="btn btn-bili-hub">Add Your First Address</a>
    </div>
  @endif
</div>
@endsection


