@php
$layout = 'layouts.app';
if (auth()->check()) {
    if (auth()->user()->isRider()) {
        $layout = 'rider.layout';
    } elseif (auth()->user()->isSeller()) {
        $layout = 'seller.layout';
    }
}
@endphp
@extends($layout)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Conversation with {{ $user->name }}</h1>
  <a href="{{ route(auth()->user()->role . '.messages.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="table-container" style="max-width: 820px;">
  <div class="border rounded p-3 mb-3" style="max-height: 450px; overflow-y: auto;">
    @forelse($messages as $msg)
      <div class="d-flex mb-2 {{ $msg->sender_id === auth()->id() ? 'justify-content-end' : 'justify-content-start' }}">
        <div class="p-2 rounded {{ $msg->sender_id === auth()->id() ? 'bg-primary text-white' : 'bg-light' }}" style="max-width: 70%;">
          <div class="small fw-bold">{{ $msg->sender->name ?? 'N/A' }}</div>
          <div>{{ $msg->message }}</div>
          <div class="small {{ $msg->sender_id === auth()->id() ? 'text-white-50' : 'text-muted' }}">{{ $msg->created_at->format('M d, H:i') }}</div>
        </div>
      </div>
    @empty
      <p class="text-muted text-center">No messages yet. Start the conversation below.</p>
    @endforelse
  </div>

  <form method="POST" action="{{ route(auth()->user()->role . '.messages.reply', $user) }}">
    @csrf
    <div class="mb-2">
      <textarea name="message" class="form-control" rows="3" placeholder="Type your message..." required></textarea>
    </div>
    <button type="submit" class="btn btn-bili-hub"><i class="bi bi-send"></i> Send Message</button>
  </form>
</div>
@endsection
