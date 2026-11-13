@extends('rider.layout')

@section('content')
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0">Truck Trip History</h1>
    <a href="{{ route('rider.history') }}" class="btn btn-outline-secondary">Delivery History</a>
  </div>

  <div class="table-container">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Manifest</th>
            <th scope="col">Origin Hub</th>
            <th scope="col">Destination Hub</th>
            <th scope="col">Parcels</th>
            <th scope="col">Arrived At</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($trips as $trip)
            <tr>
              <th scope="row">{{ $trip->trip_number }}</th>
              <td>{{ $trip->originHub?->name ?? '—' }}</td>
              <td>{{ $trip->destinationHub?->name ?? '—' }}</td>
              <td>{{ $trip->parcelCount() }}</td>
              <td>{{ $trip->arrived_at?->format('M d, Y g:i A') ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">No completed truck trips yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($trips->hasPages())
      <div class="mt-3">{{ $trips->links('pagination::bootstrap-5') }}</div>
    @endif
  </div>
</div>
@endsection
