<?php

use App\Livewire\Wallet\Index;

/*
 * Deposits run in the deposit sheet (App\Livewire\Wallet\DepositSheet, tested in AccountAreaTest). The excise split
 * it shows lives on the wallet page's component, shared by both.
 */

test('deposit breakdown withholds 5% excise duty and credits the rest', function (string $input, int $amount, float $excise, float $credited) {
    expect(Index::depositBreakdown($input))->toBe([
        'amount' => $amount,
        'excise' => $excise,
        'credited' => $credited,
    ]);
})->with([
    'minimum' => ['10', 10, 0.5, 9.5],
    'round amount' => ['1000', 1000, 50.0, 950.0],
    'odd amount' => ['333', 333, 16.65, 316.35],
    'fractional input is rounded like the stk push' => ['99.6', 100, 5.0, 95.0],
    'blank' => ['', 0, 0.0, 0.0],
]);
