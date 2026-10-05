<?php

namespace App\Enums;

/**
 * Estados del pedido.
 *
 * Flujo operativo: PENDIENTE → ENTREGADO → POR COBRAR → TERMINADO (pago registrado).
 * - PENDIENTE: recién creado o con una adición sin imprimir.
 * - ENTREGADO: comandas impresas (la impresión operativa hace la transición).
 * - POR COBRAR: cuenta de mesa impresa o domicilio que "🛵 Salió".
 * - TERMINADO: pago registrado.
 */
enum OrderStatus: string
{
    case PENDING = 'PENDIENTE';
    case DELIVERED = 'ENTREGADO';
    case TO_COLLECT = 'POR COBRAR';
    case COMPLETED = 'TERMINADO';

    // Estados heredados: se conservan solo para leer datos anteriores y nunca se asignan.
    // EN PREPARACIÓN equivale a ENTREGADO y EN CAMINO a POR COBRAR (ver operational()).
    case PREPARING = 'EN PREPARACIÓN';
    case IN_TRANSIT = 'EN CAMINO';

    // Conserva registros históricos anteriores sin ofrecer este estado en la operación actual.
    case LEGACY_CANCELLED = 'CANCELADO';

    public static function operationalCases(): array
    {
        return [self::PENDING, self::DELIVERED, self::TO_COLLECT, self::COMPLETED];
    }

    /**
     * Estado operativo equivalente: traduce los estados heredados al flujo actual.
     */
    public function operational(): self
    {
        return match ($this) {
            self::PREPARING => self::DELIVERED,
            self::IN_TRANSIT => self::TO_COLLECT,
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
     * ¿Ya se imprimió y solo falta registrar el pago? (ENTREGADO o POR COBRAR).
     */
    public function isCollectable(): bool
    {
        return in_array($this->operational(), [self::DELIVERED, self::TO_COLLECT], true);
    }
}
