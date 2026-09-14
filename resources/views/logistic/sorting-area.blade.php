@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2"><i class="bi bi-box-seam"></i> Sorting Area - {{ $logistic->company_name }}</h1>
  <a href="{{ route('logistic.shipments') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Shipments</a>
</div>

  <ul class="nav nav-tabs mb-4" id="sortingTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
        <i class="bi bi-inbox"></i> Pending Receipt <span class="badge bg-warning">{{ $pendingShipments->total() }}</span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="received-tab" data-bs-toggle="tab" data-bs-target="#received" type="button" role="tab">
        <i class="bi bi-upcs-scan"></i> Received <span class="badge bg-info">{{ $receivedShipments->count() }}</span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="sorted-tab" data-bs-toggle="tab" data-bs-target="#sorted" type="button" role="tab">
        <i class="bi bi-sort-down"></i> Sort & Stage <span class="badge bg-primary">{{ $receivedShipments->count() }}</span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="staged-tab" data-bs-toggle="tab" data-bs-target="#staged" type="button" role="tab">
        <i class="bi bi-collection"></i> Staged <span class="badge bg-success">{{ $sortedShipments->total() }}</span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="zones-tab" data-bs-toggle="tab" data-bs-target="#zones" type="button" role="tab">
        <i class="bi bi-geo-alt"></i> By Zone <span class="badge bg-info">{{ $sortedShipments->whereNotNull('delivery_zone')->count() }}</span>
      </button>
    </li>
  </ul>

<div class="tab-content" id="sortingTabsContent">
  <!-- Step 1: Pending Receipt -->
  <div class="tab-pane fade show active" id="pending" role="tabpanel">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-inbox"></i> Step 1: Pending Receipt</h5>
        <small class="text-muted">Packages from sellers waiting to be received at sorting area</small>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Tracking #</th>
                <th>Order #</th>
                <th>Items</th>
                <th>Seller Address</th>
                <th>Delivery Address</th>
                <th>Zone</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($pendingShipments as $shipment)
                <tr>
                  <td><code>{{ $shipment->tracking_number }}</code></td>
                  <td>{{ $shipment->order->order_number ?? 'N/A' }}</td>
                  <td>
                    @foreach($shipment->order->items ?? [] as $item)
                      <small class="d-block">{{ $item->product_name }} x{{ $item->quantity }}</small>
                    @endforeach
                  </td>
                  <td><small>{{ Str::limit($shipment->pickup_address, 50) }}</small></td>
                  <td><small>{{ Str::limit($shipment->delivery_address, 50) }}</small></td>
                  <td>
                    <span class="badge bg-secondary">{{ $shipment->delivery_zone ?? 'Not Set' }}</span>
                  </td>
                  <td>
                    <form action="{{ route('logistic.shipments.receive', $shipment) }}" method="POST">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm receipt of this package?')">
                        <i class="bi bi-box-arrow-in-down"></i> Receive Parcel
                      </button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No packages pending receipt.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-3">{{ $pendingShipments->links('pagination::bootstrap-5') }}</div>
      </div>
    </div>
  </div>

  <!-- Step 2: Received - Scan & Tag -->
  <div class="tab-pane fade" id="received" role="tabpanel">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-upcs-scan"></i> Step 2: Scan & Tag Packages</h5>
        <small class="text-muted">Assign delivery zone and type to received packages</small>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Tracking #</th>
                <th>Order #</th>
                <th>Items</th>
                <th>Delivery Address</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($receivedShipments as $shipment)
                <tr>
                  <td><code>{{ $shipment->tracking_number }}</code></td>
                  <td>{{ $shipment->order->order_number ?? 'N/A' }}</td>
                  <td>
                    @foreach($shipment->order->items ?? [] as $item)
                      <small class="d-block">{{ $item->product_name }} x{{ $item->quantity }}</small>
                    @endforeach
                  </td>
                  <td><small>{{ Str::limit($shipment->delivery_address, 50) }}</small></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#scanModal{{ $shipment->id }}">
                      <i class="bi bi-upc-scan"></i> Scan
                    </button>
                    <div class="modal fade" id="scanModal{{ $shipment->id }}" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form action="{{ route('logistic.shipments.scan', $shipment) }}" method="POST">
                            @csrf
                            <div class="modal-header">
                              <h5 class="modal-title">Scan & Tag Package</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <p><strong>Tracking:</strong> {{ $shipment->tracking_number }}</p>
                              <p><strong>Destination:</strong> {{ $shipment->delivery_address }}</p>
                              <div class="mb-3">
                                <label class="form-label">Delivery Zone <span class="text-danger">*</span></label>
                                <select name="delivery_zone" class="form-select" required>
                                  <option value="">Select Zone</option>
                                  <option value="Zone A - Metro Manila">Zone A - Metro Manila</option>
                                  <option value="Zone B - Luzon">Zone B - Luzon</option>
                                  <option value="Zone C - Visayas">Zone C - Visayas</option>
                                  <option value="Zone D - Mindanao">Zone D - Mindanao</option>
                                  <option value="Zone E - International">Zone E - International</option>
                                </select>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Delivery Type <span class="text-danger">*</span></label>
                                <select name="delivery_type" class="form-select" required>
                                  <option value="standard">Standard Delivery</option>
                                  <option value="same_day">Same Day Delivery</option>
                                  <option value="cod">Cash on Delivery (COD)</option>
                                </select>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirm Scan</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No packages to scan.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Step 3: Sort & Stage -->
  <div class="tab-pane fade" id="sorted" role="tabpanel">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-sort-down"></i> Step 3: Sort & Stage Packages</h5>
        <small class="text-muted">Sort parcels according to destination area and stage for rider pickup</small>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Tracking #</th>
                <th>Order #</th>
                <th>Zone</th>
                <th>Type</th>
                <th>Rack</th>
                <th>Status</th>
                <th>Rider</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($sortedShipments as $shipment)
                <tr>
                  <td><code>{{ $shipment->tracking_number }}</code></td>
                  <td>{{ $shipment->order->order_number ?? 'N/A' }}</td>
                  <td><span class="badge bg-info">{{ $shipment->delivery_zone }}</span></td>
                  <td>
                    @if($shipment->delivery_type == 'same_day')
                      <span class="badge bg-danger">Same Day</span>
                    @elseif($shipment->delivery_type == 'cod')
                      <span class="badge bg-warning">COD</span>
                    @else
                      <span class="badge bg-secondary">Standard</span>
                    @endif
                  </td>
                  <td>{{ $shipment->rack_number ?? 'N/A' }}</td>
                  <td>
                    @if($shipment->sorting_status == 'staged')
                      <span class="badge bg-success">Staged</span>
                    @else
                      <span class="badge bg-primary">{{ ucfirst($shipment->sorting_status) }}</span>
                    @endif
                  </td>
                  <td>
                    @if($shipment->rider)
                      <small>{{ $shipment->rider->name }}</small>
                    @else
                      <span class="text-muted">Unassigned</span>
                    @endif
                  </td>
                  <td>
                    @if($shipment->sorting_status == 'scanned')
                      <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sortModal{{ $shipment->id }}">
                        <i class="bi bi-sort-down"></i> Sort
                      </button>
                      <div class="modal fade" id="sortModal{{ $shipment->id }}" tabindex="-1">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <form action="{{ route('logistic.shipments.sort', $shipment) }}" method="POST">
                              @csrf
                              <div class="modal-header">
                                <h5 class="modal-title">Sort Package</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                              </div>
                              <div class="modal-body">
                                <p><strong>Tracking:</strong> {{ $shipment->tracking_number }}</p>
                                <p><strong>Zone:</strong> {{ $shipment->delivery_zone }}</p>
                                <div class="mb-3">
                                  <label class="form-label">Sorting Area <span class="text-danger">*</span></label>
                                  <select name="sorting_area" class="form-select" required>
                                    <option value="">Select Area</option>
                                    <option value="Area A - Small Items">Area A - Small Items</option>
                                    <option value="Area B - Medium Items">Area B - Medium Items</option>
                                    <option value="Area C - Large Items">Area C - Large Items</option>
                                    <option value="Area D - Fragile">Area D - Fragile</option>
                                    <option value="Area E - COD">Area E - COD</option>
                                  </select>
                                </div>
                                <div class="mb-3">
                                  <label class="form-label">Rack Number <span class="text-danger">*</span></label>
                                  <input type="text" name="rack_number" class="form-control" placeholder="e.g. R-01-A" required>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirm Sort</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    @elseif($shipment->sorting_status == 'sorted')
                      @if(!$shipment->rider)
                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#assignModal{{ $shipment->id }}">
                          <i class="bi bi-person-plus"></i> Assign Rider
                        </button>
                        <div class="modal fade" id="assignModal{{ $shipment->id }}" tabindex="-1">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <form action="{{ route('logistic.shipments.assign-rider', $shipment) }}" method="POST">
                                @csrf
                                <div class="modal-header">
                                  <h5 class="modal-title">Assign Rider</h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                  <p><strong>Tracking:</strong> {{ $shipment->tracking_number }}</p>
                                  <p><strong>Zone:</strong> {{ $shipment->delivery_zone }}</p>
                                  <p><strong>Rack:</strong> {{ $shipment->rack_number }}</p>
                                  <div class="mb-3">
                                    <label class="form-label">Select Rider <span class="text-danger">*</span></label>
                                    <select name="rider_id" class="form-select" required>
                                      <option value="">Select Available Rider</option>
                                      @foreach($logistic->riders()->where('availability_status', 'available')->whereColumn('current_load', '<', 'max_capacity')->get() as $rider)
                                        <option value="{{ $rider->id }}">{{ $rider->name }} ({{ $rider->current_load }}/{{ $rider->max_capacity }} packages)</option>
                                      @endforeach
                                    </select>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                  <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Assign</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                      @else
                        <form action="{{ route('logistic.shipments.stage', $shipment) }}" method="POST">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Stage this package for rider pickup?')">
                            <i class="bi bi-box-arrow-up"></i> Stage
                          </button>
                        </form>
                      @endif
                    @elseif($shipment->sorting_status == 'staged')
                      <span class="badge bg-success"><i class="bi bi-check-circle"></i> Ready for Pickup</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No sorted packages.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-3">{{ $sortedShipments->links('pagination::bootstrap-5') }}</div>
      </div>
    </div>
  </div>

  <!-- By Zone -->
  <div class="tab-pane fade" id="zones" role="tabpanel">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Parcels by Delivery Zone</h5>
        <small class="text-muted">Group parcels by zone and auto-assign to riders</small>
      </div>
      <div class="card-body">
        @php
          $zoneShipments = $logistic->shipments()
              ->whereNotIn('sorting_status', ['pending', 'received'])
              ->whereNotNull('delivery_zone')
              ->orderBy('delivery_zone')
              ->get();
          $zones = $zoneShipments->groupBy('delivery_zone');
        @endphp

        @forelse($zones as $zone => $zoneParcels)
          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0"><span class="badge bg-info">{{ $zone }}</span></h6>
              <form action="{{ route('logistic.shipments.auto-assign') }}" method="POST">
                @csrf
                <input type="hidden" name="zone" value="{{ $zone }}">
                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Auto-assign all sorted parcels in this zone to an available rider?')">
                  <i class="bi bi-magic"></i> Auto-Assign All
                </button>
              </form>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Tracking #</th>
                    <th>Order #</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Rider</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($zoneParcels as $shipment)
                    <tr>
                      <td><code>{{ $shipment->tracking_number }}</code></td>
                      <td>{{ $shipment->order->order_number ?? 'N/A' }}</td>
                      <td><small>{{ Str::limit($shipment->delivery_address, 50) }}</small></td>
                      <td>
                        <span class="badge bg-{{ $shipment->sorting_status == 'staged' ? 'success' : 'primary' }}">{{ ucfirst($shipment->sorting_status) }}</span>
                      </td>
                      <td>
                        @if($shipment->rider)
                          {{ $shipment->rider->name }}
                        @else
                          <span class="text-muted">Unassigned</span>
                        @endif
                      </td>
                      <td>
                        @if(!$shipment->rider && $shipment->sorting_status == 'sorted')
                          <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#assignModal{{ $shipment->id }}">
                            <i class="bi bi-person-plus"></i> Assign
                          </button>
                          <div class="modal fade" id="assignModal{{ $shipment->id }}" tabindex="-1">
                            <div class="modal-dialog">
                              <div class="modal-content">
                                <form action="{{ route('logistic.shipments.assign-rider', $shipment) }}" method="POST">
                                  @csrf
                                  <div class="modal-header">
                                    <h5 class="modal-title">Assign Rider</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                  </div>
                                  <div class="modal-body">
                                    <p><strong>Tracking:</strong> {{ $shipment->tracking_number }}</p>
                                    <p><strong>Zone:</strong> {{ $shipment->delivery_zone }}</p>
                                    <div class="mb-3">
                                      <label class="form-label">Select Rider <span class="text-danger">*</span></label>
                                      <select name="rider_id" class="form-select" required>
                                        <option value="">Select Available Rider</option>
                                        @foreach($logistic->riders()->where('availability_status', 'available')->whereColumn('current_load', '<', 'max_capacity')->get() as $rider)
                                          <option value="{{ $rider->id }}">{{ $rider->name }} ({{ $rider->current_load }}/{{ $rider->max_capacity }})</option>
                                        @endforeach
                                      </select>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Assign</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                          </div>
                        @elseif($shipment->sorting_status == 'sorted' && !$shipment->rider)
                          <form action="{{ route('logistic.shipments.stage', $shipment) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">
                              <i class="bi bi-box-arrow-up"></i> Stage
                            </button>
                          </form>
                        @else
                          <span class="badge bg-success"><i class="bi bi-check-circle"></i> Ready</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @empty
          <div class="text-center py-4">
            <i class="bi bi-geo-alt fs-1 text-muted"></i>
            <p class="text-muted mt-2">No parcels have been scanned and assigned a zone yet.</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
