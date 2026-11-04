{{--
    A catalogue title as a lobby tile. The play button opens the in-site play page; `link="page"` points it
    at the crawlable game page instead (SEO pages). Players get a working heart; guests see none.
--}}
@props(['title', 'favourites' => null, 'link' => 'play'])
@php
    /** @var \App\Games\GameTitle $title */
    $href = $link === 'page' ? ($title->pageUrl() ?? $title->playUrl()) : $title->playUrl();
@endphp
<x-ui.game-tile :title="$title" :href="$href"
                :favourite="$favourites === null ? null : in_array($title->code, $favourites, true)"
                :toggle="$favourites !== null"
                :needs-account="auth()->guest() && $href === $title->playUrl()"
                {{ $attributes }} />
