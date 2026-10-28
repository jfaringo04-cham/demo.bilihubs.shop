@extends('layouts.logistic', ['title' => 'Hub Transfers', 'logistic' => $logistic])

@section('content')
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2"><i class="bi bi-arrow-left-right"></i> Inter-Hub Transfers</h1>
        <p class="text-muted mb-0">Move parcels from their origin barangay hub to their destination barangay hub.</p>
    </div>
    <a href="{{ route('logistic.sorting-area') }}" class="btn btn-outline-secondary">Back to Sorting Area</a>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><strong>Ready to Dispatch from Origin Hub</strong></div>
            <div class="card-body table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Tracking</th><th>Origin Hub</th><th>Destination Hub</th><th>Buyer</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($atOrigin as $shipment)
                        <tr>
                            <td><code>{{ $shipment->tracking_number }}</code></td>
                            <td>{{ $shipment->originHub?->name ?? 'N/A' }}</td>
                            <td>{{ $shipment->destinationHub?->name ?? 'N/A' }}</td>
                            <td>{{ $shipment->sellerOrder?->order?->user?->name ?? 'N/A' }}</td>
                            <td>
                                <form method="POST" action="{{ route('logistic.shipments.dispatch-transfer', $shipment) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary" onclick="return confirm('Dispatch this parcel to its destination hub?')">Dispatch Transfer</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No parcels waiting for dispatch.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><strong>In Transfer / Ready for Destination Receipt</strong></div>
            <div class="card-body table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Tracking</th><th>Origin Hub</th><th>Destination Hub</th><th>Dispatched</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($inTransfer as $shipment)
                        <tr>
                            <td><code>{{ $shipment->tracking_number }}</code></td>
                            <td>{{ $shipment->originHub?->name ?? 'N/A' }}</td>
                            <td>{{ $shipment->destinationHub?->name ?? 'N/A' }}</td>
                            <td>{{ optional($shipment->transfer_dispatched_at)->format('M d, Y h:i A') ?? 'N/A' }}</td>
                            <td>
                                <form method="POST" action="{{ route('logistic.shipments.receive-destination', $shipment) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success" onclick="return confirm('Confirm this parcel arrived at the destination hub?')">Receive at Destination</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No parcels currently in hub transfer.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
