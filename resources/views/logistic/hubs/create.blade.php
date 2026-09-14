@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Create Hub</h1>
        <a href="{{ route('logistic.hubs') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Hubs</a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="table-container mb-4">
                <form action="{{ route('logistic.hubs.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Hub Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                            @error('name') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Hub Address <span class="text-danger">*</span></label>
                            <textarea name="address" id="address" class="form-control" rows="2" required>{{ old('address') }}</textarea>
                            @error('address') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="api_address" class="form-label">Hub API Address / Location</label>
                            <input type="text" name="api_address" id="api_address" class="form-control" value="{{ old('api_address') }}" placeholder="Enter hub API address or full location for auto-assignment">
                            <small class="text-muted">This address will be used to match orders near your hub for rider assignment.</small>
                            @error('api_address') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" id="contact_person" class="form-control" value="{{ old('contact_person') }}">
                            @error('contact_person') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone') }}">
                            @error('phone') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-bili-hub">Create Hub</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
