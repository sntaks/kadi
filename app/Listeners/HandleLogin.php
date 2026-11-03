<?php

namespace App\Listeners;

use App\Account\ResponsiblePlay;
use App\Facades\KadiApi;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleLogin implements ShouldQueue
{
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if (! $user->linked_id) {
            return;
        }

        // Learn of any break now, so Play Kadi (which never waits on KadiApi) knows it on the first page.
        dispatch(fn () => app(ResponsiblePlay::class)->state($user))->afterResponse();

        try {
            $response = KadiApi::getCustomer($user->linked_id);
            $profile = $response['data'] ?? $response;

            $account = DB::connection('kadi')
                ->table('accounts')
                ->select('google_id', 'pic')
                ->where('email', $user->email)
                ->first();

            $profile['google_id'] = $account?->google_id;
            $profile['pic'] = $account?->pic;

            Cache::put("kadi.customer.{$user->id}", $profile, now()->addHour());
        } catch (RequestException|ConnectionException $e) {
            Log::error("HandleLogin: KadiApi fetch failed for user {$user->id}: ".$e->getMessage());
        } catch (\Throwable $e) {
            Log::error("HandleLogin: Failed to cache profile for user {$user->id}: ".$e->getMessage());
        }
    }
}
