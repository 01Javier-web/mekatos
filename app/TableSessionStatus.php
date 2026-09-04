<?php

namespace App;

enum TableSessionStatus: string
{
    case Active = 'ACTIVO';
    case Cancelled = 'CANCELADO';
    case CLOSED = 'CERRADO';
}
