<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Shipping Label - {{ $shipment->tracking_number }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <style>
    body { font-family: 'Courier New', monospace; background: #f4f4f4; padding: 20px; }
    .shipping-label {
      background: white;
      max-width: 600px;
      margin: 0 auto;
      border: 3px solid #000;
      padding: 20px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .label-header {
      border-bottom: 2px dashed #000;
      padding-bottom: 10px;
      margin-bottom: 15px;
    }
    .qr-section {
      text-align: center;
      border: 2px dashed #000;
      padding: 10px;
      margin: 15px 0;
      background: #fafafa;
    }
    .qr-section img { max-width: 250px; }
    .tracking-number {
      font-size: 1.5rem;
      font-weight: bold;
      letter-spacing: 2px;
      text-align: center;
      margin: 10px 0;
    }
    .barcode {
      font-family: 'Libre Barcode 39', 'Courier New', monospace;
      font-size: 2.5rem;
      text-align: center;
      letter-spacing: 3px;
      margin: 10px 0;
    }
    .address-block {
      border: 1px solid #000;
      padding: 10px;
      margin: 10px 0;
      background: #fff;
    }
    .address-block strong { display: block; margin-bottom: 5px; }
    @media print {
      body { background: white; padding: 0; }
      .no-print { display: none; }
      .shipping-label { border: 3px solid #000; box-shadow: none; }
    }
  </style>
</head>
<body>
  <div class="no-print text-center mb-3">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Label</button>
    <a href="{{ url()->previous() }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <div class="shipping-label">
    <div class="label-header text-center">
      <h4 class="mb-0">BILI HUB SHIPPING LABEL</h4>
      <small>{{ $shipment->logistic->company_name ?? 'Speedy Express' }}</small>
    </div>

    <div class="tracking-number">
      <i class="bi bi-upc-scan"></i> {{ $shipment->tracking_number }}
    </div>

    <div class="barcode">
      *{{ $shipment->tracking_number }}*
    </div>

    <div class="qr-section">
      <img src="{{ route('shipments.qr.image', $shipment) }}" alt="QR Code" onerror="this.style.display='none'">
      <div class="mt-2 small">
        <strong>SCAN TO CONFIRM</strong><br>
        <code>{{ $shipment->qr_token }}</code>
      </div>
    </div>

    <div class="row">
      <div class="col-6">
        <div class="address-block">
          <strong>FROM (Seller):</strong>
          {{ $shipment->order->items->first()->product->seller->business_name ?? $shipment->order->items->first()->product->seller->name ?? 'Seller' }}<br>
          @php
            $seller = $shipment->order->items->first()->product->seller ?? null;
          @endphp
          @if($seller)
            {{ $seller->house_number }} {{ $seller->street_address }}<br>
            {{ $seller->barangay_name ?? $seller->barangay }}, {{ $seller->municipality_name ?? $seller->municipality }}<br>
            {{ $seller->province_name ?? $seller->province }}, {{ $seller->region_name ?? $seller->region }}<br>
            @if($seller->phone) <i class="bi bi-telephone"></i> {{ $seller->phone }} @endif
          @endif
        </div>
      </div>
      <div class="col-6">
        <div class="address-block">
          <strong>TO (Buyer):</strong>
          {{ $shipment->order->user->name }}<br>
          {{ $shipment->delivery_address }}<br>
          @if($shipment->order->user->phone)
            <i class="bi bi-telephone"></i> {{ $shipment->order->user->phone }}
          @endif
        </div>
      </div>
    </div>

    <div class="row mt-2">
      <div class="col-6">
        <small><strong>Order #:</strong> {{ $shipment->order->order_number }}</small>
      </div>
      <div class="col-6 text-end">
        <small><strong>Date:</strong> {{ $shipment->created_at->format('M d, Y') }}</small>
      </div>
    </div>

    @if($shipment->order->items->count() > 0)
      <div class="mt-3">
        <strong>Items:</strong>
        <ul class="small mb-0">
          @foreach($shipment->order->items as $item)
            <li>{{ $item->product_name }} x{{ $item->quantity }}@if($item->size) ({{ $item->size->name }})@endif</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="mt-3 text-center small text-muted">
      <i class="bi bi-info-circle"></i> Rider must scan QR code upon pickup to confirm parcel.
    </div>
  </div>
</body>
</html>
