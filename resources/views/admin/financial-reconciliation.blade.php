@extends('admin.layout')

@section('title', 'Financial Reconciliation')

@section('content')
<div class="container-fluid py-3">

    {{-- PAGE HEADER --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Financial Reconciliation</h2>
            <p class="text-muted mb-0">
                Monitor payment collections, COD remittances, commission obligations,
                and recognized platform revenue.
            </p>
        </div>

        <span class="badge bg-primary fs-6 px-3 py-2">
            <i class="bi bi-shield-check me-1"></i>
            Read-Only Financial Overview
        </span>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="row g-3 mb-4">

        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Total Collected Payments</p>
                    <h3 class="fw-bold mb-1">
                        &#8369;{{ number_format($totalCollectedMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">
                        Paid payment records; not platform revenue
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Confirmed COD Remittances</p>
                    <h3 class="fw-bold text-success mb-1">
                        &#8369;{{ number_format($confirmedCodMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">
                        Paid payments linked to confirmed remittances
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Recorded Commission Obligations</p>
                    <h3 class="fw-bold mb-1">
                        &#8369;{{ number_format($totalCommissionMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">
                        Commission records; not necessarily ledger-recognized revenue
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Pending Commission Records</p>
                    <h3 class="fw-bold text-warning mb-1">
                        &#8369;{{ number_format($pendingCommissionMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">
                        Commission records marked pending
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Paid-Status Commission Records</p>
                    <h3 class="fw-bold text-success mb-1">
                        &#8369;{{ number_format($paidCommissionMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">
                        Administrative status only; not proof of a bank transfer
                    </small>
                </div>
            </div>
        </div>

    </div>

    {{-- PLATFORM REVENUE LEDGER --}}
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-1">
                <i class="bi bi-wallet2 me-2"></i>
                Platform Revenue Ledger
            </h5>

            <small class="text-muted">
                Recognized platform revenue from confirmed COD service fees
                and delivered seller commissions, less recorded reversals.
            </small>
        </div>

        <div class="card-body">
            <div class="row g-3">

                {{-- SERVICE FEE REVENUE --}}
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">
                            Net Platform Service Fee Revenue (3%)
                        </div>

                        <h4 class="fw-bold mb-2">
                            &#8369;{{ number_format($serviceFeeNetMinor / 100, 2) }}
                        </h4>

                        <small class="text-muted d-block">
                            Earned:
                            &#8369;{{ number_format($serviceFeeEarnedMinor / 100, 2) }}
                        </small>

                        <small class="text-muted d-block">
                            Reversed:
                            &#8369;{{ number_format($serviceFeeReversedMinor / 100, 2) }}
                        </small>
                    </div>
                </div>

                {{-- SELLER COMMISSION REVENUE --}}
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">
                            Net Seller Commission Revenue (10%)
                        </div>

                        <h4 class="fw-bold mb-2">
                            &#8369;{{ number_format($commissionNetMinor / 100, 2) }}
                        </h4>

                        <small class="text-muted d-block">
                            Earned:
                            &#8369;{{ number_format($commissionEarnedMinor / 100, 2) }}
                        </small>

                        <small class="text-muted d-block">
                            Reversed:
                            &#8369;{{ number_format($commissionReversedMinor / 100, 2) }}
                        </small>
                    </div>
                </div>

                {{-- TOTAL PLATFORM REVENUE --}}
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">
                            Total Net Platform Revenue
                        </div>

                        <h4 class="fw-bold text-success mb-2">
                            &#8369;{{ number_format($totalPlatformRevenueMinor / 100, 2) }}
                        </h4>

                        <small class="text-muted">
                            Net service fees + net seller commissions
                        </small>
                    </div>
                </div>

            </div>
        </div>

        <div class="card-footer bg-white text-muted small">
            Ledger-recognized revenue only. COD custody, seller payouts,
            and unconfirmed payments are excluded. Currency shown: PHP.
        </div>
    </div>

    {{-- ORDER-LEVEL FINANCIAL BREAKDOWN --}}
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-1">
                <i class="bi bi-receipt-cutoff me-2"></i>
                Order-Level Financial Breakdown
            </h5>

            <small class="text-muted">
                Historical amounts are displayed exactly as recorded.
                The indicative amount after commission is not an approved or settled payout.
            </small>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Buyer</th>
                            <th>Subtotal</th>
                            <th>Platform Service Fee (3%)</th>
                            <th>Shipping</th>
                            <th>Buyer Total</th>
                            <th>Recorded Commission</th>
                            <th>Indicative Amount After Commission*</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($recentOrders as $order)

                        @php
                        $subtotalMinor = (int) $order->subtotal_minor;

                        $feeMinor = $order->service_fee_minor !== null
                        ? (int) $order->service_fee_minor
                        : null;

                        $shippingMinor = (int) $order->shipping_minor;
                        $totalMinor = (int) $order->total_minor;

                        $commissionMinor = (int) (
                        $commissionsByOrder[$order->id] ?? 0
                        );

                        $sellerNetMinor = $subtotalMinor - $commissionMinor;
                        @endphp

                        <tr>
                            <td class="fw-semibold">
                                #{{ $order->id }}
                            </td>

                            <td>
                                {{ $order->user?->name ?? 'N/A' }}
                            </td>

                            <td>
                                &#8369;{{ number_format($subtotalMinor / 100, 2) }}
                            </td>

                            <td>
                                @if($feeMinor !== null)
                                &#8369;{{ number_format($feeMinor / 100, 2) }}
                                @else
                                <span class="text-muted">
                                    Legacy / Not Recorded
                                </span>
                                @endif
                            </td>

                            <td>
                                &#8369;{{ number_format($shippingMinor / 100, 2) }}
                            </td>

                            <td class="fw-bold">
                                &#8369;{{ number_format($totalMinor / 100, 2) }}
                            </td>

                            <td>
                                &#8369;{{ number_format($commissionMinor / 100, 2) }}
                            </td>

                            <td class="fw-semibold">
                                @if(
                                in_array(strtolower($order->status), ['delivered', 'completed'])
                                && $commissionMinor > 0
                                )
                                <span class="text-success">
                                    &#8369;{{ number_format($sellerNetMinor / 100, 2) }}
                                </span>
                                @else
                                <span class="text-muted">
                                    Not Available
                                </span>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-secondary">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                        </tr>

                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No orders found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>

        <div class="card-footer bg-white text-muted small">
            * Indicative Amount After Commission = Order Product Subtotal
            minus recorded commission. This is an order-level reference only,
            not an exact seller-level settlement calculation, approved settlement,
            wallet credit, or completed payout. Actual settlement eligibility
            uses the Seller Settlement workflow.
        </div>
    </div>

    {{-- RECENT COD REMITTANCES --}}
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-cash-coin me-2"></i>
                Recent COD Remittances
            </h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Rider</th>
                            <th>Logistics</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($recentRemittances as $remittance)
                        <tr>
                            <td class="fw-semibold">
                                {{ $remittance->reference ?? '#'.$remittance->id }}
                            </td>

                            <td>
                                {{ $remittance->rider?->name ?? 'N/A' }}
                            </td>

                            <td>
                                {{ $remittance->logistic?->company_name ?? 'N/A' }}
                            </td>

                            <td>
                                &#8369;{{ number_format($remittance->total_minor / 100, 2) }}
                            </td>

                            <td>
                                @php
                                $status = strtolower($remittance->status ?? 'unknown');

                                $color = match($status) {
                                'confirmed' => 'success',
                                'rejected' => 'danger',
                                'pending', 'submitted' => 'warning',
                                default => 'secondary'
                                };
                                @endphp

                                <span class="badge bg-{{ $color }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            <td>
                                {{ $remittance->submitted_at?->format('M d, Y h:i A') ?? '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No COD remittances found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>
    </div>

    {{-- RECENT PAYMENTS --}}
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-credit-card me-2"></i>
                Recent Payments
            </h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th>Payment ID</th>
                            <th>Order</th>
                            <th>Shipment</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($recentPayments as $payment)
                        <tr>
                            <td>#{{ $payment->id }}</td>

                            <td>
                                {{ $payment->order_id ? '#'.$payment->order_id : '—' }}
                            </td>

                            <td>
                                {{ $payment->shipment_id ? '#'.$payment->shipment_id : '—' }}
                            </td>

                            <td>
                                {{ strtoupper($payment->method ?? 'N/A') }}
                            </td>

                            <td>
                                &#8369;{{ number_format($payment->amount_minor / 100, 2) }}
                            </td>

                            <td>
                                @php
                                $paymentStatus = strtolower($payment->status ?? 'unknown');

                                $paymentColor = match($paymentStatus) {
                                'paid' => 'success',
                                'pending' => 'warning',
                                'failed', 'cancelled' => 'danger',
                                default => 'secondary'
                                };
                                @endphp

                                <span class="badge bg-{{ $paymentColor }}">
                                    {{ ucfirst($paymentStatus) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No payment records found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>
    </div>

    {{-- FINANCIAL NOTICE --}}
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        This dashboard is read-only. Confirmed COD remittances do not
        automatically mean seller or logistics payouts have been settled.
        Commission record statuses are separate from actual seller withdrawals.
        Platform revenue is reported separately through recognized ledger entries.
    </div>

</div>
@endsection