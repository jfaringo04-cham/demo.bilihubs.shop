<?php

namespace App\Services;

use App\Models\Logistic;
use App\Models\LogisticWallet;
use App\Models\LogisticWalletTransaction;
use App\Models\LogisticWithdrawal;
use App\Models\RiderWalletTransaction;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticWalletService
{
    public const TYPE_SHIPMENT_CREDIT = 'shipment_credit';

    public function walletForLogistic(int $logisticId): LogisticWallet
    {
        return LogisticWallet::firstOrCreate(
            ['logistic_id' => $logisticId],
            [
                'available_balance_minor' => 0,
                'pending_balance_minor' => 0,
            ]
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

    public function requestWithdrawal(
        int $logisticId,
        int $amountMinor,
        array $data
    ): LogisticWithdrawal {
        return DB::transaction(function () use ($logisticId, $amountMinor, $data) {
            if ($amountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Withdrawal amount must be greater than zero.',
                ]);
            }

            $minimum = (int) config('logistic_wallet.minimum_withdrawal_minor', 10000);

            if ($amountMinor < $minimum) {
                throw ValidationException::withMessages([
                    'amount' => 'Minimum withdrawal is ₱'
                        . number_format($minimum / 100, 2)
                        . '.',
                ]);
            }

            $wallet = $this->walletForLogistic($logisticId);
            $wallet = LogisticWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (bool) config('logistic_wallet.single_pending_withdrawal', true)
                && LogisticWithdrawal::query()
                    ->where('logistic_id', $logisticId)
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
                    'amount' => 'Insufficient available logistics wallet balance.',
                ]);
            }

            $newAvailable = (int) $wallet->available_balance_minor - $amountMinor;
            $newPending = (int) $wallet->pending_balance_minor + $amountMinor;

            $wallet->update([
                'available_balance_minor' => $newAvailable,
                'pending_balance_minor' => $newPending,
            ]);

            $withdrawal = LogisticWithdrawal::create([
                'logistic_wallet_id' => $wallet->id,
                'logistic_id' => $logisticId,
                'amount_minor' => $amountMinor,
                'payment_method' => $data['payment_method'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
                'bank_name' => $data['bank_name'] ?? null,
                'status' => 'pending',
                'logistic_notes' => $data['logistic_notes'] ?? null,
                'requested_at' => now(),
            ]);

            LogisticWalletTransaction::create([
                'logistic_wallet_id' => $wallet->id,
                'shipment_id' => null,
                'type' => 'withdrawal_reserved',
                'amount_minor' => -$amountMinor,
                'balance_after_minor' => $newAvailable,
                'reference' => 'LOGISTIC-WITHDRAWAL-' . $withdrawal->id,
                'description' => 'Logistics withdrawal request #' . $withdrawal->id
                    . ' reserved from available balance.',
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                    'amount_minor' => $amountMinor,
                ],
            ]);

            return $withdrawal;
        }, 3);
    }

    public function markWithdrawalPaid(
        LogisticWithdrawal $withdrawal,
        string $reference,
        ?string $adminNotes,
        ?int $adminUserId
    ): LogisticWithdrawal {
        return DB::transaction(function () use ($withdrawal, $reference, $adminNotes, $adminUserId) {
            $locked = LogisticWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'paid') {
                return $locked;
            }

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Only pending withdrawals can be marked paid.',
                ]);
            }

            $wallet = LogisticWallet::query()
                ->whereKey($locked->logistic_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $wallet->pending_balance_minor < (int) $locked->amount_minor) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Logistics wallet pending balance is inconsistent.',
                ]);
            }

            $wallet->pending_balance_minor =
                (int) $wallet->pending_balance_minor - (int) $locked->amount_minor;
            $wallet->save();

            $locked->update([
                'status' => 'paid',
                'transaction_reference' => $reference,
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminUserId,
            ]);

            LogisticWalletTransaction::create([
                'logistic_wallet_id' => $wallet->id,
                'shipment_id' => null,
                'type' => 'withdrawal_paid',
                'amount_minor' => 0,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => $reference,
                'description' => 'Logistics withdrawal #' . $locked->id . ' paid externally.',
                'meta' => [
                    'withdrawal_id' => $locked->id,
                    'amount_minor' => (int) $locked->amount_minor,
                    'payment_method' => $locked->payment_method,
                ],
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function rejectWithdrawal(
        LogisticWithdrawal $withdrawal,
        ?string $adminNotes,
        ?int $adminUserId
    ): LogisticWithdrawal {
        return DB::transaction(function () use ($withdrawal, $adminNotes, $adminUserId) {
            $locked = LogisticWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Only pending withdrawals can be rejected.',
                ]);
            }

            $wallet = LogisticWallet::query()
                ->whereKey($locked->logistic_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $wallet->pending_balance_minor < (int) $locked->amount_minor) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Logistics wallet pending balance is inconsistent.',
                ]);
            }

            $amount = (int) $locked->amount_minor;
            $newAvailable = (int) $wallet->available_balance_minor + $amount;

            $wallet->update([
                'available_balance_minor' => $newAvailable,
                'pending_balance_minor' => (int) $wallet->pending_balance_minor - $amount,
            ]);

            $locked->update([
                'status' => 'rejected',
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminUserId,
            ]);

            LogisticWalletTransaction::create([
                'logistic_wallet_id' => $wallet->id,
                'shipment_id' => null,
                'type' => 'withdrawal_reversal',
                'amount_minor' => $amount,
                'balance_after_minor' => $newAvailable,
                'reference' => 'LOGISTIC-WITHDRAWAL-REJECTED-' . $locked->id,
                'description' => 'Rejected logistics withdrawal #' . $locked->id
                    . ' returned to available balance.',
                'meta' => [
                    'withdrawal_id' => $locked->id,
                    'amount_minor' => $amount,
                ],
            ]);

            return $locked->fresh();
        }, 3);
    }
}
