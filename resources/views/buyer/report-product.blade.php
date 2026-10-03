@extends('layouts.app')

@section('content')
<div class="container py-5">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Report Product</h2>
            <p class="text-muted mb-0">
                Help us keep BiliHub safe for everyone.
            </p>
        </div>

        <a href="{{ route('products.show', $product) }}"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Back
        </a>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="alert alert-danger mb-4">
            <div class="fw-semibold mb-1">
                <i class="bi bi-exclamation-circle me-1"></i>
                Please check the information below.
            </div>

            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="row g-0">

            {{-- LEFT: PRODUCT PREVIEW --}}
            <div class="col-lg-5 bg-light">
                <div class="p-4 p-lg-5 h-100 d-flex flex-column">

                    <div class="mb-3">
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">
                            <i class="bi bi-flag me-1"></i>
                            Product being reported
                        </span>
                    </div>

                    <div class="bg-white border rounded-4 p-3 mb-4 text-center">
                        <img
                            src="{{ $product->image_url }}"
                            alt="{{ $product->name }}"
                            class="img-fluid rounded-3"
                            style="
                                width: 100%;
                                max-width: 340px;
                                height: 300px;
                                object-fit: contain;
                            "
                        >
                    </div>

                    <div>
                        <h4 class="fw-bold mb-2">
                            {{ $product->name }}
                        </h4>

                        <div class="text-muted small mb-2">
                            <i class="bi bi-shop me-1"></i>
                            Sold by
                        </div>

                        <div class="fw-semibold">
                            {{
                                $product->seller->name
                                ?? $product->sellerUser->business_name
                                ?? $product->sellerUser->name
                                ?? 'Unknown Seller'
                            }}
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-top">
                        <div class="d-flex gap-2 align-items-start text-muted small">
                            <i class="bi bi-shield-check fs-5"></i>

                            <span>
                                Reports are reviewed by the BiliHub compliance team.
                                The seller will not receive your report details directly.
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            {{-- RIGHT: REPORT FORM --}}
            <div class="col-lg-7">
                <div class="p-4 p-lg-5">

                    <div class="mb-4">
                        <h4 class="fw-bold mb-2">
                            What's wrong with this listing?
                        </h4>

                        <p class="text-muted mb-0">
                            Select the reason that best describes the issue and provide
                            enough information for our team to review the product.
                        </p>
                    </div>

                    <form method="POST"
                          action="{{ route('buyer.report-product.store', $product) }}">

                        @csrf

                        {{-- Reason --}}
                        <div class="mb-4">
                            <label for="reason" class="form-label fw-semibold">
                                Reason for Report
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="reason"
                                id="reason"
                                class="form-select form-select-lg @error('reason') is-invalid @enderror"
                                required
                            >
                                <option value="">
                                    Select a reason
                                </option>

                                <option value="scam"
                                    {{ old('reason') === 'scam' ? 'selected' : '' }}>
                                    Scam or fraud
                                </option>

                                <option value="offensive"
                                    {{ old('reason') === 'offensive' ? 'selected' : '' }}>
                                    Offensive or inappropriate content
                                </option>

                                <option value="misleading"
                                    {{ old('reason') === 'misleading' ? 'selected' : '' }}>
                                    Misleading description or images
                                </option>

                                <option value="copyright"
                                    {{ old('reason') === 'copyright' ? 'selected' : '' }}>
                                    Copyright or trademark violation
                                </option>

                                <option value="other"
                                    {{ old('reason') === 'other' ? 'selected' : '' }}>
                                    Other policy violation
                                </option>
                            </select>

                            @error('reason')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        {{-- Details --}}
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="details" class="form-label fw-semibold mb-0">
                                    Report Details
                                    <span class="text-danger">*</span>
                                </label>

                                <small class="text-muted">
                                    Maximum 1,000 characters
                                </small>
                            </div>

                            <textarea
                                name="details"
                                id="details"
                                class="form-control @error('details') is-invalid @enderror"
                                rows="7"
                                maxlength="1000"
                                required
                                placeholder="Describe what is wrong with this product listing. Include specific details that can help our team investigate."
                            >{{ old('details') }}</textarea>

                            @error('details')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            <div class="form-text mt-2">
                                Please provide accurate information. Reports may be reviewed
                                together with the product listing and seller account.
                            </div>
                        </div>

                        {{-- Notice --}}
                        <div class="alert alert-light border d-flex gap-3 align-items-start mb-4">
                            <i class="bi bi-info-circle text-primary fs-5"></i>

                            <div>
                                <div class="fw-semibold mb-1">
                                    What happens after you report?
                                </div>

                                <div class="small text-muted">
                                    Our compliance team will review your report and the
                                    product listing. Appropriate action may be taken if
                                    the listing violates marketplace policies.
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <button type="submit"
                                    class="btn btn-danger px-4 py-2">
                                <i class="bi bi-flag-fill me-1"></i>
                                Submit Report
                            </button>

                            <a href="{{ route('products.show', $product) }}"
                               class="btn btn-outline-secondary px-4 py-2">
                                Cancel
                            </a>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>

</div>
@endsection