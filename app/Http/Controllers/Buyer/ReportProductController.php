<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\ComplianceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportProductController extends Controller
{
    public function create(Product $product)
    {
        if ($product->compliance_status !== 'approved') {
            abort(404);
        }
        return view('buyer.report-product', compact('product'));
    }

    public function store(Request $request, Product $product)
    {
        $request->validate([
            'reason' => 'required|in:scam,offensive,misleading,copyright,other',
            'details' => 'required|string|max:1000',
        ]);

        SupportTicket::create([
            'user_id' => Auth::id(),
            'type' => 'complaint',
            'subject' => 'Product Report: ' . $product->name,
            'message' => 'Reason: ' . $request->reason . "\n\nDetails: " . $request->details,
            'priority' => 'high',
            'status' => 'open',
        ]);

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'New Product Report',
                'message' => Auth::user()->name . " reported product \"{$product->name}\" for: {$request->reason}.",
                'type' => 'product_report',
                'link' => route('admin.compliance.show', $product),
            ]);
        }

        ComplianceMonitor::checkUserReports($product);

        return back()->with('success', 'Thank you for your report. Our team will review this product.');
    }
}
