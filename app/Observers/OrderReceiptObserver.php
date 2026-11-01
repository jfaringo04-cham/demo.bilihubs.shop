<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderReceiptFinalizationService;
use Illuminate\Support\Facades\Schema;

class OrderReceiptObserver
{
    public function saved(Order $order): void
    {
        if (
            !Schema::hasColumn('orders', 'confirmed_received_at')
            || !$order->confirmed_received_at
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | RELEASE CONFIRMED-RECEIPT EARNINGS
        |--------------------------------------------------------------------------
        |
        | Existing manual Confirm Received actions update the Order directly.
        | This observer makes sure all shipping-fee funded earnings are released:
        |
        | 1. Pickup / linehaul / final-mile rider earnings
        | 2. Logistics provider earning
        | 3. Seller settlement retry
        |
        | Wallet services are idempotent, so saving the same confirmed order
        | again will not create duplicate shipment credits.
        |
        */

        $service = app(OrderReceiptFinalizationService::class);

        $service->creditRiders($order);
        $service->creditLogistics($order);
        $service->retrySellerSettlements($order);
    }
}