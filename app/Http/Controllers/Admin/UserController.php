<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Notification;

class UserController extends Controller
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
        $query = User::query()->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'));

        // User Management lists accounts that already got past approval.
        // Never-approved applicants live in Admin -> Registrations, and
        // rejected / permanently deactivated accounts are not managed ones.
        // An explicit ?status= filter is still honoured so the admin can audit
        // a specific status on purpose.
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereNotIn('status', [
                User::STATUS_PENDING,
                User::STATUS_REJECTED,
                User::STATUS_DEACTIVATED,
            ]);
        }

        if ($request->filled('role')) {
            $requestedRole = match ($request->role) { 'customer' => 'buyer', 'logistic_owner', 'logistic' => 'logistics', default => $request->role };
            $query->whereHas('roles', fn ($q) => $q->where('name', $requestedRole));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->latest()->paginate(15);
        $statuses = [
            User::STATUS_PENDING => 'Pending',
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_SUSPENDED => 'Suspended',
            User::STATUS_DEACTIVATED => 'Deactivated',
            User::STATUS_REJECTED => 'Rejected',
        ];

        return view('admin.users', compact('users', 'statuses'));
    }

    public function show(User $user)
    {
        $user->loadCount(['products', 'orders', 'supportTickets', 'warnings']);
        return view('admin.users-show', compact('user'));
    }

    public function activate(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot modify an admin account.');
        }

        $user->update([
            'status' => User::STATUS_ACTIVE,
            'rejection_reason' => null,
        ]);

        $this->createNotification(
            $user->id,
            'Account Activated',
            'Your account has been activated by the administrator.',
            'account'
        );

        return back()->with('success', $user->name . ' has been activated.');
    }

    public function suspend(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot modify an admin account.');
        }

        $user->update(['status' => User::STATUS_SUSPENDED, 'suspended_at' => now()]);

        $this->createNotification(
            $user->id,
            'Account Suspended',
            'Your account has been suspended by the administrator. Please contact support.',
            'account'
        );

        return back()->with('success', $user->name . ' has been suspended.');
    }

    public function deactivate(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot modify an admin account.');
        }

        $user->update(['status' => User::STATUS_DEACTIVATED]);

        $this->createNotification(
            $user->id,
            'Account Deactivated',
            'Your account has been deactivated by the administrator.',
            'account'
        );

        return back()->with('success', $user->name . ' has been deactivated.');
    }
}
