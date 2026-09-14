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
        $query = Logistic::with('owner');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $logistics = $query->latest()->paginate(15);

        return view('admin.logistics.index', compact('logistics'));
    }

    public function show(Logistic $logistic)
    {
        $logistic->load(['owner', 'riders']);

        $pendingRiders = $logistic->riders()->where('logistic_status', 'pending')->get();
        $approvedRiders = $logistic->riders()->where('logistic_status', 'approved')->get();
        $rejectedRiders = $logistic->riders()->where('logistic_status', 'rejected')->get();

        return view('admin.logistics.show', compact('logistic', 'pendingRiders', 'approvedRiders', 'rejectedRiders'));
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

    public function approveRider(User $rider)
    {
        $rider->update([
            'logistic_status' => 'approved',
            'logistic_approved_at' => now(),
        ]);

        $this->createNotification(
            $rider->id,
            'Rider Application Approved',
            'Your application to join ' . ($rider->logistic->company_name ?? 'a logistics company') . ' has been approved by admin.',
            'logistic'
        );

        return back()->with('success', 'Rider approval confirmed.');
    }

    public function rejectRider(Request $request, User $rider)
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $rider->update([
            'logistic_status' => 'rejected',
            'logistic_rejection_reason' => $request->rejection_reason,
            'logistic_id' => null,
        ]);

        $this->createNotification(
            $rider->id,
            'Rider Application Rejected',
            'Your application to join ' . ($rider->logistic->company_name ?? 'a logistics company') . ' was rejected. Reason: ' . $request->rejection_reason,
            'logistic'
        );

        return back()->with('success', 'Rider rejected.');
    }
}
