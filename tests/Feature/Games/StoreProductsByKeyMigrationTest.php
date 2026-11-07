<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('prefixed slots and scratch codes in favourites and launches are stored by key', function () {
    [$ann, $ben] = User::factory()->count(2)->create();

    DB::table('game_favourites')->insert([
        ['user_id' => $ann->id, 'product' => 'slots_berry_blast'],
        ['user_id' => $ann->id, 'product' => 'scratch_safari'],
        ['user_id' => $ann->id, 'product' => 'wild_safari'], // already a key
        ['user_id' => $ben->id, 'product' => 'slots_berry_blast'],
        ['user_id' => $ben->id, 'product' => 'berry_blast'], // the same title, already by key
    ]);
    DB::table('game_launches')->insert([
        ['user_id' => $ann->id, 'game' => 'slots', 'product' => 'slots_wild_safari', 'status' => 'launched'],
        ['user_id' => $ann->id, 'game' => 'scratch', 'product' => 'scratch_fruit', 'status' => 'launched'],
        ['user_id' => $ann->id, 'game' => 'kadi', 'product' => null, 'status' => 'launched'],
        ['user_id' => $ann->id, 'game' => 'slots', 'product' => 'berry_blast', 'status' => 'failed'],
    ]);

    (require database_path('migrations/2026_11_08_000002_store_game_products_by_key.php'))->up();

    expect(DB::table('game_favourites')->orderBy('user_id')->orderBy('product')->get(['user_id', 'product'])->map(fn ($r) => [$r->user_id, $r->product])->all())
        ->toBe([[$ann->id, 'berry_blast'], [$ann->id, 'safari'], [$ann->id, 'wild_safari'], [$ben->id, 'berry_blast']])
        ->and(DB::table('game_launches')->orderBy('id')->pluck('product')->all())
        ->toBe(['wild_safari', 'fruit', null, 'berry_blast']);
});
