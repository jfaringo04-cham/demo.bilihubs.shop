<?php

namespace App\Console\Commands;

use App\Services\ComplianceMonitor;
use Illuminate\Console\Command;

class ScanComplianceTriggers extends Command
{
    protected $signature = 'compliance:scan {--product=}';
    protected $description = 'Scan products for compliance triggers and notify admins';

    public function handle(): int
    {
        if ($productId = $this->option('product')) {
            $product = \App\Models\Product::find($productId);
            if (!$product) {
                $this->error("Product #{$productId} not found.");
                return self::FAILURE;
            }
            $triggers = ComplianceMonitor::checkProduct($product);
            $this->info("Scanned product #{$product->id} ({$product->name}): " . count($triggers) . " trigger(s) found.");
            foreach ($triggers as $t) {
                $this->line("  - [{$t['severity']}] {$t['trigger']}: {$t['message']}");
            }
            return self::SUCCESS;
        }

        $this->info('Scanning all approved products for compliance triggers...');
        $count = ComplianceMonitor::scanAllProducts();
        $this->info("Scan complete. {$count} product(s) triggered compliance alerts.");
        return self::SUCCESS;
    }
}
