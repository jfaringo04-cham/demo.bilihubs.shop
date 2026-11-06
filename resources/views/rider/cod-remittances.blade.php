@extends('rider.layout')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <p class="text-uppercase text-muted small fw-semibold mb-1">
                Rider Finance
            </p>
            <h2 class="fw-bold mb-1">COD Cash Remittance</h2>
            <p class="text-muted mb-0">
                Submit collected cash to your logistics company for verification.
            </p>
        </div>
        <span class="badge bg-light text-dark border px-3 py-2">
            <i class="bi bi-shield-check me-1"></i>
            Secure Cash Tracking
        </span>
    </div>

    @php
    $availableTotal = $availablePayments->sum('amount_minor');
    $pendingCount = $remittances->getCollection()
    ->where('status', 'pending')->count();
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Available Cash</div>
                    <h3 class="fw-bold text-success mb-1">
                        ₱{{ number_format($availableTotal / 100, 2) }}
                    </h3>
                    <small class="text-muted">
                        Eligible for remittance
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Available Payments</div>
                    <h3 class="fw-bold mb-1">
                        {{ $availablePayments->count() }}
                    </h3>
                    <small class="text-muted">Delivered COD collections</small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Pending Remittances</div>
                    <h3 class="fw-bold text-warning mb-1">
                        {{ $pendingCount }}
                    </h3>
                    <small class="text-muted">On this history page</small>
                </div>
            </div>
        </div>
    </div>

    {{-- COD deadline monitoring --}}
    <div class="alert alert-info mb-4">
        <strong>COD Remittance Deadline: 24 Hours</strong>
        <div class="small mt-1">
            Submit COD cash within 24 hours after collection.
            Late remittances are still accepted for confirmation.
        </div>
    </div>

    @if($overdueCount > 0)
        <div class="alert alert-danger mb-4">
            <strong>Overdue COD Remittance</strong>

            <p class="mb-1 mt-2">
                {{ $overdueCount }}
                COD collections exceeded the 24-hour deadline.
            </p>

            <strong>
                Amount:
                PHP {{ number_format($overdueTotalMinor / 100, 2) }}
            </strong>

            <div class="small mt-2">
                Please remit the overdue cash as soon as possible.
            </div>
        </div>
    @endif

    <div id="remittanceFeedback" class="alert d-none" role="alert"></div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <h5 class="fw-bold mb-1">Available COD Collections</h5>
            <p class="text-muted small mb-0">
                Select payments belonging to the same logistics company.
            </p>
        </div>

        <div class="card-body p-0">
            <form id="codRemittanceForm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Select</th>
                                <th>Payment</th>
                                <th>Shipment</th>
                                <th>Logistics</th>
                                <th>Collected</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($availablePayments as $payment)
                            <tr>
                                <td class="ps-4">
                                    <input
                                        type="checkbox"
                                        class="form-check-input remittance-payment"
                                        value="{{ $payment->id }}"
                                        data-amount="{{ $payment->amount_minor }}"
                                        data-logistic="{{ $payment->shipment->logistic_id }}">
                                </td>
                                <td class="fw-semibold">#{{ $payment->id }}</td>
                                <td>#{{ $payment->shipment_id }}</td>
                                <td>
                                    Logistics #{{ $payment->shipment->logistic_id }}
                                </td>
                                <td>
                                    {{ $payment->paid_at?->format('M d, Y') ?? '—' }}
                                    <div class="small text-muted mt-1">
                                        Due:
                                        {{ $deadlineStatuses[$payment->id]['due_at']->format('M d, Y h:i A') }}
                                    </div>

                                    @if($deadlineStatuses[$payment->id]['overdue'])
                                        <span class="badge bg-danger mt-1">
                                            Overdue
                                        </span>
                                    @else
                                        <span class="badge bg-success mt-1">
                                            Within Deadline
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold pe-4">
                                    ₱{{ number_format($payment->amount_minor / 100, 2) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-check-circle fs-2 d-block mb-2"></i>
                                    No COD collections available for remittance.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($availablePayments->isNotEmpty())
                <div class="p-4 border-top bg-light">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">
                                Remittance Notes
                            </label>
                            <textarea
                                id="remittanceNotes"
                                class="form-control"
                                rows="2"
                                maxlength="1000"
                                placeholder="Optional notes for logistics"></textarea>
                        </div>

                        <div class="col-md-4">
                            <div class="text-muted small">Selected Cash Total</div>
                            <h4 class="fw-bold mb-0" id="selectedTotal">
                                ₱0.00
                            </h4>
                            <small id="selectedCount" class="text-muted">
                                0 payments selected
                            </small>
                        </div>

                        <div class="col-md-3">
                            <button
                                type="submit"
                                id="submitRemittance"
                                class="btn btn-success w-100 py-3"
                                disabled>
                                <i class="bi bi-send-check me-1"></i>
                                Submit Remittance
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white p-4">
            <h5 class="fw-bold mb-1">Remittance History</h5>
            <p class="text-muted small mb-0">
                Track submitted, confirmed, and rejected cash remittances.
            </p>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Reference</th>
                        <th>Submitted</th>
                        <th>Payments</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($remittances as $remittance)
                    <tr>
                        <td class="ps-4">
                            <span class="fw-semibold">
                                {{ $remittance->reference }}
                            </span>
                            @if($remittance->rejection_reason)
                            <div class="text-danger small mt-1">
                                {{ $remittance->rejection_reason }}
                            </div>
                            @endif
                        </td>
                        <td>
                            {{ $remittance->submitted_at?->format('M d, Y h:i A') ?? '—' }}
                        </td>
                        <td>{{ $remittance->payments->count() }}</td>
                        <td class="fw-semibold">
                            ₱{{ number_format($remittance->total_minor / 100, 2) }}
                        </td>
                        <td>
                            @php
                            $badgeColor = match ($remittance->status) {
                            'confirmed' => 'success',
                            'rejected' => 'danger',
                            default => 'warning'
                            };
                            @endphp
                            <span class="badge bg-{{ $badgeColor }}">
                                {{ ucfirst($remittance->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            No remittance history yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($remittances->hasPages())
        <div class="card-footer bg-white">
            {{ $remittances->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('codRemittanceForm');
        const checkboxes = [...document.querySelectorAll('.remittance-payment')];
        const submitButton = document.getElementById('submitRemittance');
        const totalDisplay = document.getElementById('selectedTotal');
        const countDisplay = document.getElementById('selectedCount');
        const feedback = document.getElementById('remittanceFeedback');

        if (!form || !submitButton) return;

        const peso = cents => new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(cents / 100);

        function selectedPayments() {
            return checkboxes.filter(box => box.checked);
        }

        function updateSelection() {
            const selected = selectedPayments();
            const total = selected.reduce(
                (sum, box) => sum + Number(box.dataset.amount), 0
            );

            const companies = new Set(
                selected.map(box => box.dataset.logistic)
            );

            totalDisplay.textContent = peso(total);
            countDisplay.textContent = `${selected.length} payments selected`;

            submitButton.disabled =
                selected.length === 0 ||
                selected.length > 100 ||
                companies.size !== 1;
        }

        checkboxes.forEach(box => {
            box.addEventListener('change', updateSelection);
        });

        form.addEventListener('submit', async function(event) {
            event.preventDefault();

            const selected = selectedPayments();

            if (!selected.length || submitButton.disabled) return;

            if (!window.confirm(
                    'Submit this cash remittance? Logistics must verify the actual cash before confirming.'
                )) return;

            submitButton.disabled = true;
            feedback.classList.add('d-none');

            try {
                const response = await fetch(
                    @json(route('rider.cod-remittances.store')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector(
                                'meta[name="csrf-token"]'
                            ).content
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            payment_ids: selected.map(box => Number(box.value)),
                            notes: document.getElementById('remittanceNotes').value
                        })
                    }
                );

                const data = await response.json();

                if (!response.ok) {
                    const validationMessage = data.errors ?
                        Object.values(data.errors).flat().join(' ') :
                        data.message;

                    throw new Error(validationMessage || 'Submission failed.');
                }

                feedback.className = 'alert alert-success';
                feedback.textContent =
                    'Remittance submitted. Awaiting logistics confirmation.';

                window.location.reload();
            } catch (error) {
                feedback.className = 'alert alert-danger';
                feedback.textContent = error.message;
                updateSelection();
            }
        });
    });
</script>
@endpush