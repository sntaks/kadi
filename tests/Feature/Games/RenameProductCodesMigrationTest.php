<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

function productCodesMigration(): object
{
    return require database_path('migrations/2026_11_08_000001_rename_game_product_codes.php');
}

test('old slots and scratch codes in favourites and launches move to the API\'s new codes', function () {
    [$ann, $ben] = User::factory()->count(2)->create();

    DB::table('game_favourites')->insert([
        ['user_id' => $ann->id, 'product' => 'slots_berry_blast'],
        ['user_id' => $ann->id, 'product' => 'scratch_safari_lotto'],
        ['user_id' => $ben->id, 'product' => 'slots_berry_blast'],
        ['user_id' => $ben->id, 'product' => 'berry_blast'], // already re-added under the new code
    ]);
    DB::table('game_launches')->insert([
        ['user_id' => $ann->id, 'game' => 'slots', 'product' => 'slots_wild_safari', 'status' => 'launched'],
        ['user_id' => $ann->id, 'game' => 'scratch', 'product' => 'scratch_fruit_fiesta', 'status' => 'launched'],
        ['user_id' => $ann->id, 'game' => 'kadi', 'product' => null, 'status' => 'launched'],
        ['user_id' => $ann->id, 'game' => 'slots', 'product' => 'berry_blast', 'status' => 'failed'],
    ]);

    productCodesMigration()->up();

    expect(DB::table('game_favourites')->orderBy('user_id')->orderBy('product')->get(['user_id', 'product'])->map(fn ($r) => [$r->user_id, $r->product])->all())
        ->toBe([[$ann->id, 'berry_blast'], [$ann->id, 'safari'], [$ben->id, 'berry_blast']])
        ->and(DB::table('game_launches')->orderBy('id')->pluck('product')->all())
        ->toBe(['wild_safari', 'fruit', null, 'berry_blast']);

    productCodesMigration()->down();

    expect(DB::table('game_favourites')->orderBy('user_id')->orderBy('product')->pluck('product')->all())
        ->toBe(['scratch_safari_lotto', 'slots_berry_blast', 'slots_berry_blast'])
        ->and(DB::table('game_launches')->orderBy('id')->pluck('product')->all())
        ->toBe(['slots_wild_safari', 'scratch_fruit_fiesta', null, 'slots_berry_blast']);
});
