<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    private function createNotification($userId, $title, $message, $type = 'order', $link = null)
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    public function index(Request $request)
    {
        $query = SupportTicket::with(['user', 'againstUser', 'order'])
            ->whereIn('type', ['complaint', 'dispute']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $tickets = $query->latest()->paginate(15);

        return view('admin.complaints', compact('tickets'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'againstUser', 'order']);
        return view('admin.complaints-show', compact('ticket'));
    }

    public function respond(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'response' => ['required', 'string', 'max:2000'],
        ]);

        $ticket->update([
            'response' => $request->response,
            'status' => 'resolved',
            'responded_at' => now(),
        ]);

        $this->createNotification(
            $ticket->user_id,
            'Complaint Update',
            'Your ' . $ticket->type . ' (Ref #' . $ticket->id . ') has been reviewed. ' . $request->response,
            'support'
        );

        return back()->with('success', 'Response sent and complaint resolved.');
    }

    public function messageParty(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'recipient_id' => ['required', 'exists:users,id'],
        ]);

        Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $request->recipient_id,
            'message' => $request->message,
        ]);

        $this->createNotification(
            $request->recipient_id,
            'New Message from Admin',
            \Illuminate\Support\Str::limit($request->message, 120),
            'message'
        );

        return back()->with('success', 'Message sent to the involved party.');
    }

    public function resolve(SupportTicket $ticket)
    {
        $ticket->update([
            'status' => 'resolved',
            'responded_at' => $ticket->responded_at ?? now(),
        ]);

        return back()->with('success', 'Complaint marked as resolved.');
    }
}
