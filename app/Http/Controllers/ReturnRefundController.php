<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Order;
use App\Models\ReturnRefund;
use App\Services\ReturnRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReturnRefundController extends Controller
{
    public function saveMethod(Request $request, Order $order, int $sellerOrderId)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        $sellerOrder = $order->sellerOrders()->findOrFail($sellerOrderId);

        // Keep payout account details out of flashed form input.
        $validator = Validator::make($request->only('method', 'account_name', 'account_number', 'bank_name'), [
            'method' => 'required|in:gcash,bank',
            'account_name' => 'required|string|max:100',
            'account_number' => 'required|string|regex:/^[0-9]{6,30}$/D',
            'bank_name' => 'exclude_unless:method,bank|required|string|max:100',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        DB::transaction(function () use ($sellerOrder, $validator) {
            $refund = ReturnRefund::where('seller_order_id', $sellerOrder->id)->lockForUpdate()->firstOrFail();
            if ($refund->sent_at || $refund->payout_details !== null) {
                throw ValidationException::withMessages([
                    'return_refund' => 'Refund details are already on file.',
                ]);
            }

            $refund->update(['payout_details' => $validator->validated()]);
            if ($sellerOrder->seller?->user_id) {
                Notification::create([
                    'user_id' => $sellerOrder->seller->user_id,
                    'title' => 'Refund Account Ready',
                    'message' => 'The buyer saved their refund method. Open the order to check whether you can send the refund.',
                    'type' => 'return',
                    'link' => route('seller.orders.show', $sellerOrder->order_id, false),
                    'is_read' => false,
                ]);
            }
        });

        return back()->with('success', 'Refund account details saved.');
    }

    public function markSent(Request $request, Order $order, int $sellerOrderId, ReturnRefundService $service)
    {
        $sellerId = $request->user()->seller?->id;
        abort_unless($sellerId, 403);
        $sellerOrder = $order->sellerOrders()->where('seller_id', $sellerId)->findOrFail($sellerOrderId);
        $validated = $request->validate([
            'transfer_reference' => 'required|string|max:120',
            'confirmed_sent' => 'accepted',
        ]);

        DB::transaction(function () use ($sellerOrder, $validated, $service) {
            $refund = ReturnRefund::where('seller_order_id', $sellerOrder->id)->lockForUpdate()->firstOrFail();
            if ($refund->sent_at || !$refund->payout_details || !$service->proceedsReleased($sellerOrder)) {
                throw ValidationException::withMessages([
                    'return_refund' => 'This refund is not eligible to be marked sent.',
                ]);
            }

            $refund->update([
                'transfer_reference' => $validated['transfer_reference'],
                'sent_at' => now(),
            ]);
            $service->notifyBuyer($sellerOrder, 'Refund Sent', 'Check the transfer reference in your order details.');
        });

        return back()->with('success', 'Refund transfer recorded.');
    }

    public function respond(Request $request, Order $order, int $sellerOrderId)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        $sellerOrder = $order->sellerOrders()->findOrFail($sellerOrderId);
        $validated = $request->validate(['response' => 'required|in:received,missing']);

        DB::transaction(function () use ($sellerOrder, $validated) {
            $refund = ReturnRefund::where('seller_order_id', $sellerOrder->id)->lockForUpdate()->firstOrFail();
            $isMissing = $validated['response'] === 'missing';
            if (!$refund->sent_at || $refund->received_at || ($isMissing && $refund->reported_at)) {
                throw ValidationException::withMessages(['return_refund' => 'This refund cannot be updated.']);
            }

            $refund->update([$isMissing ? 'reported_at' : 'received_at' => now()]);
            if ($isMissing && $sellerOrder->seller?->user_id) {
                Notification::create([
                    'user_id' => $sellerOrder->seller->user_id,
                    'title' => 'Refund Reported Missing',
                    'message' => 'The buyer has not received the refund for order ' . $sellerOrder->order->order_number . '.',
                    'type' => 'return',
                    'link' => route('seller.orders.show', $sellerOrder->order_id, false),
                    'is_read' => false,
                ]);
            }
        });

        return back()->with('success', 'Refund status updated.');
    }

    public function startExisting(Request $request, Order $order, int $sellerOrderId, ReturnRefundService $service)
    {
        $sellerId = $request->user()->seller?->id;
        abort_unless($sellerId, 403);
        $request->validate(['confirmed_unpaid' => 'accepted']);

        DB::transaction(function () use ($order, $sellerId, $sellerOrderId, $service) {
            $sellerOrder = $order->sellerOrders()->where('seller_id', $sellerId)
                ->lockForUpdate()->findOrFail($sellerOrderId);
            if ($sellerOrder->return_resolution !== 'refund'
                || ReturnRefund::where('seller_order_id', $sellerOrder->id)->exists()) {
                throw ValidationException::withMessages(['return_refund' => 'Refund tracking has already started or is unavailable.']);
            }

            $service->start($sellerOrder);
        });

        return back()->with('success', 'Refund tracking started.');
    }
}
