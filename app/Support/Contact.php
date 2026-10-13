<?php

namespace App\Support;

/**
 * Customer-care details and social profiles (config('kadi.support'), config('kadi.socials')).
 * Use this rather than hard-coding a number or address, so a change is one config edit.
 */
final class Contact
{
    /** The number as players read and dial it in Kenya, e.g. "0182 473 265". */
    public static function phone(): ?string
    {
        $digits = self::digits();

        if ($digits === null) {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7);
        }

        return (string) config('kadi.support.phone');
    }

    /** International form for tel: links and structured data, e.g. "+254182473265". */
    public static function phoneInternational(): ?string
    {
        $digits = self::digits();

        if ($digits === null) {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    public static function phoneHref(): ?string
    {
        return ($number = self::phoneInternational()) ? "tel:{$number}" : null;
    }

    public static function email(): ?string
    {
        return filled($email = config('kadi.support.email')) ? (string) $email : null;
    }

    public static function whatsapp(): ?string
    {
        return filled($url = config('kadi.support.whatsapp')) ? (string) $url : null;
    }

    /**
     * @return array<string, array{label: string, url: string, icon: string}>
     */
    public static function socials(): array
    {
        return array_filter(
            (array) config('kadi.socials', []),
            fn ($social) => filled($social['url'] ?? null) && filled($social['icon'] ?? null),
        );
    }

    private static function digits(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) config('kadi.support.phone'));

        return $digits === '' ? null : $digits;
    }
}
