<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\ReturnRefund;
use App\Models\SellerOrder;
use App\Models\SellerSettlement;
use Illuminate\Validation\ValidationException;

class ReturnRefundService
{
    public function start(SellerOrder $sellerOrder): ReturnRefund
    {
        if (!$sellerOrder->return_received_at) {
            throw ValidationException::withMessages(['return_refund' => 'Confirm receipt of the returned item first.']);
        }

        $order = $sellerOrder->order;
        if ($order->payment_method !== 'cod' || $order->payment_status !== 'paid') {
            throw ValidationException::withMessages([
                'return_refund' => 'Manual refund tracking is available for paid COD orders.',
            ]);
        }

        $item = $sellerOrder->items()->find($sellerOrder->return_order_item_id);
        $quantity = (int) $sellerOrder->return_quantity;
        if (!$item || $quantity < 1 || $quantity > $item->quantity) {
            throw ValidationException::withMessages([
                'return_refund' => 'The returned item or quantity cannot be verified.',
            ]);
        }

        $paid = max(0, $item->subtotal_minor - (int) $item->discount_minor);
        $amount = intdiv($paid * $quantity, (int) $item->quantity);
        if ($amount === 0) {
            throw ValidationException::withMessages([
                'return_refund' => 'There is no paid merchandise amount to refund for this item.',
            ]);
        }

        $refund = ReturnRefund::create([
            'seller_order_id' => $sellerOrder->id,
            'amount_minor' => $amount,
        ]);

        $this->notifyBuyer($sellerOrder, 'Refund Pending', 'Choose a refund method in your order details.');

        return $refund;
    }

    public function proceedsReleased(SellerOrder $sellerOrder): bool
    {
        return SellerSettlement::where('seller_order_id', $sellerOrder->id)
            ->where('seller_id', $sellerOrder->seller_id)
            ->whereIn('status', ['wallet_credited', 'paid'])
            ->exists();
    }

    public function notifyBuyer(SellerOrder $sellerOrder, string $title, string $message): void
    {
        Notification::create([
            'user_id' => $sellerOrder->order->user_id,
            'title' => $title,
            'message' => $message,
            'type' => 'return',
            'link' => route('orders.show', $sellerOrder->order_id, false),
            'is_read' => false,
        ]);
    }
}
