@extends('rider.layout')

@section('content')
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0">Truck Trips</h1>
    <a href="{{ route('rider.history') }}" class="btn btn-outline-secondary">Delivery History</a>
  </div>

  @if ($selectedTrip)
    <section class="card mb-4" aria-labelledby="trip-details-heading">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
          <h2 id="trip-details-heading" class="h5 mb-0">{{ $selectedTrip->trip_number }}</h2>
          <span class="badge text-bg-primary align-self-start">{{ ucfirst($selectedTrip->status) }}</span>
        </div>
        <dl class="row mb-0">
          <dt class="col-sm-4">Origin hub</dt>
          <dd class="col-sm-8">{{ $selectedTrip->originHub?->name ?? '—' }}</dd>
          <dt class="col-sm-4">Destination hub</dt>
          <dd class="col-sm-8">{{ $selectedTrip->destinationHub?->name ?? '—' }}</dd>
          <dt class="col-sm-4">Scheduled departure</dt>
          <dd class="col-sm-8">{{ $selectedTrip->scheduled_departure_at?->format('M d, Y g:i A') ?? '—' }}</dd>
          <dt class="col-sm-4">Parcels loaded</dt>
          <dd class="col-sm-8">{{ $selectedTrip->parcelCount() }}</dd>
        </dl>
      </div>
    </section>
  @endif

  <h2 class="h5 mb-2">Active trips</h2>
  <div class="list-group mb-4">
    @forelse ($activeTrips as $trip)
      <a href="{{ route('rider.truck-trip-history', ['trip' => $trip->id]) }}"
         class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <div class="fw-semibold">{{ $trip->trip_number }}</div>
          <div class="small text-muted">{{ $trip->originHub?->name ?? '—' }} → {{ $trip->destinationHub?->name ?? '—' }}</div>
        </div>
        <span class="badge text-bg-primary">{{ ucfirst($trip->status) }}</span>
      </a>
    @empty
      <div class="list-group-item text-muted">No active truck trips.</div>
    @endforelse
  </div>

  <h2 class="h5 mb-2">Completed trips</h2>
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
