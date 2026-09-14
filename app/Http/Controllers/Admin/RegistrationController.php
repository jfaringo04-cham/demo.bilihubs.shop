<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\RegistrationDecision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use App\Models\Notification;

class RegistrationController extends Controller
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

    private function notifyByEmail(User $user, string $decision, ?string $reason = null)
    {
        try {
            Mail::to($user->email)->send(new RegistrationDecision($user, $decision, $reason));
        } catch (\Throwable $e) {
            logger()->error('Failed to send registration email to ' . $user->email . ': ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $query = User::where('status', User::STATUS_PENDING)
            ->whereIn('role', ['customer', 'seller', 'logistic_owner']);

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $applications = $query->latest()->paginate(15);

        return view('admin.registrations', compact('applications'));
    }

    public function show(User $user)
    {
        if (!$user->isPending()) {
            return redirect()->route('admin.registrations.index')
                ->with('error', 'This application is no longer pending.');
        }

        return view('admin.registrations-show', compact('user'));
    }

    public function approve(Request $request, User $user)
    {
        if ($user->role === 'rider') {
            return redirect()->route('admin.registrations.index')
                ->with('error', 'Rider applications must be approved by the logistics company.');
        }

        if (!$user->isPending()) {
            return back()->with('error', 'This application has already been processed.');
        }

        $user->update([
            'status' => User::STATUS_ACTIVE,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->createNotification(
            $user->id,
            'Registration Approved',
            'Your ' . ucfirst($user->role) . ' account application has been approved. You can now log in.',
            'account',
            route('login')
        );

        $this->notifyByEmail($user, 'approved');

        return redirect()->route('admin.registrations.index')
            ->with('success', ucfirst($user->role) . ' application approved and notified via email.');
    }

    public function reject(Request $request, User $user)
    {
        if ($user->role === 'rider') {
            return redirect()->route('admin.registrations.index')
                ->with('error', 'Rider applications must be rejected by the logistics company.');
        }

        if (!$user->isPending()) {
            return back()->with('error', 'This application has already been processed.');
        }

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $user->update([
            'status' => User::STATUS_REJECTED,
            'rejection_reason' => $request->rejection_reason,
        ]);

        $this->createNotification(
            $user->id,
            'Registration Declined',
            'Your ' . ucfirst($user->role) . ' account application was declined. Reason: ' . $request->rejection_reason,
            'account'
        );

        $this->notifyByEmail($user, 'rejected', $request->rejection_reason);

        return redirect()->route('admin.registrations.index')
            ->with('success', ucfirst($user->role) . ' application rejected and notified via email.');
    }
}
