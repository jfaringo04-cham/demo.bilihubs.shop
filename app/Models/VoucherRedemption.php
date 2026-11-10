<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherRedemption extends Model
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_REVERSED = 'reversed';

    public const ACTIVE_STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_REDEEMED,
    ];

    protected $fillable = [
        'voucher_id',
        'user_id',
        'order_id',
        'seller_order_id',
        'voucher_code_snapshot',
        'funded_by',
        'discount_minor',
        'status',
        'redeemed_at',
        'reversed_at',
    ];

    protected $casts = [
        'discount_minor' => 'integer',
        'redeemed_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }
}
