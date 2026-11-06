<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Kadi API renamed the slots and scratch-card product codes: slots lost their `slots_`
 * prefix (`slots_berry_blast` → `berry_blast`) and the four scratch cards got short names.
 * Favourites and launch history (which feeds "Most played") keep the old codes, so they are
 * renamed here; a favourite the player already re-added under the new code wins.
 */
return new class extends Migration
{
    private const SCRATCH = [
        'scratch_fantasy_quest' => 'fantasy',
        'scratch_fruit_fiesta' => 'fruit',
        'scratch_lucky_leprechaun_charms' => 'leprechaun',
        'scratch_safari_lotto' => 'safari',
    ];

    public function up(): void
    {
        $this->rename(fn (string $code): ?string => self::SCRATCH[$code]
            ?? (str_starts_with($code, 'slots_') ? substr($code, strlen('slots_')) : null));
    }

    public function down(): void
    {
        $scratch = array_flip(self::SCRATCH);

        $this->rename(fn (string $code, ?string $game = null): ?string => $scratch[$code]
            ?? (($game ?? 'slots') === 'slots' && ! str_starts_with($code, 'slots_') && ! in_array($code, ['kadi', 'sportsbook'], true) ? 'slots_'.$code : null));
    }

    /** @param  callable(string, ?string=): ?string  $map  old code (and game, for launches) => new code, or null to keep */
    private function rename(callable $map): void
    {
        DB::transaction(function () use ($map) {
            foreach (DB::table('game_launches')->whereNotNull('product')->select('game', 'product')->distinct()->get() as $row) {
                if (($new = $map($row->product, $row->game)) !== null) {
                    DB::table('game_launches')->where('game', $row->game)->where('product', $row->product)->update(['product' => $new]);
                }
            }

            foreach (DB::table('game_favourites')->select('product')->distinct()->pluck('product') as $old) {
                if (($new = $map($old)) === null) {
                    continue;
                }

                // A player who already has the new code keeps that row; their old one goes. (Ids are
                // read first: MySQL refuses a DELETE whose subquery reads the same table.)
                DB::table('game_favourites')->where('product', $new)->pluck('user_id')->chunk(500)
                    ->each(fn ($users) => DB::table('game_favourites')->where('product', $old)->whereIn('user_id', $users->all())->delete());

                DB::table('game_favourites')->where('product', $old)->update(['product' => $new]);
            }
        });
    }
};
