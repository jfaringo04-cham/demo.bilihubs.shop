<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    private function createNotification($userId, $title, $message, $type = 'message', $link = null)
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    public function index()
    {
        $adminId = auth()->id();

        $partnerIds = Message::where('sender_id', $adminId)
            ->orWhere('receiver_id', $adminId)
            ->pluck('sender_id', 'receiver_id')
            ->flatten()
            ->unique()
            ->reject(fn ($id) => $id == $adminId)
            ->values();

        $conversations = User::whereIn('id', $partnerIds)->get()->map(function ($user) use ($adminId) {
            $last = Message::where(function ($q) use ($adminId, $user) {
                    $q->where('sender_id', $adminId)->where('receiver_id', $user->id);
                })
                ->orWhere(function ($q) use ($adminId, $user) {
                    $q->where('sender_id', $user->id)->where('receiver_id', $adminId);
                })
                ->max('created_at');
            $user->last_activity = $last;
            return $user;
        })->sortByDesc('last_activity');

        return view('admin.messages', compact('conversations'));
    }

    public function show(User $user)
    {
        $adminId = auth()->id();

        $messages = Message::where(function ($q) use ($adminId, $user) {
                $q->where('sender_id', $adminId)->where('receiver_id', $user->id);
            })
            ->orWhere(function ($q) use ($adminId, $user) {
                $q->where('sender_id', $user->id)->where('receiver_id', $adminId);
            })
            ->with(['sender', 'receiver'])
            ->orderBy('created_at')
            ->get();

        Message::where('sender_id', $user->id)
            ->where('receiver_id', $adminId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return view('admin.messages-show', compact('user', 'messages'));
    }

    public function reply(Request $request, User $user)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $user->id,
            'message' => $request->message,
        ]);

        $this->createNotification(
            $user->id,
            'New Message from Admin',
            \Illuminate\Support\Str::limit($request->message, 120),
            'message'
        );

        return back()->with('success', 'Message sent.');
    }
}
