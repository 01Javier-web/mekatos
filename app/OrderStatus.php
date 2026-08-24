<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case PREPARING = 'PREPARING';
    case READY = 'READY';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';
}