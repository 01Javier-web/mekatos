<?php

namespace App\Enums;

/**
 * Estados del pedido.
 *
 * Flujo operativo: PENDIENTE → POR COBRAR → TERMINADO (pago registrado).
 * - PENDIENTE: recién creado o con una adición sin imprimir.
 * - POR COBRAR: comandas impresas; falta registrar el pago.
 * - TERMINADO: pago registrado.
 * - CANCELADO: excepción. El pedido se conserva con su historial, pero no cuenta
 *   como venta, no se cobra y no está activo.
 *
 * "🛵 Salió" (domicilios) no es un estado: es la marca dispatched_at del pedido.
 */
enum OrderStatus: string
{
    case PENDING = 'PENDIENTE';
    case TO_COLLECT = 'POR COBRAR';
    case COMPLETED = 'TERMINADO';
    case CANCELLED = 'CANCELADO';

    // Estados heredados: se conservan solo para leer datos anteriores y nunca se asignan.
    // Equivalen a POR COBRAR (ver operational()).
    case DELIVERED = 'ENTREGADO';
    case PREPARING = 'EN PREPARACIÓN';
    case IN_TRANSIT = 'EN CAMINO';

    public static function operationalCases(): array
    {
        return [self::PENDING, self::TO_COLLECT, self::COMPLETED, self::CANCELLED];
    }

    /**
     * Estado operativo equivalente: traduce los estados heredados al flujo actual.
     */
    public function operational(): self
    {
        return match ($this) {
            self::DELIVERED, self::PREPARING, self::IN_TRANSIT => self::TO_COLLECT,
            default => $this,
        };
    }

    /**
     * Valores guardados que corresponden a este estado operativo (incluye los heredados).
     *
     * @return array<int, string>
     */
    public function storedValues(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->operational() === $this)
        ));
    }

    /**
     * Valores guardados de los pedidos activos (PENDIENTE o POR COBRAR, con sus heredados).
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [...self::PENDING->storedValues(), ...self::TO_COLLECT->storedValues()];
    }

    /**
     * ¿Ya se imprimió y solo falta registrar el pago? (POR COBRAR).
     */
    public function isCollectable(): bool
    {
        return $this->operational() === self::TO_COLLECT;
    }

    /**
     * ¿Sigue en operación? (PENDIENTE o POR COBRAR). Solo estos pedidos se pueden cancelar.
     */
    public function isActive(): bool
    {
        return in_array($this->operational(), [self::PENDING, self::TO_COLLECT], true);
    }
}
