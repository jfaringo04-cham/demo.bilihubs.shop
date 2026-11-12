<form method="POST" action="{{ route('rider.returns.pickup', $shipment, false) }}" data-return-pickup>
  @csrf
  <div class="mb-2">
    <button type="button" class="btn btn-outline-primary" data-return-scan hidden>Scan return QR</button>
    <button type="button" class="btn btn-outline-secondary" data-return-stop hidden>Stop camera</button>
  </div>
  <div id="return-camera-{{ $shipment->id }}" data-return-camera class="mb-2" style="max-width: 360px" hidden></div>
  <div class="mb-2" data-return-image-options hidden>
    <label class="form-label" for="return-image-{{ $shipment->id }}">Or scan a screenshot of the buyer’s QR</label>
    <input type="file" id="return-image-{{ $shipment->id }}" class="form-control"
      accept="image/png,image/jpeg,image/webp" data-return-image>
  </div>
  <p class="small text-muted" role="status" data-return-camera-status></p>
  <label class="form-label" for="return-code-{{ $shipment->id }}">Pickup code from buyer</label>
  <input id="return-code-{{ $shipment->id }}" name="pickup_code" class="form-control mb-2"
    autocomplete="off" spellcheck="false" maxlength="24" pattern="[0-9A-Fa-f]{24}" required>
  <button class="btn btn-primary" type="submit">Verify pickup</button>
</form>
