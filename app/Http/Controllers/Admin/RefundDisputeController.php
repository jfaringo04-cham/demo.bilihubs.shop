<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ReturnRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RefundDisputeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->validate(['status' => 'nullable|in:open,under_review,resolved'])['status'] ?? 'open';

        $query = ReturnRefund::query()->with(['sellerOrder.order', 'sellerOrder.seller'])
            ->whereNotNull('reported_at');

        if ($status === 'resolved') {
            $query->where(function ($query) {
                $query->whereNotNull('dispute_resolved_at')->orWhereNotNull('received_at');
            });
        } else {
            $query->whereNull('dispute_resolved_at')->whereNull('received_at');
            $status === 'under_review'
                ? $query->whereNotNull('dispute_reviewed_at')
                : $query->whereNull('dispute_reviewed_at');
        }

        $disputes = $query->orderByDesc('reported_at')->paginate(20)->withQueryString();

        return view('admin.refund-disputes', compact('disputes', 'status'));
    }

    public function update(Request $request, ReturnRefund $returnRefund)
    {
        $validated = $request->validate([
            'action' => 'required|in:review,request_proof,resolve',
            'resolution' => 'required_if:action,resolve|nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $returnRefund, $validated) {
            $refund = ReturnRefund::whereNotNull('reported_at')->lockForUpdate()->findOrFail($returnRefund->id);
            if ($refund->dispute_resolved_at || $refund->received_at) {
                throw ValidationException::withMessages(['action' => 'This dispute is already resolved.']);
            }

            $sellerOrder = $refund->sellerOrder;
            if ($validated['action'] === 'review') {
                if (!$refund->dispute_reviewed_at) {
                    $refund->update(['dispute_reviewed_at' => now()]);
                }
            } elseif ($validated['action'] === 'request_proof') {
                if ($refund->dispute_proof_requested_at) {
                    throw ValidationException::withMessages(['action' => 'Transfer proof has already been requested.']);
                }

                $sellerId = $sellerOrder->seller?->user_id;
                abort_unless($sellerId, 422, 'There is no seller account for this refund.');
                $refund->update([
                    'dispute_reviewed_at' => $refund->dispute_reviewed_at ?? now(),
                    'dispute_proof_requested_at' => now(),
                ]);
                $this->notify($sellerId, 'Refund Transfer Proof Requested',
                    'Upload the refund transfer receipt in the seller order details.',
                    route('seller.orders.show', $sellerOrder->order_id, false));
            } else {
                $refund->update([
                    'dispute_reviewed_at' => $refund->dispute_reviewed_at ?? now(),
                    'dispute_resolved_at' => now(),
                    'dispute_resolved_by' => $request->user()->id,
                    'dispute_resolution' => $validated['resolution'],
                ]);

                $this->notify($sellerOrder->order->user_id, 'Refund Dispute Resolved',
                    'View the resolution in your order details.',
                    route('orders.show', $sellerOrder->order_id, false));
                if ($sellerId = $sellerOrder->seller?->user_id) {
                    $this->notify($sellerId, 'Refund Dispute Resolved',
                        'View the resolution in your seller order details.',
                        route('seller.orders.show', $sellerOrder->order_id, false));
                }
            }
        });

        return back()->with('success', 'Refund dispute updated.');
    }

    public function proof(ReturnRefund $returnRefund)
    {
        $disk = Storage::disk('local');
        abort_unless($returnRefund->reported_at && $returnRefund->dispute_proof_path
            && $disk->exists($returnRefund->dispute_proof_path), 404);

        return $disk->response($returnRefund->dispute_proof_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function notify(int $userId, string $title, string $message, string $link): void
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'return',
            'link' => $link,
            'is_read' => false,
        ]);
    }
}
