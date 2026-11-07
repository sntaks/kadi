<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Kadi API put the game prefix back on slots and scratch-card product codes (`slots_berry_blast`,
 * `scratch_safari`). Favourites and launch history are now stored by the title's key (the code without
 * its prefix, App\Games\GameTitle::$key), so a prefix the API adds or drops never renames them again.
 * Rows written with a prefixed code (before 2026_11_08_000001, or since the API change) lose it here;
 * a favourite the player already has under the key wins.
 */
return new class extends Migration
{
    private const PREFIXES = ['slots_', 'scratch_'];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('game_launches')->whereIn('game', ['slots', 'scratch'])->whereNotNull('product')->select('game', 'product')->distinct()->get() as $row) {
                if (str_starts_with($row->product, $row->game.'_')) {
                    DB::table('game_launches')->where('game', $row->game)->where('product', $row->product)
                        ->update(['product' => substr($row->product, strlen($row->game) + 1)]);
                }
            }

            foreach (DB::table('game_favourites')->select('product')->distinct()->pluck('product') as $old) {
                if (($new = $this->key($old)) === null) {
                    continue;
                }

                // Ids are read first: MySQL refuses a DELETE whose subquery reads the same table.
                DB::table('game_favourites')->where('product', $new)->pluck('user_id')->chunk(500)
                    ->each(fn ($users) => DB::table('game_favourites')->where('product', $old)->whereIn('user_id', $users->all())->delete());

                DB::table('game_favourites')->where('product', $old)->update(['product' => $new]);
            }
        });
    }

    /** Nothing to undo: keys are what the code reads, whatever the API's prefix. */
    public function down(): void {}

    private function key(string $code): ?string
    {
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix) && strlen($code) > strlen($prefix)) {
                return substr($code, strlen($prefix));
            }
        }

        return null;
    }
};
