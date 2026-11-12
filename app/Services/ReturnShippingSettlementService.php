<?php

namespace App\Services;

use App\Models\SellerOrder;

class ReturnShippingSettlementService
{
    public const CREDIT_TYPE = 'return_shipping_credit';

    public static function quoteRiderShare(SellerOrder $sellerOrder, int $fee): int
    {
        $pickup = min($fee, max(0, (int) config('rider_wallet.pickup_earning_minor', 1500)));
        $delivery = max(0, (int) config('rider_wallet.delivery_earning_minor', 2500))
            + ShippingBonusService::deliveryExtraForOrderMinor($sellerOrder);

        return $pickup + min($delivery, $fee - $pickup);
    }

    /**
     * Call inside the transaction that locks the seller order and return shipment.
     * A false result leaves the charge pending for a later retry.
     */
    public function settle(SellerOrder $sellerOrder): bool
    {
        $shipment = $sellerOrder->returnShipment()->lockForUpdate()->first();

        if (!$shipment || $shipment->seller_shipping_fee_minor === null
            || $shipment->rider_payout_minor === null || $shipment->wallet_settled_at) {
            return true;
        }

        if (!$sellerOrder->return_received_at || !$shipment->delivered_at
            || !$shipment->seller_shipping_fee_posted_at || !$shipment->rider_id) {
            return false;
        }

        $fee = $shipment->seller_shipping_fee_minor;
        if ($fee === 0) {
            $shipment->update(['wallet_settled_at' => now()]);

            return true;
        }

        $sellerWallet = app(SellerWalletService::class)->walletForSeller($sellerOrder->seller_id);
        $sellerWallet = $sellerWallet->newQuery()->lockForUpdate()->findOrFail($sellerWallet->id);

        if ($sellerWallet->available_balance_minor < $fee) {
            return false;
        }

        $riderShare = $shipment->rider_payout_minor;
        $logisticsShare = $fee - $riderShare;
        $entry = [
            'reference' => $shipment->tracking_number,
            'description' => 'Return shipping for ' . $shipment->tracking_number,
            'meta' => [
                'return_shipment_id' => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'funding_source' => 'seller_wallet',
            ],
        ];

        $sellerWallet->available_balance_minor -= $fee;
        $sellerWallet->save();
        $sellerWallet->transactions()->create($entry + [
            'type' => 'return_shipping_debit',
            'amount_minor' => -$fee,
            'balance_after_minor' => $sellerWallet->available_balance_minor,
        ]);

        if ($riderShare > 0) {
            $wallet = app(RiderWalletService::class)->walletForRider($shipment->rider_id);
            $wallet = $wallet->newQuery()->lockForUpdate()->findOrFail($wallet->id);
            $wallet->available_balance_minor += $riderShare;
            $wallet->save();
            $wallet->transactions()->create($entry + [
                'type' => self::CREDIT_TYPE,
                'amount_minor' => $riderShare,
                'balance_after_minor' => $wallet->available_balance_minor,
            ]);
        }

        if ($logisticsShare > 0) {
            $wallet = app(LogisticWalletService::class)->walletForLogistic($shipment->logistic_id);
            $wallet = $wallet->newQuery()->lockForUpdate()->findOrFail($wallet->id);
            $wallet->available_balance_minor += $logisticsShare;
            $wallet->save();
            $wallet->transactions()->create($entry + [
                'type' => self::CREDIT_TYPE,
                'amount_minor' => $logisticsShare,
                'balance_after_minor' => $wallet->available_balance_minor,
            ]);
        }

        $shipment->update(['wallet_settled_at' => now()]);

        return true;
    }
}
