@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Manage Riders - {{ $hub->name }}</h1>
        <a href="{{ route('logistic.hubs.show', $hub) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Hub</a>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="table-container mb-4">
                <h5 class="mb-3">Assign Rider to Hub</h5>
                <form action="{{ route('logistic.hubs.riders.assign', $hub) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="rider_id" class="form-label">Select Rider</label>
                        <select name="rider_id" id="rider_id" class="form-select" required>
                            <option value="">Select Rider</option>
                            @forelse($availableRiders as $rider)
                                <option value="{{ $rider->id }}">{{ $rider->name }} ({{ $rider->vehicle_type ?? 'N/A' }})</option>
                            @empty
                                <option value="" disabled>No available riders</option>
                            @endforelse
                        </select>
                        @error('rider_id') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-bili-hub">Assign Rider</button>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-container mb-4">
                <h5 class="mb-3">Currently Assigned Riders</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Rider Name</th>
                                <th>Email</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($hub->riders as $rider)
                                <tr>
                                    <td>{{ $rider->name }}</td>
                                    <td>{{ $rider->email }}</td>
                                    <td>{{ $rider->vehicle_type ?? 'N/A' }}</td>
                                    <td>
                                        @if($rider->logistic_status == 'approved')
                                            <span class="badge bg-success">Approved</span>
                                        @elseif($rider->logistic_status == 'pending')
                                            <span class="badge bg-warning">Pending</span>
                                        @else
                                            <span class="badge bg-danger">Rejected</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route('logistic.hubs.riders.unassign', $hub) }}" method="POST" class="d-inline" onsubmit="return confirm('Unassign this rider from hub?')">
                                            @csrf
                                            <input type="hidden" name="rider_id" value="{{ $rider->id }}">
                                            <button type="submit" class="btn btn-sm btn-warning">Unassign</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No riders assigned to this hub.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
