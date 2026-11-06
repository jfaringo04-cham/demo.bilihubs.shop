@extends('layouts.logistic')

@section('content')

    {{-- Logistics COD Deadline Monitoring --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    COD Remittance Deadline Monitoring
                </h5>
                <span class="badge bg-secondary">24-hour policy</span>
            </div>

            <p class="text-muted small">
                Unsubmitted COD cash is overdue 24 hours after collection.
                Pending logistics confirmation is not counted as unsubmitted.
            </p>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-muted mb-2">
                            Overdue Riders
                        </div>
                        <h3 class="fw-bold mb-0">
                            {{ $overdueStats['rider_count'] }}
                        </h3>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-muted mb-2">
                            Overdue Collections
                        </div>
                        <h3 class="fw-bold mb-0">
                            {{ $overdueStats['payment_count'] }}
                        </h3>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="small text-muted mb-2">
                            Overdue COD Amount
                        </div>
                        <h3 class="fw-bold text-danger mb-0">
                            PHP {{ number_format($overdueStats['total_minor'] / 100, 2) }}
                        </h3>
                    </div>
                </div>
            </div>

            @if($overdueStats['payment_count'] > 0)
                <div class="alert alert-warning mt-3 mb-0">
                    Some riders have unsubmitted COD collections past
                    the deadline. Review rider cash balances for follow-up.
                </div>
            @else
                <div class="alert alert-success mt-3 mb-0">
                    No overdue unsubmitted COD collections detected.
                </div>
            @endif
        </div>
    </div>
<div class="container-fluid py-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">COD Financial Reconciliation</h2>
            <p class="text-muted mb-0">
                {{ $logistic->company_name }} · Eligible delivered COD collections
            </p>
        </div>

        <a href="{{ route('logistic.cod-remittances.index') }}"
            class="btn btn-outline-primary">
            <i class="bi bi-cash-stack me-2"></i>
            COD Remittances
        </a>
    </div>

    {{-- Financial Summary Cards --}}
    @php
    $cards = [
    [
    'label' => 'Total COD Collected',
    'key' => 'collected_minor',
    'color' => 'primary',
    'icon' => 'bi-wallet2'
    ],
    [
    'label' => 'Unsubmitted Cash',
    'key' => 'unsubmitted_minor',
    'color' => 'warning',
    'icon' => 'bi-hourglass-split'
    ],
    [
    'label' => 'Pending Verification',
    'key' => 'pending_minor',
    'color' => 'info',
    'icon' => 'bi-clock-history'
    ],
    [
    'label' => 'Confirmed Received',
    'key' => 'confirmed_minor',
    'color' => 'success',
    'icon' => 'bi-check-circle'
    ],
    ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach ($cards as $card)
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="small text-muted fw-medium">
                            {{ $card['label'] }}
                        </span>

                        <span class="text-{{ $card['color'] }}">
                            <i class="bi {{ $card['icon'] }} fs-4"></i>
                        </span>
                    </div>

                    <div class="fs-4 fw-bold text-{{ $card['color'] }}">
                        ₱{{ number_format($totals[$card['key']] / 100, 2) }}
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Rider Cash Balances --}}
    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3 px-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-1">Rider Cash Balances</h5>
                    <p class="text-muted small mb-0">
                        View individual COD collections and remittance status per rider.
                    </p>
                </div>

                <span class="badge bg-primary-subtle text-primary px-3 py-2">
                    {{ $balances->count() }} Riders
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Rider</th>
                        <th class="text-end">Payments</th>
                        <th class="text-end">Collected</th>
                        <th class="text-end">Unsubmitted</th>
                        <th class="text-end">Pending</th>
                        <th class="text-end">Confirmed</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($balances as $balance)
                    <tr>
                        {{-- Rider Name --}}
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width: 38px; height: 38px;">
                                    <i class="bi bi-person-fill"></i>
                                </div>

                                <div>
                                    <div class="fw-semibold">
                                        {{ $balance->rider_name }}
                                    </div>
                                    <div class="small text-muted">
                                        Rider #{{ $balance->rider_id }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Number of Payments --}}
                        <td class="text-end">
                            {{ number_format($balance->payment_count) }}
                        </td>

                        {{-- Total Collected --}}
                        <td class="text-end fw-semibold">
                            ₱{{ number_format($balance->collected_minor / 100, 2) }}
                        </td>

                        {{-- Unsubmitted Cash --}}
                        <td class="text-end text-warning fw-semibold">
                            ₱{{ number_format($balance->unsubmitted_minor / 100, 2) }}
                        </td>

                        {{-- Pending Verification --}}
                        <td class="text-end">
                            ₱{{ number_format($balance->pending_minor / 100, 2) }}
                        </td>

                        {{-- Confirmed Received --}}
                        <td class="text-end text-success fw-semibold">
                            ₱{{ number_format($balance->confirmed_minor / 100, 2) }}
                        </td>

                        {{-- View Rider COD Details --}}
                        <td class="text-end pe-4">
                            <a href="{{ route('logistic.cod-reconciliation.rider', ['rider' => $balance->rider_id]) }}"
                                class="btn btn-sm btn-outline-primary text-nowrap"
                                aria-label="View COD details for {{ $balance->rider_name }}">
                                <i class="bi bi-eye me-1"></i>
                                View Details
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-wallet2 fs-2 d-block mb-2"></i>
                                <p class="mb-1 fw-semibold">
                                    No Rider Cash Balances Found
                                </p>
                                <p class="small mb-0">
                                    No eligible delivered COD collections are available yet.
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    {{-- Reconciliation Notes --}}
    <div class="mt-3">
        <p class="small text-muted mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Rejected remittances remain in history. Their payments count
            as unsubmitted unless a newer pending or confirmed remittance
            exists. Amounts are displayed in Philippine pesos and stored
            as integer centavos.
        </p>
    </div>

</div>
@endsection