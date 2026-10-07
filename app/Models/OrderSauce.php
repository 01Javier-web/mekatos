<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salsa elegida en un pedido. Con order_item_id es de un producto (EN_PRODUCTO o
 * APARTE); sin order_item_id es una salsa general del pedido (siempre APARTE).
 * Las salsas son gratuitas y no tienen cantidad.
 */
class OrderSauce extends Model
{
    public const IN_PRODUCT = 'EN_PRODUCTO';

    public const ON_SIDE = 'APARTE';

    public const PLACEMENTS = [
        self::IN_PRODUCT => 'En el producto',
        self::ON_SIDE => 'Aparte',
    ];

    protected $fillable = [
        'order_id',
        'order_item_id',
        'order_round_id',
        'sauce_id',
        'placement',
        'sent_at',
        'voided_at',
        'void_sent_at',
    ];

    protected $casts = [
        'voided_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function sauce(): BelongsTo
    {
        return $this->belongsTo(Sauce::class);
    }
}
