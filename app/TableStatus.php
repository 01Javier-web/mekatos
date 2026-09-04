<?php

namespace App;

enum TableStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case OCCUPIED = 'OCCUPIED';
    case CLEANING = 'CLEANING';
}