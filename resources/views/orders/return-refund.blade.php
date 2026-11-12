@php
  $isRefundBuyer = (int) auth()->id() === (int) $order->user_id;
  $isRefundSeller = (int) auth()->user()?->seller?->id === (int) $sellerOrder->seller_id;
  $refund = \App\Models\ReturnRefund::where('seller_order_id', $sellerOrder->id)->first();
  $payout = ($isRefundBuyer || $isRefundSeller) ? $refund?->payout_details : null;
  $refundRouteParams = [$order, $sellerOrder->id];
@endphp

@if($isRefundBuyer || $isRefundSeller)
<div class="border rounded p-3 mt-3">
  <h6>Item refund</h6>
  @if(!$refund)
    <p>Refund tracking has not started for this return.</p>
    @if($isRefundSeller && $order->payment_method === 'cod' && $order->payment_status === 'paid')
      <form method="POST" action="{{ route('seller.orders.startRefund', $refundRouteParams, false) }}">
        @csrf
        <label class="form-check-label d-block mb-2">
          <input type="checkbox" class="form-check-input" name="confirmed_unpaid" value="1" required>
          I verified that no refund has been sent for this return.
        </label>
        <button class="btn btn-outline-primary btn-sm" type="submit">Start refund tracking</button>
      </form>
    @endif
  @else
    <p><strong>{{ $refund->status_label }}</strong> · ₱{{ number_format($refund->amount_minor / 100, 2) }}</p>
    @if($payout)
      <p>{{ $payout['method'] === 'gcash' ? 'GCash' : 'Bank transfer' }}
        · Account ending in {{ substr($payout['account_number'], -4) }}</p>
    @endif

    @if($refund->sent_at)
      <p><strong>Transfer reference:</strong> {{ $refund->transfer_reference }}</p>
      @if($refund->reported_at)
        <p><strong>Refund dispute:</strong> {{ $refund->dispute_status_label }}</p>
        @if($refund->dispute_resolution)
          <p><strong>Admin resolution:</strong> {{ $refund->dispute_resolution }}</p>
        @endif
        @if($isRefundSeller && $refund->dispute_status_label !== 'Resolved' && $refund->dispute_proof_requested_at)
          @if($refund->dispute_proof_submitted_at)
            <p>Transfer proof submitted to admin.</p>
          @else
            <form method="POST" enctype="multipart/form-data"
                  action="{{ route('seller.refund-disputes.proof', $refund, false) }}" class="mb-2">
              @csrf
              <label for="refund-proof-{{ $refund->id }}" class="form-label">Transfer receipt (JPG, PNG, or WebP up to 2 MB)</label>
              <input id="refund-proof-{{ $refund->id }}" class="form-control mb-2" type="file"
                     name="refund_proof" accept="image/jpeg,image/png,image/webp" required>
              <button class="btn btn-outline-primary btn-sm">Submit transfer proof</button>
            </form>
          @endif
          @error('refund_proof')
            <p class="text-danger">{{ $message }}</p>
          @enderror
        @endif
      @endif
      @if($isRefundBuyer && !$refund->received_at)
        <form method="POST" action="{{ route('orders.returns.refundResponse', $refundRouteParams, false) }}">
          @csrf
          <button class="btn btn-primary btn-sm" name="response" value="received">Confirm refund received</button>
          @unless($refund->reported_at)
            <button class="btn btn-outline-danger btn-sm" name="response" value="missing">Report refund missing</button>
          @endunless
        </form>
      @endif
    @elseif($isRefundBuyer && !$payout)
      <form method="POST" action="{{ route('orders.returns.refundMethod', $refundRouteParams, false) }}">
        @csrf
        <label class="form-label d-block mb-2">Refund method
          <select class="form-select" name="method" required>
            <option value="">Choose method</option>
            <option value="gcash">GCash</option>
            <option value="bank">Bank transfer</option>
          </select>
        </label>
        <label class="form-label d-block mb-2">Account name
          <input class="form-control" name="account_name" maxlength="100" autocomplete="off" required>
        </label>
        <label class="form-label d-block mb-2">Account or mobile number
          <input class="form-control" name="account_number" inputmode="numeric" pattern="[0-9]{6,30}" maxlength="30" autocomplete="off" required>
        </label>
        <label class="form-label d-block mb-2">Bank name (bank transfer only)
          <input class="form-control" name="bank_name" maxlength="100" autocomplete="off">
        </label>
        <button class="btn btn-primary btn-sm" type="submit">Save refund account</button>
      </form>
    @elseif($isRefundSeller && $payout)
      @if(app(\App\Services\ReturnRefundService::class)->proceedsReleased($sellerOrder))
        <p>Send ₱{{ number_format($refund->amount_minor / 100, 2) }} to
          {{ $payout['account_name'] }} · {{ $payout['account_number'] }}
          @if(!empty($payout['bank_name']))
            · {{ $payout['bank_name'] }}
          @endif
        </p>
        <form method="POST" action="{{ route('seller.orders.markRefundSent', $refundRouteParams, false) }}">
          @csrf
          <label class="form-label d-block mb-2">Transfer reference
            <input class="form-control" name="transfer_reference" maxlength="120" required>
          </label>
          <label class="form-check-label d-block mb-2">
            <input class="form-check-input" type="checkbox" name="confirmed_sent" value="1" required>
            I already sent this refund.
          </label>
          <button class="btn btn-primary btn-sm" type="submit">Record refund sent</button>
        </form>
      @else
        <p class="mb-0">Seller transfer is pending review. Order proceeds have not been released to your wallet.</p>
      @endif
    @elseif($isRefundSeller)
      <p class="mb-0">Waiting for the buyer’s refund account details.</p>
    @endif
  @endif
</div>
@endif
