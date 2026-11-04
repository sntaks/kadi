<?php

namespace App\Sportsbook\Enums;

enum FixtureStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
}
