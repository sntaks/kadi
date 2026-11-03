{{--
    The one "Play Kadi" control. Signed-in players only (callers show their own sign-up link to guests).
    - Launcher on and Kadi framed: a link to the in-site play page.
    - Launcher on, Kadi not framed yet: a POST through the launcher, which logs the launch and opens Kadi
      in this page.
    - Launcher off: Kadi's existing ggid form, posted straight to the Kadi play URL.
    No inline JavaScript: every variant is a real link or form.
--}}
@props(['disabled' => false])
@php
    $viaLauncher = (bool) config('platform.token_launch');
    $framed = \App\Games\Game::framed('kadi');
@endphp
@auth
    @if ($disabled)
        <button type="button" disabled data-play-kadi {{ $attributes }}>{{ $slot }}</button>
    @elseif ($viaLauncher && $framed)
        <a href="{{ route('play', ['game' => 'kadi']) }}" data-play-kadi {{ $attributes }}>{{ $slot }}</a>
    @else
        <form method="POST" class="contents" data-play-kadi
              action="{{ $viaLauncher ? route('play.launch', ['game' => 'kadi']) : config('services.kadi_api.play_url') }}">
            @if ($viaLauncher)
                @csrf
            @else
                <input type="hidden" name="ggid" value="{{ auth()->user()->account_no }}">
            @endif
            <button type="submit" {{ $attributes }}>{{ $slot }}</button>
        </form>
    @endif
@endauth
