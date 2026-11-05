<?php

namespace App\Games;

/**
 * One slide of the landing hero, resolved by HeroSlides from config/hero.php.
 *
 * `kind` is lead, promo, games, jackpot or install. `art` lists the built images
 * (`src` the largest, `srcset` every width, `w`/`h` of the largest, `role` bg, card or object; game cards add `alt`
 * and `href`, their game page or null).
 * `extra` holds per-kind data: `titles` (list<GameTitle>) for games, `prize` (cents) and `closesAt` for jackpot, and
 * `needsAccount` for every slide (its CTA is an account-only page).
 */
final readonly class HeroSlide
{
    /**
     * @param  list<array{src: string, srcset: string, w: int, h: int, role: string, alt?: string, href?: string|null}>  $art
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $key,
        public string $kind,
        public string $label,
        public ?string $eyebrow,
        public string $title,
        public ?string $copy,
        public ?string $ctaLabel,
        public ?string $ctaUrl,
        public array $art,
        public string $transition,
        public int $duration,
        public array $extra = [],
    ) {}
}
