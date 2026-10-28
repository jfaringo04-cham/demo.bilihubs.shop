<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderReceiptFinalizationService
{
    public function finalize(Order $order, string $source = 'manual'): Order
    {
        return DB::transaction(function () use ($order, $source) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$order->confirmed_received_at) {
                $order->confirmed_received_at = now();
            }

            if ((string) $order->status !== 'completed') {
                $order->status = 'completed';
            }

            $order->save();

            $this->creditRiders($order);
            $this->creditLogistics($order);
            $this->retrySellerSettlements($order);

            return $order->fresh();
        });
    }

    public function isEligibleForAutoConfirm(Order $order): bool
    {
        if ($order->confirmed_received_at) {
            return false;
        }

        if ((string) $order->status !== 'delivered') {
            return false;
        }

        $sellerOrderIds = SellerOrder::query()
            ->where('order_id', $order->id)
            ->pluck('id');

        if ($sellerOrderIds->isEmpty()) {
            return false;
        }

        $shipments = Shipment::query()
            ->whereIn('seller_order_id', $sellerOrderIds)
            ->get();

        if ($shipments->isEmpty()) {
            return false;
        }

        if ($shipments->contains(fn ($shipment) =>
            strtolower((string) $shipment->status) !== 'delivered'
            || !$shipment->delivered_at
        )) {
            return false;
        }

        $latestDeliveredAt = $shipments
            ->pluck('delivered_at')
            ->filter()
            ->max();

        if (!$latestDeliveredAt) {
            return false;
        }

        $days = max(
            1,
            (int) config('order_receipt.auto_confirm_after_days', 3)
        );

        return \Illuminate\Support\Carbon::parse($latestDeliveredAt)
            ->lte(now()->subDays($days));
    }

    public function creditRiders(Order $order): void
    {
        if (!$order->confirmed_received_at) {
            return;
        }

        if (
            !Schema::hasTable('rider_wallets')
            || !Schema::hasTable('rider_wallet_transactions')
        ) {
            return;
        }

        $sellerOrderIds = SellerOrder::query()
            ->where('order_id', $order->id)
            ->pluck('id');

        if ($sellerOrderIds->isEmpty()) {
            return;
        }

        $walletService = app(RiderWalletService::class);

        Shipment::query()
            ->whereIn('seller_order_id', $sellerOrderIds)
            ->where('status', 'delivered')
            ->get()
            ->each(fn (Shipment $shipment) =>
                $walletService->creditDeliveredShipment($shipment)
            );
    }

    public function creditLogistics(Order $order): void
    {
        if (!$order->confirmed_received_at) {
            return;
        }

        if (
            !Schema::hasTable('logistic_wallets')
            || !Schema::hasTable('logistic_wallet_transactions')
        ) {
            return;
        }

        $sellerOrderIds = SellerOrder::query()
            ->where('order_id', $order->id)
            ->pluck('id');

        if ($sellerOrderIds->isEmpty()) {
            return;
        }

        $walletService = app(LogisticWalletService::class);

        Shipment::query()
            ->whereIn('seller_order_id', $sellerOrderIds)
            ->where('status', 'delivered')
            ->get()
            ->each(fn (Shipment $shipment) =>
                $walletService->creditConfirmedShipment($shipment)
            );
    }

    public function retrySellerSettlements(Order $order): void
    {
        if (!$order->confirmed_received_at) {
            return;
        }

        if (!class_exists(SellerSettlementService::class)) {
            return;
        }

        $service = app(SellerSettlementService::class);

        if (!method_exists($service, 'createIfEligible')) {
            return;
        }

        SellerOrder::query()
            ->where('order_id', $order->id)
            ->get()
            ->each(function (SellerOrder $sellerOrder) use ($service) {
                try {
                    // SellerSettlementService expects the seller order ID (int),
                    // not a SellerOrder model instance.
                    $service->createIfEligible((int) $sellerOrder->id);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}
