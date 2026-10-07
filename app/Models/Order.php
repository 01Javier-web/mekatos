<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\OrderStatus;
use App\Enums\OrderType;

class Order extends Model
{
    protected $fillable = [
        'table_session_id',
        'type',
        'status',
        'subtotal',
        'packaging_fee',
        'delivery_fee',
        'tax',
        'total',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'delivery_reference',
        'notes',
        'handled_by_user_id',
        'delivered_by_user_id',
        'delivered_at',
        'dispatched_by_user_id',
        'dispatched_at',
        'paid_at',
        'paid_by_user_id',
    ];

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /** Líneas activas del pedido (las anuladas por una edición no cuentan). */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->whereNull('voided_at');
    }

    /** Todas las líneas, incluidas las anuladas (comandas de cambio, reimpresión, historial). */
    public function allOrderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Líneas anuladas por una edición. */
    public function voidedItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->whereNotNull('voided_at');
    }

    /** ¿Ya se envió alguna comanda a cocina? (incluye líneas anuladas después). */
    public function hasSentKitchenTicket(): bool
    {
        return $this->allOrderItems()->whereNotNull('sent_at')->exists();
    }

    /**
     * ¿Hay algo pendiente de imprimir para cocina? Líneas activas sin enviar o líneas ya
     * enviadas que se anularon y cuyo "❌ NO PREPARAR" todavía no se imprimió.
     */
    public function hasPendingKitchenChanges(): bool
    {
        return $this->orderItems()->whereNull('sent_at')->exists()
            || $this->voidedItems()->whereNotNull('sent_at')->whereNull('void_sent_at')->exists()
            // Salsas generales nuevas o anuladas después de enviarse.
            || $this->generalSauces()->whereNull('sent_at')->exists()
            || $this->allGeneralSauces()->whereNotNull('voided_at')->whereNull('void_sent_at')->exists();
    }

    /**
     * Texto del botón de impresión: primera comanda, cambio (algo anulado ya enviado, o solo
     * salsas generales nuevas en un pedido ya enviado) o adición tradicional (productos nuevos).
     */
    public function kitchenPrintLabel(): string
    {
        if (! $this->hasSentKitchenTicket()) {
            return 'Imprimir comandas';
        }

        $hasVoids = $this->voidedItems()->whereNotNull('sent_at')->whereNull('void_sent_at')->exists()
            || $this->allGeneralSauces()->whereNotNull('voided_at')->whereNull('void_sent_at')->exists();
        $hasNewItems = $this->orderItems()->whereNull('sent_at')->exists();

        return $hasVoids || ! $hasNewItems ? 'Imprimir cambio' : 'Imprimir adición';
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(OrderRound::class)->orderBy('number');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** Salsas generales del pedido (no asociadas a un producto; siempre van aparte). */
    public function generalSauces(): HasMany
    {
        return $this->hasMany(OrderSauce::class)->whereNull('order_item_id')->whereNull('voided_at')->orderBy('id');
    }

    /** Todas las salsas generales, incluidas las anuladas (comandas, reimpresión, historial). */
    public function allGeneralSauces(): HasMany
    {
        return $this->hasMany(OrderSauce::class)->whereNull('order_item_id')->orderBy('id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    /** Quien marcó "🛵 Salió" en un domicilio (marca, no estado). */
    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'delivered_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'packaging_fee' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
