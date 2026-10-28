<?php

namespace App\Services;

use App\Models\RiderWallet;
use App\Models\RiderWalletTransaction;
use App\Models\RiderWithdrawal;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderWalletService
{
    public const TYPE_PICKUP_CREDIT = 'pickup_credit';
    public const TYPE_DELIVERY_CREDIT = 'delivery_credit';
    public const TYPE_DELIVERY_ADJUSTMENT = 'delivery_credit_adjustment';

    public function walletForRider(int $riderUserId): RiderWallet
    {
        return RiderWallet::firstOrCreate(
            ['rider_id' => $riderUserId],
            [
                'available_balance_minor' => 0,
                'pending_balance_minor' => 0,
            ]
        );
    }

    public function earningTransactionTypes(): array
    {
        return [
            self::TYPE_PICKUP_CREDIT,
            self::TYPE_DELIVERY_CREDIT,
        ];
    }

    public function resolveRiderUser(Shipment $shipment): ?User
    {
        if (empty($shipment->rider_id)) {
            return null;
        }

        return User::find((int) $shipment->rider_id);
    }

    public function resolvePickupRiderUser(Shipment $shipment): ?User
    {
        if (empty($shipment->pickup_rider_id)) {
            return null;
        }

        return User::find((int) $shipment->pickup_rider_id);
    }

    public function sellerOrderForShipment(Shipment $shipment): ?SellerOrder
    {
        if (empty($shipment->seller_order_id)) {
            return null;
        }

        return SellerOrder::query()
            ->with('order')
            ->find((int) $shipment->seller_order_id);
    }

    public function orderIsConfirmedReceived(Shipment $shipment): bool
    {
        $sellerOrder = $this->sellerOrderForShipment($shipment);

        return (bool) ($sellerOrder?->order?->confirmed_received_at);
    }

    public function calculatePickupEarningMinor(Shipment $shipment): int
    {
        $sellerOrder = $this->sellerOrderForShipment($shipment);

        if (!$sellerOrder) {
            return 0;
        }

        $shippingMinor = max(0, (int) ($sellerOrder->shipping_minor ?? 0));
        $configuredPickupPay = max(
            0,
            (int) config('rider_wallet.pickup_earning_minor', 1500)
        );

        return min($configuredPickupPay, $shippingMinor);
    }

    /**
     * Final-delivery pay is reserved after the configured pickup share so the
     * combined rider payout can never exceed the shipment shipping fee.
     */
    public function calculateDeliveryEarningMinor(Shipment $shipment): int
    {
        $sellerOrder = $this->sellerOrderForShipment($shipment);

        if (!$sellerOrder) {
            return 0;
        }

        $shippingMinor = max(0, (int) ($sellerOrder->shipping_minor ?? 0));
        $pickupTarget = $this->calculatePickupEarningMinor($shipment);
        $logisticsTarget = min(
            max(0, (int) config('rider_wallet.logistics_earning_minor', 1000)),
            max(0, $shippingMinor - $pickupTarget)
        );
        $configuredDeliveryPay = max(
            0,
            (int) config('rider_wallet.delivery_earning_minor', 2500)
        );

        return min(
            $configuredDeliveryPay,
            max(0, $shippingMinor - $pickupTarget - $logisticsTarget)
        );
    }

    public function calculateTotalRiderEarningMinor(Shipment $shipment): int
    {
        return $this->calculatePickupEarningMinor($shipment)
            + $this->calculateDeliveryEarningMinor($shipment);
    }

    /**
     * Credit both shipment legs after Confirm Received.
     *
     * Backward compatibility:
     * Old shipments may already contain one full delivery_credit (for example
     * ₱50). Existing shipping-fee-funded credits are counted first, so this
     * method never adds a pickup credit that would push the shipment above the
     * current total rider earning cap.
     */
    public function creditDeliveredShipment(Shipment $shipment): ?RiderWalletTransaction
    {
        if (strtolower((string) $shipment->status) !== 'delivered') {
            return null;
        }

        if (!$this->orderIsConfirmedReceived($shipment)) {
            return null;
        }

        $sellerOrder = $this->sellerOrderForShipment($shipment);

        if (!$sellerOrder) {
            return null;
        }

        $shippingMinor = max(0, (int) ($sellerOrder->shipping_minor ?? 0));
        $totalTargetMinor = $this->calculateTotalRiderEarningMinor($shipment);

        if ($totalTargetMinor <= 0) {
            return null;
        }

        $deliveryTransaction = RiderWalletTransaction::query()
            ->where('shipment_id', $shipment->id)
            ->where('type', self::TYPE_DELIVERY_CREDIT)
            ->first();

        $pickupTransaction = RiderWalletTransaction::query()
            ->where('shipment_id', $shipment->id)
            ->where('type', self::TYPE_PICKUP_CREDIT)
            ->first();

        $alreadyFundedMinor = $this->shippingFundedCreditsMinor($shipment);

        // A legacy full delivery credit already consumed the rider pool.
        if ($alreadyFundedMinor >= $totalTargetMinor) {
            return $deliveryTransaction ?? $pickupTransaction;
        }

        if (!$pickupTransaction) {
            $pickupRider = $this->resolvePickupRiderUser($shipment);
            $remainingMinor = max(
                0,
                $totalTargetMinor - $this->shippingFundedCreditsMinor($shipment)
            );
            $pickupAmountMinor = min(
                $this->calculatePickupEarningMinor($shipment),
                $remainingMinor
            );

            if ($pickupRider && $pickupAmountMinor > 0) {
                $pickupTransaction = $this->creditRoleTransaction(
                    shipment: $shipment,
                    rider: $pickupRider,
                    sellerOrder: $sellerOrder,
                    shippingMinor: $shippingMinor,
                    amountMinor: $pickupAmountMinor,
                    type: self::TYPE_PICKUP_CREDIT,
                    referencePrefix: 'PICKUP',
                    role: 'pickup',
                    description: 'Pickup rider earning funded from shipping fee for shipment '
                        . ($shipment->tracking_number ?: ('#' . $shipment->id)) . '.'
                );
            }
        }

        if (!$deliveryTransaction) {
            $deliveryRider = $this->resolveRiderUser($shipment);
            $remainingMinor = max(
                0,
                $totalTargetMinor - $this->shippingFundedCreditsMinor($shipment)
            );
            $deliveryAmountMinor = min(
                $this->calculateDeliveryEarningMinor($shipment),
                $remainingMinor
            );

            if ($deliveryRider && $deliveryAmountMinor > 0) {
                $deliveryTransaction = $this->creditRoleTransaction(
                    shipment: $shipment,
                    rider: $deliveryRider,
                    sellerOrder: $sellerOrder,
                    shippingMinor: $shippingMinor,
                    amountMinor: $deliveryAmountMinor,
                    type: self::TYPE_DELIVERY_CREDIT,
                    referencePrefix: 'DELIVERY',
                    role: 'final_delivery',
                    description: 'Final delivery rider earning funded from shipping fee for shipment '
                        . ($shipment->tracking_number ?: ('#' . $shipment->id)) . '.'
                );
            }
        }

        return $deliveryTransaction ?? $pickupTransaction;
    }

    private function shippingFundedCreditsMinor(Shipment $shipment): int
    {
        return (int) RiderWalletTransaction::query()
            ->where('shipment_id', $shipment->id)
            ->whereIn('type', [
                self::TYPE_PICKUP_CREDIT,
                self::TYPE_DELIVERY_CREDIT,
                self::TYPE_DELIVERY_ADJUSTMENT,
            ])
            ->sum('amount_minor');
    }

    private function creditRoleTransaction(
        Shipment $shipment,
        User $rider,
        SellerOrder $sellerOrder,
        int $shippingMinor,
        int $amountMinor,
        string $type,
        string $referencePrefix,
        string $role,
        string $description
    ): RiderWalletTransaction {
        return DB::transaction(function () use (
            $shipment,
            $rider,
            $sellerOrder,
            $shippingMinor,
            $amountMinor,
            $type,
            $referencePrefix,
            $role,
            $description
        ) {
            $existing = RiderWalletTransaction::query()
                ->where('shipment_id', $shipment->id)
                ->where('type', $type)
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = $this->walletForRider((int) $rider->id);

            $wallet = RiderWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = RiderWalletTransaction::query()
                ->where('shipment_id', $shipment->id)
                ->where('type', $type)
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet->available_balance_minor =
                (int) $wallet->available_balance_minor + $amountMinor;
            $wallet->save();

            return RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'shipment_id' => $shipment->id,
                'type' => $type,
                'amount_minor' => $amountMinor,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => $referencePrefix . '-' . $shipment->id,
                'description' => $description,
                'meta' => [
                    'tracking_number' => $shipment->tracking_number,
                    'seller_order_id' => $sellerOrder->id,
                    'rider_user_id' => $rider->id,
                    'rider_role' => $role,
                    'shipping_fee_minor' => $shippingMinor,
                    'rider_earning_minor' => $amountMinor,
                    'funding_source' => 'shipping_fee',
                ],
            ]);
        });
    }

    public function requestWithdrawal(
        int $riderUserId,
        int $amountMinor,
        array $data
    ): RiderWithdrawal {
        return DB::transaction(function () use ($riderUserId, $amountMinor, $data) {
            if ($amountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Withdrawal amount must be greater than zero.',
                ]);
            }

            $minimum = (int) config('rider_wallet.minimum_withdrawal_minor', 10000);

            if ($amountMinor < $minimum) {
                throw ValidationException::withMessages([
                    'amount' => 'Minimum withdrawal is ₱'
                        . number_format($minimum / 100, 2)
                        . '.',
                ]);
            }

            $wallet = $this->walletForRider($riderUserId);

            $wallet = RiderWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (bool) config('rider_wallet.single_pending_withdrawal', true)
                && RiderWithdrawal::query()
                    ->where('rider_id', $riderUserId)
                    ->where('status', 'pending')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'withdrawal' =>
                        'You already have a pending withdrawal. Wait until it is paid or rejected.',
                ]);
            }

            if ((int) $wallet->available_balance_minor < $amountMinor) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient rider wallet balance.',
                ]);
            }

            $wallet->available_balance_minor -= $amountMinor;
            $wallet->pending_balance_minor += $amountMinor;
            $wallet->save();

            $withdrawal = RiderWithdrawal::create([
                'rider_id' => $riderUserId,
                'rider_wallet_id' => $wallet->id,
                'amount_minor' => $amountMinor,
                'payment_method' => $data['payment_method'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
                'bank_name' => $data['bank_name'] ?? null,
                'status' => 'pending',
                'rider_notes' => $data['rider_notes'] ?? null,
                'requested_at' => now(),
            ]);

            RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'type' => 'withdrawal_reserved',
                'amount_minor' => -$amountMinor,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => 'RIDER-WITHDRAWAL-' . $withdrawal->id,
                'description' => 'Rider withdrawal request #' . $withdrawal->id
                    . ' reserved from available balance.',
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                    'amount_minor' => $amountMinor,
                ],
            ]);

            return $withdrawal;
        });
    }

    public function markWithdrawalPaid(
        RiderWithdrawal $withdrawal,
        string $reference,
        ?string $adminNotes,
        ?int $adminUserId
    ): RiderWithdrawal {
        return DB::transaction(function () use ($withdrawal, $reference, $adminNotes, $adminUserId) {
            $withdrawal = RiderWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status === 'paid') {
                return $withdrawal;
            }

            if ($withdrawal->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Only pending withdrawals can be marked paid.',
                ]);
            }

            $wallet = RiderWallet::query()
                ->whereKey($withdrawal->rider_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->pending_balance_minor = max(
                0,
                (int) $wallet->pending_balance_minor - (int) $withdrawal->amount_minor
            );
            $wallet->save();

            $withdrawal->update([
                'status' => 'paid',
                'transaction_reference' => $reference,
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminUserId,
            ]);

            RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'type' => 'withdrawal_paid',
                'amount_minor' => 0,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => $reference,
                'description' => 'Rider withdrawal #' . $withdrawal->id . ' paid externally.',
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                    'amount_minor' => (int) $withdrawal->amount_minor,
                ],
            ]);

            return $withdrawal->fresh();
        });
    }

    public function rejectWithdrawal(
        RiderWithdrawal $withdrawal,
        ?string $adminNotes,
        ?int $adminUserId
    ): RiderWithdrawal {
        return DB::transaction(function () use ($withdrawal, $adminNotes, $adminUserId) {
            $withdrawal = RiderWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Only pending withdrawals can be rejected.',
                ]);
            }

            $wallet = RiderWallet::query()
                ->whereKey($withdrawal->rider_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $withdrawal->amount_minor;

            $wallet->pending_balance_minor = max(
                0,
                (int) $wallet->pending_balance_minor - $amount
            );
            $wallet->available_balance_minor += $amount;
            $wallet->save();

            $withdrawal->update([
                'status' => 'rejected',
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminUserId,
            ]);

            RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'type' => 'withdrawal_reversal',
                'amount_minor' => $amount,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => 'RIDER-WITHDRAWAL-' . $withdrawal->id,
                'description' => 'Rejected rider withdrawal #' . $withdrawal->id
                    . ' returned to available balance.',
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                    'amount_minor' => $amount,
                ],
            ]);

            return $withdrawal->fresh();
        });
    }
}
