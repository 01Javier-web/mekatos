<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDIENTE';
    case PREPARING = 'EN PREPARACIÓN';
    case DELIVERED = 'ENTREGADO';
    case COMPLETED = 'TERMINADO';
}
