@extends('layouts.logistic', ['title' => 'Rider Details', 'logistic' => $logistic])

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Rider Details</h1>
  <a href="{{ route('logistic.riders') }}" class="btn btn-secondary btn-sm">Back to Riders</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">Rider Information</h5>
    @if($rider->logistic_status == 'pending')
      <div>
        <form method="POST" action="{{ route('logistic.riders.approve', $rider) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-bili-hub btn-sm">Approve</button>
        </form>
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
      </div>
    @endif
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label text-muted">Full Name</label>
        <p class="fw-bold">{{ $rider->name }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Email</label>
        <p class="fw-bold">{{ $rider->email }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Phone</label>
        <p class="fw-bold">{{ $rider->phone ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Address</label>
        @php
          $street = trim(implode(' ', array_filter([$rider->house_number, $rider->street_address])));
          $addressParts = $rider->address ? [
            ['text' => $rider->address, 'urls' => []],
          ] : [
            ['text' => $street, 'urls' => []],
          ];

          if (!$rider->address) {
            foreach ([
              'barangay' => ['barangay'],
              'municipality' => ['municipality', 'city'],
              'province' => ['province', 'city'],
              'region' => ['region'],
            ] as $field => $lookups) {
              $name = trim((string) $rider->{$field . '_name'});
              $code = trim((string) $rider->{$field});
              $value = $name ?: $code;
              if ($value === '') {
                continue;
              }

              $urls = preg_match('/^\d{9,10}$/D', $value)
                ? array_map(fn ($lookup) => route('api.addresses.' . $lookup, [$value], false), $lookups)
                : [];
              $addressParts[] = ['text' => $value, 'urls' => $urls];
            }
          }

          $addressLabel = collect($addressParts)->pluck('text')->filter()->unique()->implode(', ');
        @endphp
        <p class="fw-bold" id="riderReviewAddress"
           data-address-parts="{{ json_encode($addressParts) }}">{{ $addressLabel ?: 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Vehicle Type</label>
        <p class="fw-bold">{{ $rider->vehicle_type ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">License Number</label>
        <p class="fw-bold">{{ $rider->license_number ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">Plate Number</label>
        <p class="fw-bold">{{ $rider->vehicle_plate_number ?? 'N/A' }}</p>
      </div>
      <div class="col-md-6">
        <label class="form-label text-muted">License Expiry Date</label>
        <p class="fw-bold">{{ $rider->license_expires_on?->format('M j, Y') ?? 'N/A' }}</p>
      </div>
      <div class="col-12">
        <label class="form-label text-muted">Status</label>
        <p>
          @if($rider->logistic_status == 'approved')
            <span class="badge bg-success">Approved</span>
          @elseif($rider->logistic_status == 'pending')
            <span class="badge bg-warning">Pending</span>
          @else
            <span class="badge bg-danger">Rejected</span>
          @endif
        </p>
      </div>
      @if($rider->logistic_rejection_reason)
        <div class="col-12">
          <label class="form-label text-muted">Rejection Reason</label>
          <p class="text-danger">{{ $rider->logistic_rejection_reason }}</p>
        </div>
      @endif
      @if($rider->logistic_approved_at)
        <div class="col-12">
          <label class="form-label text-muted">Approved At</label>
          <p class="fw-bold">{{ $rider->logistic_approved_at->format('M d, Y h:i A') }}</p>
        </div>
      @endif
    </div>
  </div>
</div>

<section class="card border-0 shadow-sm mt-3" aria-labelledby="riderDocumentsHeading">
  <div class="card-body">
    <h2 id="riderDocumentsHeading" class="h5 mb-3">Submitted Documents</h2>
    @if($errors->hasAny(['document_version', 'decision', 'reason']))
      <div class="alert alert-danger" role="alert">
        {{ $errors->first('document_version') ?: ($errors->first('decision') ?: $errors->first('reason')) }}
      </div>
    @endif
    <div class="row g-3">
      @foreach(\App\Models\User::RIDER_DOCUMENTS as $type => $label)
        @php
          $hasFile = (bool) $rider->{$type};
          $review = $rider->rider_document_reviews[$type] ?? [];
          $status = $hasFile ? ($review['status'] ?? 'uploaded')
              : ($type === 'license_document' && $rider->license_number ? 'number_provided' : 'missing');
        @endphp
        <div class="col-lg-4">
          <div class="border rounded p-3 h-100">
            <h6>{{ $label }}</h6>
            @include('shared.rider-document-status', ['status' => $status, 'reason' => $review['reason'] ?? null])

            @if($hasFile)
              <a class="d-block small mt-2" href="{{ route('logistic.riders.documents.show', [$rider, $type], false) }}"
                 target="_blank" rel="noopener">View {{ $label }}</a>

              <form class="mt-3" method="POST" action="{{ route('logistic.riders.documents.review', [$rider, $type]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="document_version" value="{{ hash('sha256', $rider->{$type}) }}">
                <label for="reason-{{ $type }}" class="form-label small">Replacement reason</label>
                <textarea id="reason-{{ $type }}" name="reason" class="form-control form-control-sm" rows="2"
                          maxlength="500">{{ old('document_version') === hash('sha256', $rider->{$type}) ? old('reason') : '' }}</textarea>
                <div class="d-flex flex-wrap gap-2 mt-2">
                  <button class="btn btn-outline-success btn-sm" type="submit" name="decision" value="verified">Verify</button>
                  <button class="btn btn-outline-danger btn-sm" type="submit" name="decision" value="needs_replacement">Request Replacement</button>
                </div>
              </form>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('logistic.riders.reject', $rider) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Reject Rider</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Rejection Reason</label>
            <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const address = document.getElementById('riderReviewAddress');
    if (!address) return;

    const parts = JSON.parse(address.dataset.addressParts);
    if (!parts.some(part => part.urls.length)) return;

    async function resolvePart(part) {
        for (const url of part.urls) {
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) continue;

                const result = await response.json();
                if (typeof result?.name === 'string' && result.name.trim()) {
                    return result.name.trim();
                }
            } catch {
                // Preserve the saved code if the lookup is unavailable.
            }
        }

        return part.text;
    }

    Promise.all(parts.map(resolvePart)).then(values => {
        address.textContent = [...new Set(values.filter(Boolean))].join(', ');
    });
})();
</script>
@endpush
