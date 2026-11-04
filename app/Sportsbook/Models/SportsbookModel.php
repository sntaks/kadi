<?php

namespace App\Sportsbook\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Every sportsbook table keeps its datetimes in UTC (plan §5.2): kick-offs come from the feed in UTC, and this app
 * runs in Africa/Nairobi. Writes are converted to UTC, and stored values are read back as UTC, so the ported code
 * behaves exactly as it did in the sportsbook (whose app timezone was UTC). Pages show them in EAT with
 * ->timezone(config('sportsbook.timezone')).
 *
 * Query bindings are not converted: pass UTC times to where() (now('UTC'), ->utc()).
 */
abstract class SportsbookModel extends Model
{
    public function fromDateTime($value): ?string
    {
        return empty($value) ? $value : $this->asDateTime($value)->utc()->format($this->getDateFormat());
    }

    /**
     * @param  mixed  $value
     * @return CarbonInterface (CarbonImmutable here: the app uses immutable dates)
     */
    protected function asDateTime($value): CarbonInterface
    {
        // A plain string from the database (or written as a literal) is a UTC wall-clock time. A string with its own
        // offset ("Z", "+03:00"), a timestamp or a date object is an exact instant already.
        if (is_string($value) && ! preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', trim($value))) {
            return Date::parse(parent::asDateTime($value)->format('Y-m-d H:i:s.u'), 'UTC');
        }

        return parent::asDateTime($value)->utc();
    }
}
