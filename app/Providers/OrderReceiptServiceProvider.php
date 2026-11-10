<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderReceiptObserver;
use App\Observers\OrderVoucherRedemptionObserver;
use Illuminate\Support\ServiceProvider;

class OrderReceiptServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Order::observe(OrderReceiptObserver::class);
        Order::observe(OrderVoucherRedemptionObserver::class);
    }
}
