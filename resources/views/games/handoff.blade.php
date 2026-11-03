<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex">
    <title>{{ isset($action) ? 'Opening '.$game->name : 'Kadi' }}</title>
    <style>
        html,body{margin:0;height:100%;background:#0e0d0b;color:#f4f1ea;font:15px/1.5 system-ui,sans-serif}
        main{min-height:100%;display:grid;place-items:center;text-align:center;padding:24px;box-sizing:border-box}
        b{display:block;font-size:20px;margin-bottom:6px}
        p{margin:0 0 16px;color:#cfc8b6;max-width:36ch}
        button,a{display:inline-flex;align-items:center;height:44px;padding:0 20px;border-radius:12px;border:0;background:#f8c038;color:#1e1503;font:700 15px system-ui,sans-serif;text-decoration:none;cursor:pointer}
    </style>
</head>
<body>
<main data-handoff>
    @if (isset($used))
        <div data-launch-used>
            <b>This game was closed</b>
            <p>Open it again from the lobby to start a new session.</p>
            <a href="{{ route('home') }}" target="_top">Back to Kadi</a>
        </div>
        <script>
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'kadi-launch-used' }, window.location.origin);
            }
        </script>
    @elseif (isset($error))
        <div data-launch-error>
            <b>{{ $game?->name ?? 'The game' }} didn't open</b>
            <p>{{ $error }}</p>
            <a href="{{ route('home') }}" target="_top">Back to Kadi</a>
        </div>
        <script>
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'kadi-launch-error', message: @json($error) }, window.location.origin);
            }
        </script>
    @else
        <form id="launch" method="POST" action="{{ $action }}">
            @foreach ($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <b>Opening {{ $game->name }}…</b>
            <noscript><p>Tap to continue.</p><button type="submit">Open {{ $game->name }}</button></noscript>
        </form>
        <script>document.getElementById('launch').submit();</script>
    @endif
</main>
</body>
</html>
