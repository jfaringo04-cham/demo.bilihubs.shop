<?php

namespace App\Services;

use App\Models\AdminWallet;
use App\Models\AdminWalletTransaction;
use App\Models\CodRemittance;
use App\Models\LogisticAdminRemittance;
use App\Models\LogisticCodWallet;
use App\Models\LogisticCodWalletTransaction;
use App\Models\SellerSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CodCustodyService
{
    public const LOGISTIC_CREDIT = 'rider_cod_confirmed';
    public const ADMIN_TRANSFER_SUBMITTED = 'admin_transfer_submitted';
    public const ADMIN_TRANSFER_CONFIRMED = 'admin_transfer_confirmed';
    public const ADMIN_TRANSFER_REJECTED = 'admin_transfer_rejected';
    public const ADMIN_CREDIT = 'logistics_cod_received';
    public const SELLER_SETTLEMENT_DEBIT = 'seller_settlement_debit';

    public function walletForLogistic(int $logisticId): LogisticCodWallet
    {
        return LogisticCodWallet::firstOrCreate(
            ['logistic_id' => $logisticId],
            [
                'available_balance_minor' => 0,
                'pending_balance_minor' => 0,
            ]
        );
    }

    public function adminWallet(): AdminWallet
    {
        return AdminWallet::firstOrCreate(
            ['id' => 1],
            ['available_balance_minor' => 0]
        );
    }

    public function creditConfirmedRiderRemittance(
        CodRemittance $remittance
    ): ?LogisticCodWalletTransaction {
        if (
            $remittance->status !== 'confirmed'
            || (int) $remittance->total_minor <= 0
        ) {
            return null;
        }

        return DB::transaction(function () use ($remittance) {
            $existing = LogisticCodWalletTransaction::query()
                ->where('cod_remittance_id', $remittance->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = $this->walletForLogistic(
                (int) $remittance->logistic_id
            );

            $wallet = LogisticCodWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = LogisticCodWalletTransaction::query()
                ->where('cod_remittance_id', $remittance->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $amount = (int) $remittance->total_minor;

            $wallet->available_balance_minor =
                (int) $wallet->available_balance_minor + $amount;

            $wallet->save();

            return LogisticCodWalletTransaction::create([
                'logistic_cod_wallet_id' => $wallet->id,
                'cod_remittance_id' => $remittance->id,
                'type' => self::LOGISTIC_CREDIT,
                'amount_minor' => $amount,
                'balance_after_minor' =>
                (int) $wallet->available_balance_minor
                    + (int) $wallet->pending_balance_minor,
                'reference' => $remittance->reference,
                'description' =>
                'COD cash confirmed received from rider.',
                'meta' => [
                    'rider_user_id' => $remittance->rider_user_id,
                    'logistic_id' => $remittance->logistic_id,
                ],
            ]);
        }, 3);
    }

    public function submitToAdmin(
        int $logisticId,
        int $submittedBy,
        int $amountMinor,
        array $data
    ): LogisticAdminRemittance {
        return DB::transaction(function () use (
            $logisticId,
            $submittedBy,
            $amountMinor,
            $data
        ) {
            if ($amountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount must be greater than zero.',
                ]);
            }

            $wallet = $this->walletForLogistic($logisticId);

            $wallet = LogisticCodWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                LogisticAdminRemittance::query()
                ->where('logistic_id', $logisticId)
                ->where('status', 'pending')
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'remittance' =>
                    'You already have a pending COD transfer to Admin.',
                ]);
            }

            if (
                (int) $wallet->available_balance_minor
                < $amountMinor
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                    'Insufficient available COD custody balance.',
                ]);
            }

            $wallet->available_balance_minor -= $amountMinor;
            $wallet->pending_balance_minor += $amountMinor;
            $wallet->save();

            $remittance = LogisticAdminRemittance::create([
                'reference' =>
                'ADM-COD-' . strtoupper((string) Str::ulid()),
                'logistic_id' => $logisticId,
                'amount_minor' => $amountMinor,
                'status' => 'pending',
                'transfer_method' =>
                $data['transfer_method'] ?? null,
                'transfer_reference' =>
                $data['transfer_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'submitted_by' => $submittedBy,
                'submitted_at' => now(),
            ]);

            LogisticCodWalletTransaction::create([
                'logistic_cod_wallet_id' => $wallet->id,
                'logistic_admin_remittance_id' => $remittance->id,
                'type' => self::ADMIN_TRANSFER_SUBMITTED,
                'amount_minor' => 0,
                'balance_after_minor' =>
                (int) $wallet->available_balance_minor
                    + (int) $wallet->pending_balance_minor,
                'reference' => $remittance->reference,
                'description' =>
                'COD cash reserved for transfer to Admin.',
                'meta' => [
                    'amount_minor' => $amountMinor,
                    'transfer_method' =>
                    $remittance->transfer_method,
                    'transfer_reference' =>
                    $remittance->transfer_reference,
                ],
            ]);

            return $remittance;
        }, 3);
    }

    public function confirmAdminTransfer(
        LogisticAdminRemittance $remittance,
        int $adminUserId
    ): AdminWalletTransaction {
        return DB::transaction(function () use (
            $remittance,
            $adminUserId
        ) {
            $locked = LogisticAdminRemittance::query()
                ->whereKey($remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'remittance' =>
                    'Only pending transfers can be confirmed.',
                ]);
            }

            $existingAdminCredit =
                AdminWalletTransaction::query()
                ->where(
                    'logistic_admin_remittance_id',
                    $locked->id
                )
                ->first();

            if ($existingAdminCredit) {
                return $existingAdminCredit;
            }

            $logisticWallet = $this->walletForLogistic(
                (int) $locked->logistic_id
            );

            $logisticWallet = LogisticCodWallet::query()
                ->whereKey($logisticWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $locked->amount_minor;

            if (
                (int) $logisticWallet->pending_balance_minor
                < $amount
            ) {
                throw ValidationException::withMessages([
                    'remittance' =>
                    'Logistics pending COD balance is inconsistent.',
                ]);
            }

            $logisticWallet->pending_balance_minor -= $amount;
            $logisticWallet->save();

            $adminWallet = $this->adminWallet();

            $adminWallet = AdminWallet::query()
                ->whereKey($adminWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $adminWallet->available_balance_minor += $amount;
            $adminWallet->save();

            $locked->update([
                'status' => 'confirmed',
                'confirmed_by' => $adminUserId,
                'confirmed_at' => now(),
            ]);

            LogisticCodWalletTransaction::create([
                'logistic_cod_wallet_id' => $logisticWallet->id,
                'logistic_admin_remittance_id' => $locked->id,
                'type' => self::ADMIN_TRANSFER_CONFIRMED,
                'amount_minor' => -$amount,
                'balance_after_minor' =>
                (int) $logisticWallet->available_balance_minor
                    + (int) $logisticWallet->pending_balance_minor,
                'reference' => $locked->reference,
                'description' =>
                'COD cash transfer confirmed by Admin.',
                'meta' => [
                    'confirmed_by' => $adminUserId,
                ],
            ]);

            return AdminWalletTransaction::create([
                'admin_wallet_id' => $adminWallet->id,
                'logistic_admin_remittance_id' => $locked->id,
                'seller_settlement_id' => null,
                'type' => self::ADMIN_CREDIT,
                'amount_minor' => $amount,
                'balance_after_minor' =>
                (int) $adminWallet->available_balance_minor,
                'reference' => $locked->reference,
                'description' =>
                'COD cash received from logistics.',
                'meta' => [
                    'logistic_id' => $locked->logistic_id,
                    'transfer_method' =>
                    $locked->transfer_method,
                    'transfer_reference' =>
                    $locked->transfer_reference,
                ],
            ]);
        }, 3);
    }

    public function rejectAdminTransfer(
        LogisticAdminRemittance $remittance,
        int $adminUserId,
        string $reason
    ): LogisticAdminRemittance {
        return DB::transaction(function () use (
            $remittance,
            $adminUserId,
            $reason
        ) {
            $locked = LogisticAdminRemittance::query()
                ->whereKey($remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'remittance' =>
                    'Only pending transfers can be rejected.',
                ]);
            }

            $wallet = $this->walletForLogistic(
                (int) $locked->logistic_id
            );

            $wallet = LogisticCodWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $locked->amount_minor;

            if (
                (int) $wallet->pending_balance_minor
                < $amount
            ) {
                throw ValidationException::withMessages([
                    'remittance' =>
                    'Logistics pending COD balance is inconsistent.',
                ]);
            }

            $wallet->pending_balance_minor -= $amount;
            $wallet->available_balance_minor += $amount;
            $wallet->save();

            $locked->update([
                'status' => 'rejected',
                'rejected_by' => $adminUserId,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            LogisticCodWalletTransaction::create([
                'logistic_cod_wallet_id' => $wallet->id,
                'logistic_admin_remittance_id' => $locked->id,
                'type' => self::ADMIN_TRANSFER_REJECTED,
                'amount_minor' => 0,
                'balance_after_minor' =>
                (int) $wallet->available_balance_minor
                    + (int) $wallet->pending_balance_minor,
                'reference' => $locked->reference,
                'description' =>
                'Admin rejected COD transfer; funds returned to available custody.',
                'meta' => [
                    'rejected_by' => $adminUserId,
                    'reason' => $reason,
                ],
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function debitSellerSettlement(
        SellerSettlement $settlement
    ): AdminWalletTransaction {
        return DB::transaction(function () use ($settlement) {
            $lockedSettlement = SellerSettlement::query()
                ->whereKey($settlement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !in_array(
                    $lockedSettlement->status,
                    ['approved', 'wallet_credited'],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'settlement' =>
                    'Only an approved seller settlement can use Admin COD funds.',
                ]);
            }

            $amountMinor = (int) $lockedSettlement->net_minor;

            if ($amountMinor <= 0) {
                throw ValidationException::withMessages([
                    'settlement' =>
                    'Seller settlement net amount must be greater than zero.',
                ]);
            }

            $existing = AdminWalletTransaction::query()
                ->where(
                    'seller_settlement_id',
                    $lockedSettlement->id
                )
                ->where(
                    'type',
                    self::SELLER_SETTLEMENT_DEBIT
                )
                ->first();

            if ($existing) {
                return $existing;
            }

            $adminWallet = $this->adminWallet();

            $adminWallet = AdminWallet::query()
                ->whereKey($adminWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $adminWallet->available_balance_minor
                < $amountMinor
            ) {
                throw ValidationException::withMessages([
                    'settlement' =>
                    'Insufficient Admin COD wallet balance for this seller settlement.',
                ]);
            }

            $newBalance =
                (int) $adminWallet->available_balance_minor
                - $amountMinor;

            $adminWallet->update([
                'available_balance_minor' => $newBalance,
            ]);

            return AdminWalletTransaction::create([
                'admin_wallet_id' => $adminWallet->id,
                'logistic_admin_remittance_id' => null,
                'seller_settlement_id' =>
                $lockedSettlement->id,
                'type' => self::SELLER_SETTLEMENT_DEBIT,
                'amount_minor' => -$amountMinor,
                'balance_after_minor' => $newBalance,
                'reference' =>
                'SELLER-SETTLEMENT-'
                    . $lockedSettlement->id,
                'description' =>
                'Seller settlement funded from Admin COD wallet.',
                'meta' => [
                    'seller_id' =>
                    (int) $lockedSettlement->seller_id,
                    'seller_order_id' =>
                    (int) $lockedSettlement->seller_order_id,
                    'subtotal_minor' =>
                    (int) $lockedSettlement->subtotal_minor,
                    'commission_minor' =>
                    (int) $lockedSettlement->commission_minor,
                    'net_minor' =>
                    (int) $lockedSettlement->net_minor,
                ],
            ]);
        }, 3);
    }
}
