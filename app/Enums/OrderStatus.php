<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDIENTE';
    case PREPARING = 'EN PREPARACIÓN';
    case DELIVERED = 'ENTREGADO';
    case COMPLETED = 'TERMINADO';

    // Conserva registros históricos anteriores sin ofrecer este estado en la operación actual.
    case LEGACY_CANCELLED = 'CANCELADO';

    public static function operationalCases(): array
    {
        return [self::PENDING, self::PREPARING, self::DELIVERED, self::COMPLETED];
    }
}
