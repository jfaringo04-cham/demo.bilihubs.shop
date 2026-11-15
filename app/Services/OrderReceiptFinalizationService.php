<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderReceiptFinalizationService
{
    // ============================================================
    // FINALIZE ORDER RECEIPT
    // ============================================================

    public function finalize(
        Order $order,
        string $source = 'manual'
    ): Order {
        return DB::transaction(function () use ($order, $source) {

            // Lock the parent order to prevent concurrent confirmation.
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Prevent repeated finalization and financial processing.
            if (
                $order->confirmed_received_at !== null ||
                (string) $order->status === 'completed'
            ) {
                return $order->fresh();
            }

            // The parent order must already be delivered.
            if ((string) $order->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'order' =>
                        'The order must be fully delivered before confirming receipt.',
                ]);
            }

            // Retrieve all seller orders.
            $sellerOrders = SellerOrder::query()
                ->where('order_id', $order->id)
                ->get(['id', 'status']);

            if ($sellerOrders->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'No seller orders were found.',
                ]);
            }

            // All seller orders must be delivered.
            $hasUndeliveredSellerOrder = $sellerOrders->contains(
                fn ($sellerOrder) =>
                    strtolower((string) $sellerOrder->status)
                        !== 'delivered'
            );

            if ($hasUndeliveredSellerOrder) {
                throw ValidationException::withMessages([
                    'order' =>
                        'Some seller orders are not delivered yet.',
                ]);
            }

            $sellerOrderIds = $sellerOrders->pluck('id');

            // Retrieve all shipments belonging to this order.
            $shipments = Shipment::query()
                ->whereIn('seller_order_id', $sellerOrderIds)
                ->get();

            if ($shipments->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' =>
                        'No delivery shipments were found.',
                ]);
            }

            // Every seller order must have a shipment.
            $shipmentSellerOrderIds = $shipments
                ->pluck('seller_order_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            if (
                $shipmentSellerOrderIds->count()
                    !== $sellerOrders->count()
            ) {
                throw ValidationException::withMessages([
                    'order' =>
                        'Some seller orders have no delivery shipment.',
                ]);
            }

            // Every shipment must be delivered and have proof
            // of delivery completion recorded.
            $hasUndeliveredShipment = $shipments->contains(
                fn ($shipment) =>
                    strtolower((string) $shipment->status)
                        !== 'delivered'
                    || $shipment->delivered_at === null
            );

            if ($hasUndeliveredShipment) {
                throw ValidationException::withMessages([
                    'order' =>
                        'All parcels must be delivered before confirming receipt.',
                ]);
            }

            // ====================================================
            // MARK ORDER AS COMPLETED
            // ====================================================

            $order->confirmed_received_at = now();
            $order->status = 'completed';
            $order->save();

            // ====================================================
            // GENERATE COMMISSIONS
            // ====================================================

            app(CommissionService::class)
                ->generateForOrder($order);

            // ====================================================
            // EXISTING WALLET AND SETTLEMENT WORKFLOW
            // ====================================================

            $this->creditRiders($order);

            $this->creditLogistics($order);

            $this->retrySellerSettlements($order);

            return $order->fresh();
        });
    }

    // ============================================================
    // AUTO-CONFIRM ELIGIBILITY
    // ============================================================

    public function isEligibleForAutoConfirm(Order $order): bool
    {
        if ($order->confirmed_received_at) {
            return false;
        }

        if ((string) $order->status !== 'delivered') {
            return false;
        }

        $sellerOrders = SellerOrder::query()
            ->where('order_id', $order->id)
            ->get(['id', 'status']);

        if ($sellerOrders->isEmpty()) {
            return false;
        }

        if ($sellerOrders->contains(
            fn ($sellerOrder) =>
                strtolower((string) $sellerOrder->status)
                    !== 'delivered'
        )) {
            return false;
        }

        $sellerOrderIds = $sellerOrders->pluck('id');

        $shipments = Shipment::query()
            ->whereIn('seller_order_id', $sellerOrderIds)
            ->get();

        if ($shipments->isEmpty()) {
            return false;
        }

        $shipmentSellerOrderIds = $shipments
            ->pluck('seller_order_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        if (
            $shipmentSellerOrderIds->count()
                !== $sellerOrders->count()
        ) {
            return false;
        }

        if ($shipments->contains(
            fn ($shipment) =>
                strtolower((string) $shipment->status)
                    !== 'delivered'
                || $shipment->delivered_at === null
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
            (int) config(
                'order_receipt.auto_confirm_after_days',
                3
            )
        );

        return \Illuminate\Support\Carbon::parse(
            $latestDeliveredAt
        )->lte(now()->subDays($days));
    }

    // ============================================================
    // RIDER WALLET CREDIT
    // ============================================================

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
            ->each(
                fn (Shipment $shipment) =>
                    $walletService->creditDeliveredShipment(
                        $shipment
                    )
            );
    }

    // ============================================================
    // LOGISTICS WALLET CREDIT
    // ============================================================

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
            ->each(
                fn (Shipment $shipment) =>
                    $walletService->creditConfirmedShipment(
                        $shipment
                    )
            );
    }

    // ============================================================
    // SELLER SETTLEMENT
    // ============================================================

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
                    // SellerSettlementService expects seller order ID.
                    $service->createIfEligible(
                        (int) $sellerOrder->id
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}
