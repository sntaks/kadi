<?php

use App\Games\GameName;

test('game names get a capital at the start of every word and nowhere else', function (string $api, string $shown) {
    expect(GameName::format($api))->toBe($shown);
})->with([
    ['BERRY BLAST', 'Berry Blast'],
    ['Curse of the Pyramid', 'Curse Of The Pyramid'],
    ['Captain’s Chest', 'Captain’s Chest'],
    ["PEARL DIVER'S DELIGHT", "Pearl Diver's Delight"],
    ['  coin   flip ', 'Coin Flip'],
    ['jack-in-the-box', 'Jack-In-The-Box'],
    ['Lucky 7s', 'Lucky 7s'],
]);

test('a name made from a product code drops the game prefix', function () {
    expect(GameName::fromCode('slots', 'slots_neon_city'))->toBe('Neon City')
        ->and(GameName::fromCode('scratch', 'goldmine'))->toBe('Goldmine');
});
