@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Hub: {{ $hub->name }}</h1>
        <div>
            <a href="{{ route('logistic.hubs') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
            <a href="{{ route('logistic.hubs.riders', $hub) }}" class="btn btn-outline-primary"><i class="bi bi-people"></i> Manage Riders</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="table-container mb-4">
                <h5 class="mb-3">Hub Information</h5>
                <form action="{{ route('logistic.hubs.update', $hub) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Hub Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ $hub->name ?? '' }}" required>
                            @error('name') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active" {{ $hub->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $hub->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Hub Address <span class="text-danger">*</span></label>
                            <textarea name="address" id="address" class="form-control" rows="2" required>{{ $hub->address ?? '' }}</textarea>
                            @error('address') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="api_address" class="form-label">Hub API Address / Location</label>
                            <input type="text" name="api_address" id="api_address" class="form-control" value="{{ $hub->api_address ?? '' }}" placeholder="Enter hub API address or full location for auto-assignment">
                            <small class="text-muted">This address will be used to match orders near your hub for rider assignment.</small>
                            @error('api_address') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" id="contact_person" class="form-control" value="{{ $hub->contact_person ?? '' }}">
                            @error('contact_person') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control" value="{{ $hub->phone ?? '' }}">
                            @error('phone') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-bili-hub">Update Hub</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-container mb-4">
                <h5 class="mb-3">Assigned Riders ({{ $hub->riders->count() }})</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Rider Name</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($hub->riders as $rider)
                                <tr>
                                    <td>{{ $rider->name }}</td>
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
                                <tr><td colspan="4" class="text-center text-muted py-4">No riders assigned to this hub.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="table-container">
                <h5 class="mb-3">Danger Zone</h5>
                <form action="{{ route('logistic.hubs.destroy', $hub) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this hub? This action cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Hub</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
