@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2 mb-1">Complaint / Dispute #{{ $ticket->id }}</h1>
        <p class="text-muted mb-0">
            Review the complaint details and coordinate with the involved parties.
        </p>
    </div>

    <a href="{{ route('admin.complaints.index') }}"
       class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i>
        Back
    </a>
</div>

<div class="row g-4">

    {{-- LEFT COLUMN --}}
    <div class="col-md-7">

        {{-- COMPLAINT DETAILS --}}
        <div class="table-container mb-4">
            <h5 class="mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Details
            </h5>

            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0">

                    <tr>
                        <th style="width: 35%;">Type</th>
                        <td>
                            <span class="badge bg-info text-capitalize">
                                {{ $ticket->type }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Subject</th>
                        <td>{{ $ticket->subject }}</td>
                    </tr>

                    <tr>
                        <th>From (Complainant)</th>
                        <td>
                            <div class="fw-semibold">
                                {{ $ticket->user->name ?? 'N/A' }}
                            </div>

                            @if($ticket->user?->email)
                                <div class="small text-muted">
                                    {{ $ticket->user->email }}
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
    <th>Against</th>
    <td>
        @if($ticket->againstUser)

            <div class="fw-semibold">
                {{
                    $ticket->againstUser->business_name
                    ?? $ticket->againstUser->store_name
                    ?? $ticket->againstUser->seller?->name
                    ?? $ticket->againstUser->name
                }}
            </div>

            @if(
                $ticket->againstUser->name &&
                $ticket->againstUser->name !==
                ($ticket->againstUser->business_name
                    ?? $ticket->againstUser->store_name
                    ?? $ticket->againstUser->seller?->name)
            )
                <div class="small text-muted">
                    Account: {{ $ticket->againstUser->name }}
                </div>
            @endif

            @if($ticket->againstUser->email)
                <div class="small text-muted">
                    {{ $ticket->againstUser->email }}
                </div>
            @endif

        @else
            —
        @endif
    </td>
</tr>


                    {{-- REPORTED PRODUCT --}}
                    <tr>
                        <th>Reported Product</th>
                        <td>
                            @if($ticket->product)
                                <div class="fw-semibold">
                                    {{ $ticket->product->name }}
                                </div>

                                <div class="small text-muted mb-2">
                                    Product ID: #{{ $ticket->product->id }}
                                </div>

                                <a href="{{ route('products.show', $ticket->product) }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>
                                    View Product
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <th>Order</th>
                        <td>
                            {{ $ticket->order ? $ticket->order->order_number : '—' }}
                        </td>
                    </tr>

                    <tr>
                        <th>Priority</th>
                        <td>
                            <span class="text-capitalize">
                                {{ $ticket->priority ?? '—' }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-{{ $ticket->status === 'resolved' ? 'success' : 'warning' }} text-capitalize">
                                {{ $ticket->status }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Submitted</th>
                        <td>
                            {{ $ticket->created_at?->format('M d, Y h:i A') ?? '—' }}
                        </td>
                    </tr>

                </table>
            </div>
        </div>

        {{-- MESSAGE --}}
        <div class="table-container mb-4">
            <h5 class="mb-3">
                <i class="bi bi-chat-left-text me-1"></i>
                Complaint Message
            </h5>

            <div style="white-space: pre-line;">{{ $ticket->message }}</div>

            @if($ticket->evidence)
                <div class="mt-3">
                    <a href="{{ asset('storage/' . $ticket->evidence) }}"
                       target="_blank"
                       rel="noopener"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-paperclip me-1"></i>
                        View Supporting Evidence
                    </a>
                </div>
            @endif
        </div>

        {{-- ADMIN RESPONSE --}}
        @if($ticket->response)
            <div class="table-container mb-4">
                <h5 class="mb-3">
                    <i class="bi bi-check-circle me-1"></i>
                    Admin Response
                </h5>

                <div style="white-space: pre-line;">{{ $ticket->response }}</div>

                <div class="small text-muted mt-3">
                    Responded:
                    {{ $ticket->responded_at?->format('M d, Y h:i A') ?? '—' }}
                </div>
            </div>
        @endif

    </div>

    {{-- RIGHT COLUMN --}}
    <div class="col-md-5">

        {{-- RESOLVE / RESPOND --}}
        <div class="table-container mb-4">
            <h5 class="mb-3">
                <i class="bi bi-reply me-1"></i>
                Resolve & Respond
            </h5>

            @if($ticket->status !== 'resolved')
                <form method="POST"
                      action="{{ route('admin.complaints.respond', $ticket) }}"
                      onsubmit="return confirm('Send this response and resolve the complaint?');">

                    @csrf

                    <div class="mb-3">
                        <label for="response" class="form-label">
                            Resolution / Response
                        </label>

                        <textarea
                            id="response"
                            name="response"
                            class="form-control @error('response') is-invalid @enderror"
                            rows="5"
                            maxlength="2000"
                            placeholder="Write your resolution or response..."
                            required>{{ old('response', $ticket->response) }}</textarea>

                        @error('response')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-reply me-1"></i>
                        Respond & Resolve
                    </button>
                </form>

                <form method="POST"
                      action="{{ route('admin.complaints.resolve', $ticket) }}"
                      class="mt-2">

                    @csrf

                    <button type="submit"
                            class="btn btn-outline-success w-100"
                            onclick="return confirm('Mark this complaint as resolved without sending a response?');">

                        <i class="bi bi-check-circle me-1"></i>
                        Mark Resolved
                    </button>
                </form>
            @else
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    This complaint has already been resolved.
                </div>
            @endif
        </div>

        {{-- MESSAGE PARTY --}}
        <div class="table-container">
            <h5 class="mb-3">
                <i class="bi bi-chat-dots me-1"></i>
                Coordinate
            </h5>

            <p class="small text-muted">
                Send a message to one of the parties involved in this complaint.
            </p>

            <form method="POST"
                  action="{{ route('admin.complaints.message', $ticket) }}">

                @csrf

                <div class="mb-3">
                    <label for="recipient_id" class="form-label">
                        Recipient
                    </label>

                    <select id="recipient_id"
                            name="recipient_id"
                            class="form-select @error('recipient_id') is-invalid @enderror"
                            required>

                        <option value="{{ $ticket->user_id }}">
                            Complainant: {{ $ticket->user->name ?? 'N/A' }}
                        </option>

                        @if($ticket->againstUser)
                            <option value="{{ $ticket->against_user_id }}">
                                Against: {{ $ticket->againstUser->name }}
                            </option>
                        @endif
                    </select>

                    @error('recipient_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="party_message" class="form-label">
                        Message
                    </label>

                    <textarea
                        id="party_message"
                        name="message"
                        class="form-control @error('message') is-invalid @enderror"
                        rows="4"
                        maxlength="2000"
                        placeholder="Write a message..."
                        required>{{ old('message') }}</textarea>

                    @error('message')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-send me-1"></i>
                    Send Message
                </button>
            </form>
        </div>

    </div>
</div>
@endsection