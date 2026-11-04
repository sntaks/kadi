<?php

namespace App\Sportsbook\Enums;

enum MarketStatus: string
{
    case Open = 'open';
    case Suspended = 'suspended';
}
