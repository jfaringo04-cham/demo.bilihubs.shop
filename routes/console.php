<?php

use App\Models\Product;
use App\Models\User;
use App\Services\ComplianceMonitor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    ComplianceMonitor::scanAllProducts();
})->hourly()->name('compliance-scan')->withoutOverlapping();

Schedule::call(function () {
    Product::where('compliance_status', 'flagged')
        ->whereNotNull('flagged_at')
        ->where('flagged_at', '<', now()->subDays(Product::RESUBMIT_DEADLINE_DAYS))
        ->with('seller')
        ->chunk(100, function ($products) {
            foreach ($products as $product) {
                $product->update([
                    'compliance_status' => 'rejected',
                    'admin_notes' => ($product->admin_notes ? $product->admin_notes . "\n\n" : '') .
                        '[AUTO] Resubmission deadline of ' . Product::RESUBMIT_DEADLINE_DAYS . ' days passed. Product auto-rejected on ' . now()->format('M d, Y g:i A') . '.',
                ]);

                if ($product->seller) {
                    \App\Models\Notification::create([
                        'user_id' => $product->seller->id,
                        'title' => 'Product Auto-Rejected - Deadline Passed',
                        'message' => "Your product \"{$product->name}\" was automatically rejected because the " . Product::RESUBMIT_DEADLINE_DAYS . "-day resubmission deadline passed. Please contact admin support if you want to restore it.",
                        'type' => 'product',
                        'link' => route('seller.products'),
                    ]);
                }
            }
        });
})->daily()->name('flagged-product-cleanup')->withoutOverlapping();

// Reset daily quota for riders at midnight
Schedule::call(function () {
    User::where('role', 'rider')
        ->where('last_quota_reset_date', '<', now()->toDateString())
        ->update([
            'daily_pickups_completed' => 0,
            'daily_deliveries_completed' => 0,
            'last_quota_reset_date' => now()->toDateString(),
        ]);
})->dailyAt('00:00')->name('daily-quota-reset')->withoutOverlapping();
