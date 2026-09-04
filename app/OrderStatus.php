<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDIENTE';
    case PREPARING = 'PREPARANDO';
    case READY = 'LISTO';
    case DELIVERED = 'ENTREGADO';
    case CANCELLED = 'CANCELADO';
}