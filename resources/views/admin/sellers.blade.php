@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
    <div>
        <h1 class="fw-bold text-slate-900 mb-0">Sellers Management</h1>
        <p class="text-muted mb-0">Manage approved BiliHub seller accounts</p>
    </div>
</div>

{{-- Search and Filter --}}
<div class="table-container mb-4">
    <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
        <div class="col-md-6">
            <label for="search" class="form-label fw-semibold">
                Search Seller
            </label>

            <input
                type="text"
                id="search"
                name="search"
                class="form-control"
                placeholder="Search by name, email, or phone..."
                value="{{ request('search') }}"
            >
        </div>

        <div class="col-md-3">
            <label for="status" class="form-label fw-semibold">
                Status
            </label>

            <select id="status" name="status" class="form-select">
                <option value="">All Statuses</option>

                <option
                    value="{{ \App\Models\User::STATUS_ACTIVE }}"
                    @selected(request('status') === \App\Models\User::STATUS_ACTIVE)
                >
                    Active
                </option>

                <option
                    value="{{ \App\Models\User::STATUS_SUSPENDED }}"
                    @selected(request('status') === \App\Models\User::STATUS_SUSPENDED)
                >
                    Suspended
                </option>
            </select>
        </div>

        <div class="col-md-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>
                    Filter
                </button>

                <a href="{{ url()->current() }}" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </div>
    </form>
</div>

{{-- Sellers Table --}}
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="border-0">Seller Name</th>
                    <th class="border-0">Email</th>
                    <th class="border-0">Phone</th>
                    <th class="border-0">Products</th>
                    <th class="border-0">Status</th>
                    <th class="border-0">Joined</th>
                    <th class="border-0">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($sellers as $seller)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="seller-avatar bg-sky-100 text-sky-600 rounded-circle d-flex align-items-center justify-content-center fw-bold">
                                    {{ strtoupper(substr($seller->name, 0, 1)) }}
                                </div>

                                <span class="fw-semibold">
                                    {{ $seller->name }}
                                </span>
                            </div>
                        </td>

                        <td class="text-muted">
                            {{ $seller->email }}
                        </td>

                        <td class="text-muted">
                            {{ $seller->phone ?? 'N/A' }}
                        </td>

                        <td>
                            <span class="badge bg-info">
                                {{ $seller->products_count }}
                                {{ $seller->products_count === 1 ? 'product' : 'products' }}
                            </span>
                        </td>

                        <td>
                            @if($seller->status === \App\Models\User::STATUS_ACTIVE)
                                <span class="badge bg-success">
                                    Active
                                </span>
                            @elseif($seller->status === \App\Models\User::STATUS_SUSPENDED)
                                <span class="badge bg-warning text-dark">
                                    Suspended
                                </span>
                            @else
                                <span class="badge bg-secondary">
                                    {{ ucfirst($seller->status) }}
                                </span>
                            @endif
                        </td>

                        <td class="text-muted">
                            {{ $seller->created_at->format('M d, Y') }}
                        </td>

                        <td>
                            <a
                                href="{{ route('admin.products', ['seller' => $seller->id]) }}"
                                class="btn btn-primary btn-sm rounded-xl"
                            >
                                <i class="bi bi-eye me-1"></i>
                                View Products
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-shop fs-3 d-block mb-2"></i>

                            @if(request()->filled('search') || request()->filled('status'))
                                No sellers matched your search or filter.
                            @else
                                No approved sellers found.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sellers->hasPages())
        <div class="pt-3">
            {{ $sellers->links() }}
        </div>
    @endif
</div>
@endsection