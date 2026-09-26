@props(['height' => 30, 'showTagline' => true, 'className' => ''])

@php
  $width = $height * 200 / 60;
@endphp

<div class="d-flex align-items-center {{ $className }}">
  <img src="{{ asset('images/speedy-express-logo.svg') }}"
       alt="Speedy Express"
       style="height: {{ $height }}px; width: auto;">
</div>
@if($showTagline)
  <div class="small text-muted">FAST. RELIABLE. EVERYWHERE.</div>
@endif
