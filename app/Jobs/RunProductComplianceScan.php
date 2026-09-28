<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ComplianceMonitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunProductComplianceScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $productId
    ) {
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);

        if (!$product) {
            return;
        }

        ComplianceMonitor::checkProduct($product->fresh(), true);
    }
}