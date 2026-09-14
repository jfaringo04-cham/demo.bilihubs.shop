<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ComplianceMonitor;
use Illuminate\Console\Command;

class ScanProductImage extends Command
{
    protected $signature = 'compliance:scan-product {productId}';
    protected $description = 'Run the image content scanner on a specific product and print results';

    public function handle(): int
    {
        $product = Product::with('images')->find($this->argument('productId'));
        if (!$product) {
            $this->error('Product not found.');
            return self::FAILURE;
        }

        $this->info("Scanning product #{$product->id}: {$product->name}");
        $this->line("Images: " . $product->images->count());
        $this->line('');

        $result = ComplianceMonitor::checkImageContent($product);

        if ($result === null) {
            $this->info('✓ No violations detected.');
            return self::SUCCESS;
        }

        $this->warn("⚠ VIOLATIONS DETECTED (severity: {$result['severity']})");
        $this->line($result['message']);
        if (!empty($result['reasons'])) {
            $this->line('');
            $this->line('Findings:');
            foreach ($result['reasons'] as $reason) {
                $this->line('  • ' . $reason);
            }
        }
        return self::SUCCESS;
    }
}
