<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ComplianceNotification;
use App\Models\Product;
use App\Models\User;
use App\Models\Warning;
use App\Models\Notification;
use App\Services\ComplianceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ComplianceController extends Controller
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

    private function sendEmailToUser($user, $subject, $message)
    {
        try {
            Mail::to($user->email)->send(new ComplianceNotification($user, $subject, $message));
        } catch (\Throwable $e) {
            logger()->error('Failed to send compliance email to ' . $user->email . ': ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $query = Product::with(['seller', 'category']);

        $filter = $request->input('filter', 'flagged');

        if ($filter === 'flagged') {
            $query->whereIn('compliance_status', ['flagged', 'auto_flagged']);
        } elseif ($filter === 'auto_flagged') {
            $query->where('compliance_status', 'auto_flagged');
        } elseif ($filter === 'resubmitted') {
            $query->where('compliance_status', 'pending')
                ->whereColumn('updated_at', '>', 'created_at');
        } elseif ($filter === 'rejected') {
            $query->where('compliance_status', 'rejected');
        } elseif ($filter === 'mismatch') {
            $query->whereHas('seller', function ($q) {
                $q->whereNotNull('selling_categories');
            });
        } else {
            $query->whereIn('compliance_status', ['pending', 'flagged', 'auto_flagged', 'rejected']);
        }

        $products = $query->latest()->paginate(20);

        $sellers = User::where('role', 'seller')->get();

        return view('admin.compliance', compact('products', 'sellers', 'filter'));
    }

    public function show(Product $product)
    {
        $product->load(['seller', 'category']);
        $warnings = $product->seller ? $product->seller->warnings()->latest()->get() : collect();
        $isMismatch = $product->seller
            && is_array($product->seller->selling_categories)
            && $product->category_id
            && !in_array($product->category_id, $product->seller->selling_categories);

        return view('admin.compliance-show', compact('product', 'warnings', 'isMismatch'));
    }

    public function approve(Product $product)
    {
        $wasResubmitted = $product->compliance_status === 'pending'
            && $product->updated_at
            && $product->updated_at->gt($product->created_at);

        $product->update([
            'compliance_status' => 'approved',
            'flagged_reason' => null,
            'flagged_at' => null,
            'admin_notes' => null,
        ]);

        if ($product->seller) {
            $title = $wasResubmitted ? 'Product Approved - Back in Your Shop' : 'Product Approved';
            $message = $wasResubmitted
                ? 'Your resubmitted product "' . $product->name . '" has been approved and is now live again in your shop. Buyers can now purchase it.'
                : 'Your product "' . $product->name . '" has been approved and is now live on the platform.';

            $this->createNotification(
                $product->seller->id,
                $title,
                $message,
                'product',
                route('seller.products')
            );

            $this->sendEmailToUser(
                $product->seller,
                'Product Approved - ' . $product->name,
                $message
            );
        }

        $successMsg = $wasResubmitted
            ? 'Resubmitted product approved and republished to the seller\'s shop.'
            : 'Product approved and now live on the platform.';

        return back()->with('success', $successMsg);
    }

    public function flag(Request $request, Product $product)
    {
        $request->validate([
            'flagged_reason' => ['required', 'string', 'max:1000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $product->update([
            'compliance_status' => 'flagged',
            'flagged_reason' => $request->flagged_reason,
            'admin_notes' => $request->admin_notes,
            'flagged_at' => now(),
        ]);

        if ($product->seller) {
            $days = Product::RESUBMIT_DEADLINE_DAYS;
            $notifMessage = 'Your product "' . $product->name . '" was flagged for compliance review: ' . $request->flagged_reason;
            if ($request->filled('admin_notes')) {
                $notifMessage .= "\n\nAdmin Instructions: " . $request->admin_notes;
            }
            $notifMessage .= "\n\nYou have {$days} days to fix and resubmit this product, otherwise it will be permanently removed.";

            $this->createNotification(
                $product->seller->id,
                'Product Flagged - ' . $days . '-Day Deadline',
                $notifMessage,
                'product',
                route('seller.products.edit', $product)
            );

            $emailMessage = $notifMessage . "\n\nPlease update your product as per the admin's instructions and save it. The product will be automatically resubmitted for review.";

            $this->sendEmailToUser(
                $product->seller,
                'Product Flagged - ' . $product->name,
                $emailMessage
            );
        }

        return back()->with('success', 'Product flagged for violation. Seller has been notified with a ' . Product::RESUBMIT_DEADLINE_DAYS . '-day deadline.');
    }

    public function issueWarning(Request $request, Product $product)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'in:product_violation,policy_violation,other'],
        ]);

        if (!$product->seller) {
            return back()->with('error', 'This product has no seller.');
        }

        Warning::create([
            'user_id' => $product->seller->id,
            'admin_id' => auth()->id(),
            'type' => $request->type,
            'reason' => $request->reason,
        ]);

        $this->createNotification(
            $product->seller->id,
            'Compliance Warning Issued',
            'A compliance warning was issued regarding: ' . $request->reason,
            'product',
            route('seller.products')
        );

        $this->sendEmailToUser(
            $product->seller,
            'Compliance Warning - ' . $product->name,
            'A compliance warning has been issued regarding your product "' . $product->name . '". Reason: ' . $request->reason
        );

        return back()->with('success', 'Warning issued to seller.');
    }

    public function suspendSeller(Request $request, Product $product)
    {
        if (!$product->seller) {
            return back()->with('error', 'This product has no seller.');
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $product->seller->update(['status' => User::STATUS_SUSPENDED, 'suspended_at' => now()]);
        $product->update(['compliance_status' => 'flagged']);

        $this->createNotification(
            $product->seller->id,
            'Account Suspended',
            'Your seller account has been suspended due to compliance violations.' .
                ($request->reason ? ' Reason: ' . $request->reason : ''),
            'account'
        );

        $this->sendEmailToUser(
            $product->seller,
            'Account Suspended',
            'Your seller account has been suspended due to compliance violations.' .
                ($request->reason ? ' Reason: ' . $request->reason : '') .
                ' Please contact support for more information.'
        );

        return back()->with('success', 'Seller account suspended.');
    }

    public function rescan(Product $product)
    {
        ComplianceMonitor::recordPriceSnapshot($product);
        $triggers = ComplianceMonitor::checkProduct($product);

        if (empty($triggers)) {
            return back()->with('success', 'Image scan completed. No violations detected — product remains live.');
        }

        $summary = collect($triggers)->map(fn($t) => '[' . strtoupper($t['severity']) . '] ' . $t['message'])->implode("\n");
        return back()->with('warning', "Image scan completed. " . count($triggers) . " violation(s) found:\n\n" . $summary);
    }

    public function blacklistImage(Request $request, Product $product)
    {
        $request->validate([
            'image_path' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $fullPath = storage_path('app/public/' . $request->image_path);
        if (!file_exists($fullPath)) {
            return back()->with('error', 'Image file not found on disk.');
        }

        $hash = ComplianceMonitor::hashImage($fullPath);
        $phash = ComplianceMonitor::perceptualHash($fullPath);
        ComplianceMonitor::blacklistImage($hash, $request->reason, $phash);

        return back()->with('success', 'Image added to blacklist. Future uploads matching this hash or perceptual hash will be auto-flagged.');
    }
}
