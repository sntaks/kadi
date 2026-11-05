<div>
    @if (auth()->user()->google_id)
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 text-sm text-win">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ __('Google account linked') }}
            </span>
            <flux:button
                wire:click="unlink"
                wire:confirm="{{ __('Are you sure? You may lose access if you have no password set.') }}"
                variant="ghost"
                size="sm"
            >
                {{ __('Unlink') }}
            </flux:button>
        </div>
    @else
        <a href="{{ route('auth.google.link') }}"
           class="inline-flex items-center gap-3 rounded-lg border border-line bg-felt px-4 py-2 text-sm font-medium text-white transition hover:bg-felt-2">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="size-5 shrink-0" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                <path fill="none" d="M0 0h48v48H0z"/>
            </svg>
            {{ __('Link your Google account') }}
        </a>
    @endif
</div>
