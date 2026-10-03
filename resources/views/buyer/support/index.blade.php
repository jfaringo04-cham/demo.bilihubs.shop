@extends('layouts.app')

@section('content')
<div class="container py-5">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Support & Reports</h2>
            <p class="text-muted mb-0">
                Track your support requests and product reports in one place.
            </p>
        </div>

        <a href="{{ route('buyer.support.create') }}"
           class="btn btn-bili-hub px-4">
            <i class="bi bi-plus-circle me-1"></i>
            New Support Ticket
        </a>
    </div>

    {{-- Success --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
        </div>
    @endif

    {{-- Summary Cards --}}
    @php
        $totalTickets = $tickets->count();

        $supportTickets = $tickets->filter(function ($ticket) {
            return !in_array($ticket->type, ['complaint', 'dispute']);
        })->count();

        $productReports = $tickets->filter(function ($ticket) {
            return $ticket->type === 'complaint' && $ticket->product_id;
        })->count();

        $openTickets = $tickets->where('status', 'open')->count();

        $resolvedTickets = $tickets->where('status', 'resolved')->count();
    @endphp

    <div class="row g-3 mb-4">

        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-body">
                    <div class="small text-muted mb-1">Total</div>
                    <div class="fs-4 fw-bold">{{ $totalTickets }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-body">
                    <div class="small text-muted mb-1">Support</div>
                    <div class="fs-4 fw-bold">{{ $supportTickets }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-body">
                    <div class="small text-muted mb-1">Product Reports</div>
                    <div class="fs-4 fw-bold">{{ $productReports }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-body">
                    <div class="small text-muted mb-1">Open</div>
                    <div class="fs-4 fw-bold">{{ $openTickets }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100 rounded-4">
                <div class="card-body">
                    <div class="small text-muted mb-1">Resolved</div>
                    <div class="fs-4 fw-bold">{{ $resolvedTickets }}</div>
                </div>
            </div>
        </div>

    </div>

    @if($tickets->count() > 0)

        {{-- Filters --}}
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">

                <div class="d-flex flex-wrap gap-2 p-3 border-bottom">
                    <button type="button"
                            class="btn btn-sm btn-primary ticket-filter"
                            data-filter="all">
                        All
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-secondary ticket-filter"
                            data-filter="support">
                        Support Tickets
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-secondary ticket-filter"
                            data-filter="report">
                        Product Reports
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-secondary ticket-filter"
                            data-filter="open">
                        Open
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-secondary ticket-filter"
                            data-filter="resolved">
                        Resolved
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Reference</th>
                                <th>Type</th>
                                <th>Subject / Product</th>
                                <th>Status</th>
                                <th>Admin Response</th>
                                <th>Submitted</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>

                        <tbody id="support-ticket-table">

                            @foreach($tickets as $ticket)

                                @php
                                    $isProductReport =
                                        $ticket->type === 'complaint'
                                        && $ticket->product_id;

                                    $ticketCategory = $isProductReport
                                        ? 'report'
                                        : 'support';
                                @endphp

                                <tr class="ticket-row"
                                    data-category="{{ $ticketCategory }}"
                                    data-status="{{ $ticket->status }}">

                                    {{-- Reference --}}
                                    <td class="ps-4">
                                        <span class="fw-semibold">
                                            #{{ $ticket->id }}
                                        </span>
                                    </td>

                                    {{-- Type --}}
                                    <td>
                                        @if($isProductReport)

                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                <i class="bi bi-flag me-1"></i>
                                                Product Report
                                            </span>

                                        @else

                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                <i class="bi bi-headset me-1"></i>
                                                Support
                                            </span>

                                        @endif
                                    </td>

                                    {{-- Subject --}}
                                    <td style="min-width: 240px;">

                                        <div class="fw-semibold">
                                            {{ $ticket->subject }}
                                        </div>

                                        @if($isProductReport && $ticket->product)

                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-box me-1"></i>
                                                {{ $ticket->product->name }}
                                            </div>

                                        @endif

                                    </td>

                                    {{-- Status --}}
                                    <td>

                                        @if($ticket->status === 'resolved')

                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>
                                                Resolved
                                            </span>

                                        @elseif($ticket->status === 'open')

                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-clock me-1"></i>
                                                Open
                                            </span>

                                        @else

                                            <span class="badge bg-secondary">
                                                {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                            </span>

                                        @endif

                                    </td>

                                    {{-- Response --}}
                                    <td>

                                        @if($ticket->response)

                                            <span class="text-success small fw-semibold">
                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                Response available
                                            </span>

                                        @else

                                            <span class="text-muted small">
                                                <i class="bi bi-hourglass-split me-1"></i>
                                                Awaiting response
                                            </span>

                                        @endif

                                    </td>

                                    {{-- Date --}}
                                    <td>
                                        <div>
                                            {{ $ticket->created_at->format('M d, Y') }}
                                        </div>

                                        <small class="text-muted">
                                            {{ $ticket->created_at->format('h:i A') }}
                                        </small>
                                    </td>

                                    {{-- Action --}}
                                    <td class="text-end pe-4">

                                        <a href="{{ route('buyer.support.show', $ticket) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>
                                            View Details
                                        </a>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>
                    </table>
                </div>

                {{-- Empty filter result --}}
                <div id="filter-empty"
                     class="text-center py-5 d-none">

                    <i class="bi bi-inbox fs-1 text-muted"></i>

                    <p class="text-muted mt-2 mb-0">
                        No records found for this filter.
                    </p>

                </div>

            </div>
        </div>

    @else

        {{-- Completely Empty --}}
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center py-5">

                <i class="bi bi-headset display-3 text-muted"></i>

                <h4 class="fw-bold mt-3">
                    No support requests yet
                </h4>

                <p class="text-muted mb-4">
                    Your support tickets and product reports will appear here.
                </p>

                <a href="{{ route('buyer.support.create') }}"
                   class="btn btn-bili-hub px-4">
                    <i class="bi bi-plus-circle me-1"></i>
                    Create Support Ticket
                </a>

            </div>
        </div>

    @endif

</div>

@if($tickets->count() > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {

    const buttons = document.querySelectorAll('.ticket-filter');
    const rows = document.querySelectorAll('.ticket-row');
    const emptyState = document.getElementById('filter-empty');
    const table = document.querySelector('.table-responsive');

    buttons.forEach(function (button) {

        button.addEventListener('click', function () {

            const filter = this.dataset.filter;
            let visibleCount = 0;

            buttons.forEach(function (btn) {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-secondary');
            });

            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary');

            rows.forEach(function (row) {

                const category = row.dataset.category;
                const status = row.dataset.status;

                let show = false;

                if (filter === 'all') {
                    show = true;
                } else if (filter === 'support') {
                    show = category === 'support';
                } else if (filter === 'report') {
                    show = category === 'report';
                } else if (filter === 'open') {
                    show = status === 'open';
                } else if (filter === 'resolved') {
                    show = status === 'resolved';
                }

                row.classList.toggle('d-none', !show);

                if (show) {
                    visibleCount++;
                }

            });

            if (visibleCount === 0) {
                table.classList.add('d-none');
                emptyState.classList.remove('d-none');
            } else {
                table.classList.remove('d-none');
                emptyState.classList.add('d-none');
            }

        });

    });

});
</script>
@endif
@endsection