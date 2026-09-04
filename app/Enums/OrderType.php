<?php

namespace App\Enums;

enum OrderType: string
{
    case TABLE = 'MESA';
    case TAKEAWAY = 'PARA_LLEVAR';
}
