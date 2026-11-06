<?php

use Tests\TestCase;

uses(TestCase::class);

test('an account id becomes a signed v1 token that only the key pair opens', function () {
    $token = encrypt_account_id(4242);

    expect($token)->toMatch('/^v1\.[A-Za-z0-9_-]+$/')
        ->and(strlen(base64_decode(strtr(substr($token, 3), '-_', '+/'))))->toBe(16 + 16 + 32) // IV + one AES block + HMAC
        ->and(decryptAccountId($token))->toBe('4242')
        ->and(encrypt_account_id('4242'))->not->toBe($token); // random IV
});

test('a changed token or the wrong MAC key fails verification', function () {
    $token = encrypt_account_id('9007199254740991');
    $tampered = substr($token, 0, 10).($token[10] === 'A' ? 'B' : 'A').substr($token, 11);

    expect(decryptAccountId($token))->toBe('9007199254740991')
        ->and(decryptAccountId($tampered))->toBeNull();

    config(['games.launch.account_id_mac_key' => str_repeat('ab', 32)]);
    expect(decryptAccountId($token))->toBeNull();
});

test('only whole ids from 1 to 9007199254740991 are accepted', function (mixed $id) {
    encrypt_account_id($id);
})->with([0, -5, '0', '042', '12a', '9007199254740992', '99999999999999999', 4.2, null])->throws(InvalidArgumentException::class);

test('missing or malformed keys refuse to encrypt', function (array $keys) {
    config($keys);

    encrypt_account_id(1);
})->with([
    'no key' => [['games.launch.account_id_key' => null]],
    'no MAC key' => [['games.launch.account_id_mac_key' => '']],
    'short key' => [['games.launch.account_id_key' => 'abcd']],
])->throws(RuntimeException::class, 'Account ID keys are not configured');
