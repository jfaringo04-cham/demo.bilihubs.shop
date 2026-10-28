<?php

namespace App\Console\Commands;

use App\Models\RiderWalletTransaction;
use App\Models\Shipment;
use App\Services\RiderWalletService;
use Illuminate\Console\Command;

class BackfillRiderWallets extends Command
{
    protected $signature = 'rider-wallet:backfill-delivered {--chunk=100}';
    protected $description = 'Credit missing rider earnings for confirmed delivered shipments.';

    public function handle(RiderWalletService $service): int
    {
        $checked = 0;
        $creditedTransactions = 0;

        Shipment::where('status', 'delivered')
            ->orderBy('id')
            ->chunkById((int) $this->option('chunk'), function ($rows) use (
                $service,
                &$checked,
                &$creditedTransactions
            ) {
                foreach ($rows as $shipment) {
                    $checked++;

                    $before = RiderWalletTransaction::query()
                        ->where('shipment_id', $shipment->id)
                        ->whereIn('type', $service->earningTransactionTypes())
                        ->count();

                    $service->creditDeliveredShipment($shipment);

                    $after = RiderWalletTransaction::query()
                        ->where('shipment_id', $shipment->id)
                        ->whereIn('type', $service->earningTransactionTypes())
                        ->count();

                    $creditedTransactions += max(0, $after - $before);
                }
            });

        $this->info(
            "Checked {$checked} delivered shipments; credited "
            . "{$creditedTransactions} new rider earning transactions."
        );

        return self::SUCCESS;
    }
}
