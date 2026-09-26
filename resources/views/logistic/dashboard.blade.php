@extends('layouts.logistic', ['title' => 'Dashboard', 'logistic' => $logistic])

@push('styles')
    @vite('resources/css/logistics/dashboard.css')
@endpush

@section('content')
<div class="dashboard-content">
  <!-- Dashboard Header -->
  <div class="logistic-page-header">
    <div class="page-title-section">
      <h5>LOGISTICS OPERATIONS</h5>
      <h1>Speedy Express Dashboard</h1>
      <p>Manage your shipments, riders, hubs, and delivery operations from one place.</p>
    </div>
    <div class="page-actions">
      <div class="date-display">
        <i class="bi bi-calendar3 me-2"></i>
        {{ \Carbon\Carbon::now()->format('l, M d, Y') }}
      </div>
      <a href="{{ route('logistic.shipments.create') }}" class="btn-se-primary">
        <i class="bi bi-plus-lg me-1"></i> New Shipment
      </a>
      <a href="{{ route('logistic.riders.register') }}" class="btn-se-outline">
        <i class="bi bi-person-plus me-1"></i> Register Rider
      </a>
    </div>
  </div>

  <!-- 4 Primary KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="kpi-card purple-tint">
        <div class="kpi-header">
          <div class="kpi-icon purple">
            <i class="bi bi-box-seam"></i>
          </div>
          <div class="kpi-label">Total Shipments</div>
        </div>
        <div class="kpi-value">{{ $totalShipments }}</div>
        <div class="kpi-subtext">
          <i class="bi bi-info-circle me-1"></i>
          All shipments in your network
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="kpi-card amber-tint">
        <div class="kpi-header">
          <div class="kpi-icon amber">
            <i class="bi bi-clock"></i>
          </div>
          <div class="kpi-label">Pending</div>
        </div>
        <div class="kpi-value">{{ $pendingShipments }}</div>
        <div class="kpi-subtext">
          <i class="bi bi-hourglass-split me-1"></i>
          Awaiting pickup or assignment
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="kpi-card blue-tint">
        <div class="kpi-header">
          <div class="kpi-icon blue">
            <i class="bi bi-truck"></i>
          </div>
          <div class="kpi-label">In Transit</div>
        </div>
        <div class="kpi-value">{{ $inTransitShipments }}</div>
        <div class="kpi-subtext">
          <i class="bi bi-geo-alt me-1"></i>
          Currently on the move
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="kpi-card green-tint">
        <div class="kpi-header">
          <div class="kpi-icon green">
            <i class="bi bi-check-circle"></i>
          </div>
          <div class="kpi-label">Delivered</div>
        </div>
        <div class="kpi-value">{{ $deliveredShipments }}</div>
        <div class="kpi-subtext">
          <i class="bi bi-check-circle-fill me-1"></i>
          Successfully completed
        </div>
      </div>
    </div>
  </div>

  <!-- 5 Compact Status Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-2-4 col-lg-2-4">
      <div class="status-card blue">
        <div class="status-icon">
          <i class="bi bi-people"></i>
        </div>
        <div class="status-content">
          <div class="status-label">Active Riders</div>
          <div class="status-value">{{ $activeRiders }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2-4 col-lg-2-4">
      <div class="status-card amber">
        <div class="status-icon">
          <i class="bi bi-person-clock"></i>
        </div>
        <div class="status-content">
          <div class="status-label">Pending Riders</div>
          <div class="status-value">{{ $pendingRiders }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2-4 col-lg-2-4">
      <div class="status-card orange">
        <div class="status-icon">
          <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="status-content">
          <div class="status-label">Delayed</div>
          <div class="status-value">{{ $delayedShipments }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2-4 col-lg-2-4">
      <div class="status-card red">
        <div class="status-icon">
          <i class="bi bi-x-circle"></i>
        </div>
        <div class="status-content">
          <div class="status-label">Cancelled</div>
          <div class="status-value">{{ $cancelledShipments }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2-4 col-lg-2-4">
      <div class="status-card {{ $alertsCount > 0 ? 'red' : 'green' }}">
        <div class="status-icon">
          @if($alertsCount > 0)
            <i class="bi bi-bell"></i>
          @else
            <i class="bi bi-check-circle"></i>
          @endif
        </div>
        <div class="status-content">
          <div class="status-label">Alerts</div>
          @if($alertsCount > 0)
            <div class="status-value">{{ $alertsCount }}</div>
          @else
            <div class="status-value">None</div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <!-- Three-column Operations Row -->
  <div class="row g-3 mb-4">
    <!-- Shipment Status Overview (Donut Chart) - Slightly wider -->
    <div class="col-lg-5">
      <div class="donut-chart-card">
        <div class="donut-header">
          <div class="donut-icon">
            <i class="bi bi-activity"></i>
          </div>
          <div>
            <h5>Shipment Status Overview</h5>
            <p class="text-muted small mb-0">{{ $totalShipments }} total shipments</p>
          </div>
        </div>

        @if($totalShipments > 0)
          <div class="donut-chart-container">
            <canvas id="shipmentChart" width="160" height="160"></canvas>
            <div class="donut-legend">
              <div class="donut-legend-item">
                <div class="donut-legend-dot" style="background-color: #10B981;"></div>
                <span>Delivered: {{ $deliveredShipments }}</span>
              </div>
              <div class="donut-legend-item">
                <div class="donut-legend-dot" style="background-color: #3B82F6;"></div>
                <span>In Transit: {{ $inTransitShipments }}</span>
              </div>
              <div class="donut-legend-item">
                <div class="donut-legend-dot" style="background-color: #F59E0B;"></div>
                <span>Pending: {{ $pendingShipments }}</span>
              </div>
              <div class="donut-legend-item">
                <div class="donut-legend-dot" style="background-color: #F97316;"></div>
                <span>Delayed: {{ $delayedShipments }}</span>
              </div>
              <div class="donut-legend-item">
                <div class="donut-legend-dot" style="background-color: #EF4444;"></div>
                <span>Cancelled: {{ $cancelledShipments }}</span>
              </div>
            </div>
          </div>
        @else
          <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-box-seam"></i></div>
            <div class="empty-title">No shipment activity yet</div>
            <div class="empty-subtitle">Shipment analytics will appear once shipments are created.</div>
          </div>
        @endif
      </div>
    </div>

    <!-- Rider Management -->
    <div class="col-lg-3">
      <div class="operations-card">
        <div class="ops-header">
          <div class="ops-icon">
            <i class="bi bi-people"></i>
          </div>
          <div>
            <h5>Rider Management</h5>
            <p class="text-muted small mb-0">Manage your delivery workforce</p>
          </div>
        </div>
        <div class="ops-content">
          <div class="ops-stat">
            <span class="ops-stat-label">Active Riders</span>
            <span class="ops-stat-value">{{ $activeRiders }}</span>
          </div>
          <div class="ops-stat">
            <span class="ops-stat-label">Pending Applications</span>
            <span class="ops-stat-value">{{ $pendingRiders }}</span>
          </div>
        </div>
        <div class="ops-actions">
          <a href="{{ route('logistic.riders.register') }}" class="btn-se-primary">
            <i class="bi bi-plus-lg me-1"></i> Register
          </a>
          <a href="{{ route('logistic.riders') }}" class="btn-se-outline">
            <i class="bi bi-list me-1"></i> Manage
          </a>
        </div>
      </div>
    </div>

    <!-- Hub Management -->
    <div class="col-lg-4">
      <div class="operations-card">
        <div class="ops-header">
          <div class="ops-icon">
            <i class="bi bi-geo-alt"></i>
          </div>
          <div>
            <h5>Hub Management</h5>
            <p class="text-muted small mb-0">Manage logistics hubs and locations</p>
          </div>
        </div>
        <div class="ops-content">
          <div class="ops-stat">
            <span class="ops-stat-label">Total Hubs</span>
            <span class="ops-stat-value">{{ $totalHubs }}</span>
          </div>
          <div class="ops-stat">
            <span class="ops-stat-label">Active Hubs</span>
            <span class="ops-stat-value">{{ $activeHubs }}</span>
          </div>
        </div>
        <div class="ops-actions">
          <a href="{{ route('logistic.hubs.create') }}" class="btn-se-primary">
            <i class="bi bi-plus-lg me-1"></i> Create Hub
          </a>
          <a href="{{ route('logistic.hubs') }}" class="btn-se-outline">
            <i class="bi bi-list me-1"></i> All Hubs
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Shipments -->
  <div class="row g-3 mb-4">
    <div class="col-12">
      <div class="shipments-table-card">
        <div class="table-header">
          <h5>Recent Shipments</h5>
          <a href="{{ route('logistic.shipments') }}" class="view-all-link">View All</a>
        </div>

        @if($recentShipments->isEmpty())
          <div class="empty-state py-5">
            <div class="empty-icon">
              <i class="bi bi-box-seam"></i>
            </div>
            <div class="empty-title">No shipments yet</div>
            <div class="empty-subtitle">Your recent Speedy Express shipments will appear here.</div>
            <a href="{{ route('logistic.shipments.create') }}" class="btn-se-primary d-inline-flex align-items-center">
              <i class="bi bi-plus-lg me-1"></i> Create First Shipment
            </a>
          </div>
        @else
          <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Tracking #</th>
                  <th>Customer / Order</th>
                  <th>Rider</th>
                  <th>Pickup Address</th>
                  <th>Delivery Address</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentShipments as $shipment)
                  <tr>
                    <td class="tracking-number">{{ $shipment->tracking_number }}</td>
                    <td>
                      @php
                        $sellerOrder = $shipment->sellerOrder;
                        $order = $sellerOrder?->order;
                      @endphp
                      @if($order)
                        {{ $order->user->name ?? 'N/A' }}
                        <div class="small text-muted">
                          {{ $order->order_number ?? ('Order #' . $order->id) }}
                          @if($sellerOrder)
                            · Parcel #{{ $sellerOrder->id }}
                          @endif
                        </div>
                      @else
                        <span class="text-muted">N/A</span>
                      @endif
                    </td>
                    <td>
                      @if($shipment->rider)
                        {{ $shipment->rider->name }}
                      @else
                        <span class="text-muted">Unassigned</span>
                      @endif
                    </td>
                    <td class="text-muted">{{ Str::limit($shipment->pickup_address, 40) }}</td>
                    <td class="text-muted">{{ Str::limit($shipment->delivery_address, 40) }}</td>
                    <td>
                      @php
                        $badgeClass = match($shipment->status) {
                            'pending', 'assigned' => 'status-badge-pending',
                            'picked_up', 'in_transit' => 'status-badge-in_transit',
                            'at_sorting_center' => 'status-badge-default',
                            'sorted', 'staged' => 'status-badge-default',
                            'delivered' => 'status-badge-delivered',
                            'cancelled' => 'status-badge-cancelled',
                            'delayed', 'delivery_failed' => 'status-badge-delayed',
                            default => 'status-badge-default',
                        };
                      @endphp
                      <span class="status-badge {{ $badgeClass }}">{{ $shipment->status }}</span>
                    </td>
                    <td class="text-muted small">{{ $shipment->created_at->format('M d, Y') }}</td>
                    <td>
                      <a href="{{ route('logistic.shipments.show', $shipment) }}" class="btn btn-primary btn-sm rounded-xl">
                        <i class="bi bi-eye"></i> View
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@vite('resources/js/logistics/dashboard.js')
<script type="application/json" id="logistic-dashboard-data">@php $logisticDashboardData = [
  'delivered' => $deliveredShipments,
  'inTransit' => $inTransitShipments,
  'pending' => $pendingShipments,
  'delayed' => $delayedShipments,
  'cancelled' => $cancelledShipments,
]; @endphp @json($logisticDashboardData)</script>
@endpush
