// Back from a game: the balance may have moved while playing, so the first page after the game
// page asks KadiApi for it (App\Livewire\WalletBalance::afterGame) instead of waiting for the poll.
// sessionStorage survives both wire:navigate and the full reload a game's "close" message causes.
const KEY = 'kadi:game-returned';

export function markGameReturn() {
    try {
        sessionStorage.setItem(KEY, '1');
    } catch {
        // Storage blocked (private mode): the 30-second poll still catches up.
    }
}

document.addEventListener('livewire:navigated', () => {
    let returned = false;

    try {
        returned = sessionStorage.getItem(KEY) === '1';
        sessionStorage.removeItem(KEY);
    } catch {
        return;
    }

    if (returned && !document.querySelector('[data-game-page]')) window.Livewire?.dispatch('game-returned');
});
