<?php

namespace App\Services;

use App\Models\Logistic;
use App\Models\LogisticWallet;
use App\Models\LogisticWalletTransaction;
use App\Models\RiderWalletTransaction;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class LogisticWalletService
{
    public const TYPE_SHIPMENT_CREDIT = 'shipment_credit';

    public function walletForLogistic(int $logisticId): LogisticWallet
    {
        return LogisticWallet::firstOrCreate(
            ['logistic_id' => $logisticId],
            ['available_balance_minor' => 0]
        );
    }

    public function sellerOrderForShipment(Shipment $shipment): ?SellerOrder
    {
        if (empty($shipment->seller_order_id)) {
            return null;
        }

        return SellerOrder::query()->with('order')->find((int) $shipment->seller_order_id);
    }

    public function resolveLogistic(Shipment $shipment): ?Logistic
    {
        $logisticId = (int) ($shipment->logistic_id ?: 0);

        if ($logisticId <= 0) {
            $logisticId = (int) ($this->sellerOrderForShipment($shipment)?->logistic_id ?: 0);
        }

        return $logisticId > 0 ? Logistic::find($logisticId) : null;
    }

    public function calculateLogisticsEarningMinor(Shipment $shipment): int
    {
        $sellerOrder = $this->sellerOrderForShipment($shipment);

        if (!$sellerOrder) {
            return 0;
        }

        $shippingMinor = max(0, (int) ($sellerOrder->shipping_minor ?? 0));
        $pickupTarget = min(
            max(0, (int) config('rider_wallet.pickup_earning_minor', 1500)),
            $shippingMinor
        );
        $configuredLogisticsPay = max(
            0,
            (int) config('rider_wallet.logistics_earning_minor', 1000)
        );

        return min(
            $configuredLogisticsPay,
            max(0, $shippingMinor - $pickupTarget)
        );
    }

    public function creditConfirmedShipment(Shipment $shipment): ?LogisticWalletTransaction
    {
        if (strtolower((string) $shipment->status) !== 'delivered') {
            return null;
        }

        $sellerOrder = $this->sellerOrderForShipment($shipment);

        if (!$sellerOrder || !$sellerOrder->order?->confirmed_received_at) {
            return null;
        }

        $logistic = $this->resolveLogistic($shipment);

        if (!$logistic) {
            return null;
        }

        $existing = LogisticWalletTransaction::query()
            ->where('shipment_id', $shipment->id)
            ->where('type', self::TYPE_SHIPMENT_CREDIT)
            ->first();

        if ($existing) {
            return $existing;
        }

        $shippingMinor = max(0, (int) ($sellerOrder->shipping_minor ?? 0));
        $targetMinor = $this->calculateLogisticsEarningMinor($shipment);

        if ($targetMinor <= 0 || $shippingMinor <= 0) {
            return null;
        }

        $riderFundedMinor = (int) RiderWalletTransaction::query()
            ->where('shipment_id', $shipment->id)
            ->whereIn('type', [
                RiderWalletService::TYPE_PICKUP_CREDIT,
                RiderWalletService::TYPE_DELIVERY_CREDIT,
                RiderWalletService::TYPE_DELIVERY_ADJUSTMENT,
            ])
            ->sum('amount_minor');

        $alreadyLogisticsFundedMinor = (int) LogisticWalletTransaction::query()
            ->where('shipment_id', $shipment->id)
            ->where('type', self::TYPE_SHIPMENT_CREDIT)
            ->sum('amount_minor');

        $remainingShippingMinor = max(
            0,
            $shippingMinor - $riderFundedMinor - $alreadyLogisticsFundedMinor
        );
        $amountMinor = min($targetMinor, $remainingShippingMinor);

        if ($amountMinor <= 0) {
            return null;
        }

        return DB::transaction(function () use (
            $shipment,
            $sellerOrder,
            $logistic,
            $shippingMinor,
            $amountMinor
        ) {
            $existing = LogisticWalletTransaction::query()
                ->where('shipment_id', $shipment->id)
                ->where('type', self::TYPE_SHIPMENT_CREDIT)
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = $this->walletForLogistic((int) $logistic->id);
            $wallet = LogisticWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = LogisticWalletTransaction::query()
                ->where('shipment_id', $shipment->id)
                ->where('type', self::TYPE_SHIPMENT_CREDIT)
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet->available_balance_minor =
                (int) $wallet->available_balance_minor + $amountMinor;
            $wallet->save();

            return LogisticWalletTransaction::create([
                'logistic_wallet_id' => $wallet->id,
                'shipment_id' => $shipment->id,
                'type' => self::TYPE_SHIPMENT_CREDIT,
                'amount_minor' => $amountMinor,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => 'LOGISTICS-' . $shipment->id,
                'description' => 'Logistics shipping-fee earning for shipment '
                    . ($shipment->tracking_number ?: ('#' . $shipment->id)) . '.',
                'meta' => [
                    'tracking_number' => $shipment->tracking_number,
                    'seller_order_id' => $sellerOrder->id,
                    'logistic_id' => $logistic->id,
                    'shipping_fee_minor' => $shippingMinor,
                    'logistics_earning_minor' => $amountMinor,
                    'funding_source' => 'shipping_fee',
                ],
            ]);
        });
    }
}
