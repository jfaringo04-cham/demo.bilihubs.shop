<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    private function authorizeConversation(User $user): void
    {
        $currentUser = Auth::user();

        abort_if(!$currentUser, 403);

        abort_if(
            (int) $currentUser->id === (int) $user->id,
            403,
            'You cannot message yourself.'
        );

        abort_unless(
            $this->canMessage($currentUser, $user),
            403,
            'You are not allowed to open this conversation.'
        );
    }

    private function canMessage(User $currentUser, User $user): bool
    {
        if (!$currentUser->isRider() && !$user->isRider()) {
            return !$currentUser->isCustomer() || $user->isSeller();
        }

        $rider = $currentUser->isRider() ? $currentUser : $user;
        $contact = $currentUser->isRider() ? $user : $currentUser;

        return match (true) {
            $contact->isLogisticOwner() => $rider->logistic_id !== null
                && $contact->ownedLogistic()->whereKey($rider->logistic_id)->exists(),
            $contact->isSeller() => Shipment::query()
                ->where('pickup_rider_id', $rider->id)
                ->whereHas('sellerOrder.seller', fn ($seller) => $seller->where('user_id', $contact->id))
                ->exists(),
            $contact->isCustomer() => Shipment::query()
                ->where('rider_id', $rider->id)
                ->whereHas('sellerOrder.order', fn ($order) => $order->where('user_id', $contact->id))
                ->exists(),
            default => false,
        };
    }

    private function conversationLinkFor(User $recipient, User $sender): string
    {
        $prefix = match (true) {
            $recipient->isSeller() => 'seller',
            $recipient->isRider() => 'rider',
            $recipient->isLogisticOwner() => 'logistic',
            $recipient->isCustomer() => 'buyer',
            default => null,
        };

        return $prefix
            ? route($prefix . '.messages.show', $sender)
            : '';
    }

    public function index()
    {
        $currentUser = Auth::user();
        $userId = $currentUser->id;

        $partnerIds = Message::where('sender_id', $userId)->distinct()->pluck('receiver_id')
            ->merge(Message::where('receiver_id', $userId)->distinct()->pluck('sender_id'))
            ->unique()
            ->reject(fn ($id) => $id == $userId);

        $conversations = User::whereIn('id', $partnerIds)
            ->get()
            ->filter(fn (User $user) => $this->canMessage($currentUser, $user))
            ->map(function ($user) use ($userId) {
                $last = Message::where(function ($q) use ($userId, $user) {
                    $q->where('sender_id', $userId)->where('receiver_id', $user->id);
                })
                    ->orWhere(function ($q) use ($userId, $user) {
                        $q->where('sender_id', $user->id)->where('receiver_id', $userId);
                    })
                    ->max('created_at');
                $user->last_activity = $last;
                $unread = Message::where('sender_id', $user->id)
                    ->where('receiver_id', $userId)
                    ->where('is_read', false)
                    ->count();
                $user->unread_count = $unread;
                return $user;
            })->sortByDesc('last_activity');

        $logisticsContact = $currentUser->isRider() && $currentUser->logistic_id
            ? \App\Models\Logistic::find($currentUser->logistic_id)?->owner
            : null;

        if ($logisticsContact && !$this->canMessage($currentUser, $logisticsContact)) {
            $logisticsContact = null;
        }

        return view('shared.messages.index', compact('conversations', 'logisticsContact'));
    }

    public function show(User $user)
    {
        $this->authorizeConversation($user);
        $userId = Auth::id();

        $messages = Message::where(function ($q) use ($userId, $user) {
            $q->where('sender_id', $userId)->where('receiver_id', $user->id);
        })
            ->orWhere(function ($q) use ($userId, $user) {
                $q->where('sender_id', $user->id)->where('receiver_id', $userId);
            })
            ->orderBy('created_at')
            ->get();

        Message::where('sender_id', $user->id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return view('shared.messages.show', compact('user', 'messages'));
    }


    public function poll(User $user)
    {
        $this->authorizeConversation($user);
        $userId = Auth::id();

        Message::where('sender_id', $user->id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = Message::with('sender:id,name')
            ->where(function ($q) use ($userId, $user) {
                $q->where('sender_id', $userId)
                    ->where('receiver_id', $user->id);
            })
            ->orWhere(function ($q) use ($userId, $user) {
                $q->where('sender_id', $user->id)
                    ->where('receiver_id', $userId);
            })
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) use ($userId) {
                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->name ?? 'User',
                    'message' => $message->message,
                    'is_mine' => (int) $message->sender_id === (int) $userId,
                    'created_at' => $message->created_at
                        ? \Illuminate\Support\Carbon::parse($message->created_at)->format('M d, H:i')
                        : '',
                ];
            })
            ->values();

        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, User $user)
    {
        $this->authorizeConversation($user);
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $user->id,
            'message' => $request->message,
        ]);

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'title' => 'New Message',
            'message' => \Illuminate\Support\Str::limit($request->message, 120),
            'type' => 'message',
            'link' => $this->conversationLinkFor($user, Auth::user()),
        ]);

        if ($request->expectsJson()) {
            $message->load('sender:id,name');

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->name ?? 'User',
                    'message' => $message->message,
                    'is_mine' => true,
                    'created_at' => $message->created_at
                        ? \Illuminate\Support\Carbon::parse($message->created_at)->format('M d, H:i')
                        : '',
                ],
            ]);
        }

        return back()->with('success', 'Message sent.');
    }
}
