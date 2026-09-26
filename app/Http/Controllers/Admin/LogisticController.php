<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logistic;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;

class LogisticController extends Controller
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
    $query = Logistic::query()
        ->with('owner')
        ->withCount([
            'riders as approved_riders_count' => function ($query) {
                $query->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'rider');
                })
                ->where('logistic_status', 'approved')
                ->where('status', User::STATUS_ACTIVE);
            }
        ]);

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    } else {
        $query->where('status', '!=', 'pending');
    }

    $logistics = $query
        ->latest()
        ->paginate(15)
        ->withQueryString();

    return view('admin.logistics.index', compact('logistics'));
}

    public function show(Logistic $logistic)
{
    $logistic->load('owner');

    $pendingRiders = $logistic->riders()
        ->whereHas('roles', function ($query) {
            $query->where('name', 'rider');
        })
        ->where('logistic_status', 'pending')
        ->get();

    $approvedRiders = $logistic->riders()
        ->whereHas('roles', function ($query) {
            $query->where('name', 'rider');
        })
        ->where('logistic_status', 'approved')
        ->where('status', User::STATUS_ACTIVE)
        ->get();

    $rejectedRiders = $logistic->riders()
        ->whereHas('roles', function ($query) {
            $query->where('name', 'rider');
        })
        ->where('logistic_status', 'rejected')
        ->get();

    return view(
        'admin.logistics.show',
        compact(
            'logistic',
            'pendingRiders',
            'approvedRiders',
            'rejectedRiders'
        )
    );
}

    public function approve(Logistic $logistic)
    {
        $logistic->update([
            'status' => 'active',
            'approved_at' => now(),
        ]);

        $this->createNotification(
            $logistic->owner_user_id,
            'Logistics Company Approved',
            'Your logistics company ' . $logistic->company_name . ' has been approved.',
            'logistic'
        );

        return back()->with('success', 'Logistics company approved successfully.');
    }

    public function reject(Request $request, Logistic $logistic)
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $logistic->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        $this->createNotification(
            $logistic->owner_user_id,
            'Logistics Company Rejected',
            'Your logistics company ' . $logistic->company_name . ' was rejected. Reason: ' . $request->rejection_reason,
            'logistic'
        );

        return back()->with('success', 'Logistics company rejected.');
    }

    public function suspend(Logistic $logistic)
    {
        $logistic->update(['status' => 'suspended']);

        $this->createNotification(
            $logistic->owner_user_id,
            'Logistics Company Suspended',
            'Your logistics company ' . $logistic->company_name . ' has been suspended.',
            'logistic'
        );

        return back()->with('success', 'Logistics company suspended.');
    }

    public function activate(Logistic $logistic)
    {
        $logistic->update(['status' => 'active']);

        return back()->with('success', 'Logistics company activated.');
    }
}
