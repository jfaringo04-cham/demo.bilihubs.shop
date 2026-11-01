@extends('layouts.logistic', [
    'title' => 'Truck Manifests',
    'logistic' => $logistic ?? auth()->user()->ownedLogistic
])

@section('content')
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                Truck Manifests
            </h2>

            <p class="text-muted mb-0">
                Group parcels by route and dispatch trucks based on load or schedule.
            </p>
        </div>

        <a
            href="{{ route('logistic.dashboard') }}"
            class="btn btn-outline-secondary">
            Back to Dashboard
        </a>
    </div>

    

    @if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
            <li>
                {{ $error }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @php
    $routeGroups = $waitingShipments->groupBy(
    fn ($shipment) =>
    $shipment->origin_hub_id
    . '-'
    . $shipment->destination_hub_id
    );
    @endphp

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-white py-3">
            <h5 class="fw-semibold mb-0">
                Create Scheduled Truck Trip
            </h5>
        </div>

        <div class="card-body">

            @forelse($routeGroups as $group)

            @php
            $first = $group->first();

            $drivers = $truckDrivers->filter(
            fn ($driver) =>
            (int) $driver->hub_id
            === (int) $first->origin_hub_id
            );
            @endphp

            <div class="border rounded p-3 mb-4">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">

                    <div>
                        <h5 class="mb-1">
                            {{ $first->originHub?->name ?? 'Origin Hub' }}
                            →
                            {{ $first->destinationHub?->name ?? 'Destination Hub' }}
                        </h5>

                        <div class="text-muted">
                            {{ $group->count() }}
                            parcel(s) currently waiting
                        </div>
                    </div>

                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle">

                        <thead>
                            <tr>
                                <th>Tracking</th>
                                <th>Order</th>
                                <th>Buyer</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($group as $shipment)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $shipment->tracking_number }}
                                </td>

                                <td>
                                    {{ $shipment->sellerOrder?->order?->order_number ?? 'N/A' }}
                                </td>

                                <td>
                                    {{ $shipment->sellerOrder?->order?->user?->name ?? 'N/A' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>

                <form
                    method="POST"
                    action="{{ route('logistic.linehaul-trips.store') }}">
                    @csrf

                    <input
                        type="hidden"
                        name="origin_hub_id"
                        value="{{ $first->origin_hub_id }}">

                    <input
                        type="hidden"
                        name="destination_hub_id"
                        value="{{ $first->destination_hub_id }}">

                    <div class="row g-3">

                        <div class="col-md-4">

                            <label class="form-label">
                                Truck Driver
                            </label>

                            <select
                                name="driver_id"
                                class="form-select"
                                required>
                                <option value="">
                                    Select driver
                                </option>

                                @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}">
                                    {{ $driver->name }}
                                    —
                                    {{ $driver->current_load }}/{{ $driver->max_capacity }}
                                </option>
                                @endforeach

                            </select>

                            @if($drivers->isEmpty())
                            <div class="small text-danger mt-1">
                                No available truck driver assigned to this origin hub.
                            </div>
                            @endif

                        </div>

                        <div class="col-md-3">

                            <label class="form-label">
                                Minimum Load
                            </label>

                            <input
                                type="number"
                                name="minimum_load"
                                class="form-control"
                                value="10"
                                min="1"
                                required>

                            <div class="form-text">
                                Truck may leave early when this parcel count is reached.
                            </div>

                        </div>

                        <div class="col-md-3">

                            <label class="form-label">
                                Departure Cutoff
                            </label>

                            <input
                                type="datetime-local"
                                name="scheduled_departure_at"
                                class="form-control"
                                required>

                            <div class="form-text">
                                Truck may leave at this time even if below minimum load.
                            </div>

                        </div>

                        <div class="col-md-2 d-flex align-items-end">

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                                {{ $drivers->isEmpty() ? 'disabled' : '' }}>
                                Create Trip
                            </button>

                        </div>

                    </div>

                </form>

            </div>

            @empty

            <div class="text-center py-5 text-muted">
                No parcels are currently waiting for inter-hub transfer.
            </div>

            @endforelse

        </div>
    </div>


    <div class="card shadow-sm border-0">

        <div class="card-header bg-white py-3">
            <h5 class="fw-semibold mb-0">
                Active & Previous Truck Trips
            </h5>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>
                        <tr>
                            <th>Trip</th>
                            <th>Route</th>
                            <th>Driver</th>
                            <th>Load</th>
                            <th>Departure</th>
                            <th>Readiness</th>
                            <th>Status</th>
                            <th class="text-end">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($trips as $trip)

                        @php
                        $ready = $trip->canDispatch();

                        $badge = match($trip->status) {
                        'loading' =>
                        $ready
                        ? 'success'
                        : 'warning',
                        'dispatched' => 'primary',
                        'arrived' => 'secondary',
                        'cancelled' => 'danger',
                        default => 'secondary',
                        };
                        @endphp

                        <tr>

                            <td class="fw-semibold">
                                {{ $trip->trip_number }}
                            </td>

                            <td>
                                {{ $trip->originHub?->name ?? 'N/A' }}
                                →
                                {{ $trip->destinationHub?->name ?? 'N/A' }}
                            </td>

                            <td>
                                {{ $trip->driver?->name ?? 'N/A' }}
                            </td>

                            <td>
                                <strong>
                                    {{ $trip->shipments_count }}
                                </strong>

                                /
                                {{ $trip->capacity_limit }}

                                <div class="small text-muted">
                                    Min:
                                    {{ $trip->minimum_load }}
                                </div>
                            </td>

                            <td>
                                {{ $trip->scheduled_departure_at?->format('M d, Y h:i A') }}
                            </td>

                            <td>
                                @if($trip->status === 'loading')

                                @if($ready)
                                <span class="badge bg-success">
                                    Ready for Dispatch
                                </span>
                                @else
                                <span class="badge bg-warning text-dark">
                                    Waiting
                                </span>
                                @endif

                                <div class="small text-muted mt-1">
                                    {{ $trip->readinessReason() }}
                                </div>

                                @else
                                —
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-{{ $badge }}">
                                    {{ ucfirst($trip->status) }}
                                </span>
                            </td>

                            <td class="text-end">

                                @if($trip->status === 'loading')

                                <form
                                    method="POST"
                                    action="{{ route('logistic.linehaul-trips.load', $trip) }}"
                                    class="d-inline">
                                    @csrf

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-primary">
                                        Load New Parcels
                                    </button>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('logistic.linehaul-trips.dispatch', $trip) }}"
                                    class="d-inline">
                                    @csrf

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-success"
                                        {{ !$ready ? 'disabled' : '' }}
                                        onclick="return confirm(
                                                    'Dispatch this truck and all loaded parcels?'
                                                )">
                                        Dispatch Truck
                                    </button>
                                </form>

                                @elseif($trip->status === 'dispatched')

                                <form
                                    method="POST"
                                    action="{{ route('logistic.linehaul-trips.receive', $trip) }}"
                                    class="d-inline">
                                    @csrf

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-primary"
                                        onclick="return confirm(
                                                    'Confirm truck arrival at destination hub?'
                                                )">
                                        Receive Truck
                                    </button>
                                </form>

                                @else

                                <span class="text-muted">
                                    Completed
                                </span>

                                @endif

                            </td>

                        </tr>

                        @empty

                        <tr>
                            <td
                                colspan="8"
                                class="text-center py-5 text-muted">
                                No truck trips created yet.
                            </td>
                        </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

        @if($trips->hasPages())
        <div class="card-footer bg-white">
            {{ $trips->links() }}
        </div>
        @endif

    </div>

</div>
@endsection
