@extends('layouts.logistic', ['title' => 'About Speedy Express', 'logistic' => $logistic])

@section('content')
<div class="dashboard-content">
  <div class="logistic-page-header">
    <div class="page-title-section">
      <h5>COMPANY</h5>
      <h1>About Speedy Express</h1>
      <p>Reliable logistics connecting sellers, buyers, and communities.</p>
    </div>
  </div>

  @php
    $totalShipments = $totalShipments ?? 0;
    $deliveredShipments = $deliveredShipments ?? 0;
    $totalRiders = $totalRiders ?? 0;
    $totalHubs = $totalHubs ?? 0;
  @endphp

  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-2 mb-4">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-3 mb-4">
            @include('components.speedy-logo', ['height' => 44, 'showTagline' => false])
            <div>
              <h1 class="fw-bold text-slate-900 mb-0" style="color: var(--se-primary);">Speedy Express</h1>
              <p class="text-muted small mb-0">Logistics Partner on BiliHub</p>
            </div>
          </div>

          <h5 class="fw-semibold mb-3" style="color: var(--se-primary); letter-spacing: 1px; text-transform: uppercase; font-size: 13px;">ABOUT SPEEDY EXPRESS</h5>
          <p class="text-slate-600 mb-4" style="line-height: 1.7;">
            Reliable delivery. Better connections.
          </p>

          <p class="text-slate-600 mb-4" style="line-height: 1.7;">
            Speedy Express is a logistics partner focused on connecting sellers, buyers, and communities through
            reliable and efficient delivery services.
          </p>

          <p class="text-slate-600 mb-0" style="line-height: 1.7;">
            Our goal is to provide organized shipment handling, dependable rider operations, and convenient
            delivery experiences while supporting the BiliHub marketplace.
          </p>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-2 mb-4" style="background-color: var(--se-light-lavender);">
        <div class="card-body p-4 text-center">
          <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
            <img src="{{ asset('images/speedy-icon.svg') }}" alt="Speedy Express" style="height: 24px; width: auto;">
            <span class="fw-bold" style="color: var(--se-primary);">Speedy Express</span>
          </div>
          <p class="text-muted small mb-0 fw-medium">FAST. RELIABLE. EVERYWHERE.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 h-100 text-center p-4">
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: var(--se-light-lavender); border-radius: 12px; color: var(--se-primary);">
            <i class="bi bi-box-seam fs-4"></i>
          </div>
          <h3 class="fw-bold mb-0" style="color: var(--se-primary);">{{ $totalShipments }}</h3>
          <p class="text-muted small mb-0">Total Shipments</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 h-100 text-center p-4">
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #ECFDF5; border-radius: 12px; color: #10B981;">
            <i class="bi bi-check-circle fs-4"></i>
          </div>
          <h3 class="fw-bold mb-0" style="color: #10B981;">{{ $deliveredShipments }}</h3>
          <p class="text-muted small mb-0">Deliveries</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 h-100 text-center p-4">
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #DBEAFE; border-radius: 12px; color: #3B82F6;">
            <i class="bi bi-people fs-4"></i>
          </div>
          <h3 class="fw-bold mb-0" style="color: #3B82F6;">{{ $totalRiders }}</h3>
          <p class="text-muted small mb-0">Active Riders</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 h-100 text-center p-4">
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #EFF6FF; border-radius: 12px; color: #3B82F6;">
            <i class="bi bi-geo-alt fs-4"></i>
          </div>
          <h3 class="fw-bold mb-0" style="color: #3B82F6;">{{ $totalHubs }}</h3>
          <p class="text-muted small mb-0">Total Hubs</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm rounded-2 h-100">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Mission</h5>
          <p class="text-slate-600" style="line-height: 1.7;">
            To provide reliable, efficient, and accessible logistics services that help sellers deliver
            products safely and help customers receive their orders conveniently.
          </p>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm rounded-2 h-100">
        <div class="card-body p-4">
          <h5 class="fw-semibold mb-3">Vision</h5>
          <p class="text-slate-600" style="line-height: 1.7;">
            To become a trusted logistics partner known for dependable delivery, organized operations,
            and excellent service.
          </p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 h-100 text-center p-4">
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: var(--se-light-lavender); border-radius: 10px; color: var(--se-primary);">
            <i class="bi bi-box-seam fs-5"></i>
          </div>
          <h6 class="fw-semibold mb-2">Shipment Management</h6>
          <p class="text-muted small mb-0">Manage shipments from pickup to successful delivery.</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 h-100 text-center p-4">
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: var(--se-light-lavender); border-radius: 10px; color: var(--se-primary);">
            <i class="bi bi-people fs-5"></i>
          </div>
          <h6 class="fw-semibold mb-2">Rider Operations</h6>
          <p class="text-muted small mb-0">Coordinate riders and delivery assignments.</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 h-100 text-center p-4">
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: var(--se-light-lavender); border-radius: 10px; color: var(--se-primary);">
            <i class="bi bi-sort-alpha-down fs-5"></i>
          </div>
          <h6 class="fw-semibold mb-2">Sorting & Hub Operations</h6>
          <p class="text-muted small mb-0">Organize parcels through hubs and sorting areas.</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm rounded-2 h-100 text-center p-4">
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: var(--se-light-lavender); border-radius: 10px; color: var(--se-primary);">
            <i class="bi bi-geo-stima fs-5"></i>
          </div>
          <h6 class="fw-semibold mb-2">Delivery Tracking</h6>
          <p class="text-muted small mb-0">Monitor shipment progress and delivery status.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-2 mb-4">
    <div class="card-body p-4">
      <h5 class="fw-semibold mb-3">Our Values</h5>
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <div class="d-flex align-items-center gap-2">
            <div style="width: 32px; height: 32px; background-color: var(--se-light-lavender); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--se-primary); font-size: 16px;">
              <i class="bi bi-shield-check"></i>
            </div>
            <div>
              <h6 class="fw-semibold mb-0">Reliability</h6>
              <p class="text-muted small mb-0">Dependable delivery you can count on.</p>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="d-flex align-items-center gap-2">
            <div style="width: 32px; height: 32px; background-color: var(--se-light-lavender); border-radius: 8px; display: flex; align-items-center; justify-content: center; color: var(--se-primary); font-size: 16px;">
              <i class="bi bi-speedometer2"></i>
            </div>
            <div>
              <h6 class="fw-semibold mb-0">Efficiency</h6>
              <p class="text-muted small mb-0">Optimized routes and fast handling.</p>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="d-flex align-items-center gap-2">
            <div style="width: 32px; height: 32px; background-color: var(--se-light-lavender); border-radius: 8px; display: flex; align-items-center; justify-content: center; color: var(--se-primary); font-size: 16px;">
              <i class="bi bi-person-heart"></i>
            </div>
            <div>
              <h6 class="fw-semibold mb-0">Customer Care</h6>
              <p class="text-muted small mb-0">Putting your needs first.</p>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="d-flex align-items-center gap-2">
            <div style="width: 32px; height: 32px; background-color: var(--se-light-lavender); border-radius: 8px; display: flex; align-items-center; justify-content: center; color: var(--se-primary); font-size: 16px;">
              <i class="bi bi-clipboard-data"></i>
            </div>
            <div>
              <h6 class="fw-semibold mb-0">Accountability</h6>
              <p class="text-muted small mb-0">Transparent and responsible service.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-2">
    <div class="card-body p-4">
      <h5 class="fw-semibold mb-3">Safety</h5>
      <p class="text-slate-600 mb-0" style="line-height: 1.7;">
        Speedy Express is committed to safe handling of all parcels. Our riders follow
        strict safety protocols, and all vehicles are maintained to meet safety standards.
        We prioritize the safety of our personnel, our customers' packages, and the community.
      </p>
    </div>
  </div>
</div>
@endsection
