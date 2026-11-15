@php
  [$label, $badge] = match ($status) {
      'pending_review' => ['Pending Review', 'bg-warning text-dark'],
      'verified' => ['Verified', 'bg-success'],
      'needs_replacement' => ['Needs Replacement', 'bg-danger'],
      'uploaded' => ['Uploaded', 'bg-secondary'],
      'number_provided' => ['License Number Provided', 'bg-secondary'],
      default => ['Missing', 'bg-warning text-dark'],
  };
@endphp
<span class="badge {{ $badge }}">{{ $label }}</span>
@if($status === 'needs_replacement' && $reason)
  <p class="small mt-2 mb-0">{{ $reason }}</p>
@endif
