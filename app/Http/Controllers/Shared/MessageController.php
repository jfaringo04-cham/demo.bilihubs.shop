<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $partnerIds = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->pluck('sender_id', 'receiver_id')
            ->flatten()
            ->unique()
            ->reject(fn ($id) => $id == $userId)
            ->values();

        $conversations = User::whereIn('id', $partnerIds)
            ->get()
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

        return view('shared.messages.index', compact('conversations'));
    }

    public function show(User $user)
    {
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

    public function reply(Request $request, User $user)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $user->id,
            'message' => $request->message,
        ]);

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'title' => 'New Message',
            'message' => \Illuminate\Support\Str::limit($request->message, 120),
            'type' => 'message',
            'link' => '',
        ]);

        return back()->with('success', 'Message sent.');
    }
}
