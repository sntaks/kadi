<?php

namespace App\Sportsbook\Support;

/**
 * Money is stored as integer cents (sportsbook.currency.minor_units per shilling), never as floats.
 */
class Money
{
    public static function fromShillings(int $shillings): int
    {
        return $shillings * self::minorUnits();
    }

    /**
     * Cents from an amount as M-Pesa reports it: a number or numeric string in shillings, such as 100 or "100.00".
     */
    public static function fromAmount(int|float|string $shillings): int
    {
        return (int) round((float) $shillings * self::minorUnits());
    }

    /**
     * Whole shillings, for M-Pesa, which only moves whole shillings. Cents are dropped.
     */
    public static function toShillings(int $cents): int
    {
        return intdiv($cents, self::minorUnits());
    }

    /**
     * 125050 -> "KES 1,250.50".
     */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        return $sign.config('sportsbook.currency.code').' '.number_format(abs($cents) / self::minorUnits(), 2);
    }

    /**
     * 125050 -> "1250.50": a plain number for spreadsheets, with no currency or thousands separator.
     */
    public static function decimal(int $cents): string
    {
        return number_format($cents / self::minorUnits(), 2, '.', '');
    }

    private static function minorUnits(): int
    {
        return (int) config('sportsbook.currency.minor_units');
    }
}
