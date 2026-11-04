<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates GMS, the staff back office, on /api/v1/backoffice (docs/backoffice-api.md).
 *
 * - Fails closed: with no secret configured every request is refused (503).
 * - X-Backoffice-Signature = hash_hmac('sha256', "{timestamp}.{requestId}.{METHOD}.{requestUri}.{sha256(rawBody)}",
 *   $secret), compared in constant time against every configured secret (rotation). Binding the method and the path
 *   means a signed call can never be replayed against another endpoint.
 * - X-Backoffice-Timestamp within 5 minutes of now; X-Backoffice-Request-Id is 8-100 of [A-Za-z0-9-_].
 * - Writes are idempotent by request id: a successful answer is kept for a day and returned again to a retry of the
 *   same request (with Backoffice-Replayed: true), so a timed-out call that GMS retries never acts twice. The same id
 *   while the first call is still running is a 409; the same id for a different request is a 422.
 * - Any authentication failure is a 401.
 */
class VerifyBackofficeSignature
{
    private const MAX_SKEW_SECONDS = 300;

    private const REPLAY_TTL_SECONDS = 86400;

    private const LOCK_SECONDS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        /** @var list<string> $secrets */
        $secrets = config('kadi.backoffice.secrets', []);

        if ($secrets === []) {
            return response()->json(['message' => 'The back-office API is not enabled.'], 503);
        }

        $timestamp = (string) $request->header('X-Backoffice-Timestamp');
        $requestId = (string) $request->header('X-Backoffice-Request-Id');

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_SKEW_SECONDS
            || ! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $requestId)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // The body goes in as its hash: fixed length and last, so no field can be shifted into another.
        $signed = implode('.', [$timestamp, $requestId, $request->getMethod(), $request->getRequestUri(), hash('sha256', $request->getContent())]);
        $signature = (string) $request->header('X-Backoffice-Signature');
        $matched = false;

        foreach ($secrets as $secret) {
            if (hash_equals(hash_hmac('sha256', $signed, $secret), $signature)) {
                $matched = true;
            }
        }

        if (! $matched) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        return $this->once($request, $requestId, $next);
    }

    private function once(Request $request, string $requestId, Closure $next): Response
    {
        $key = 'backoffice:request:'.hash('sha256', $requestId);
        $fingerprint = hash('sha256', $request->getMethod().' '.$request->getRequestUri().' '.$request->getContent());

        if (($stored = $this->stored($key)) !== null) {
            return $this->replay($stored, $fingerprint);
        }

        // Held for as long as the slowest action may run (a result on a busy match settles every leg in one go).
        $lock = Cache::lock($key.':lock', self::LOCK_SECONDS);

        if (! $lock->get()) {
            return response()->json(['message' => 'This request is still being handled.'], 409);
        }

        try {
            // The first call may have finished between our first look and taking the lock.
            if (($stored = $this->stored($key)) !== null) {
                return $this->replay($stored, $fingerprint);
            }

            $response = $next($request);

            // Only a success is kept. A refusal (validation, 409, 429, 5xx) did nothing, so a retry may run again.
            if ($response->isSuccessful()) {
                Cache::put($key, ['fingerprint' => $fingerprint, 'status' => $response->getStatusCode(), 'body' => (string) $response->getContent()], self::REPLAY_TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Read fresh each time: another call may have stored its answer since.
     *
     * @return array{fingerprint: string, status: int, body: string}|null
     *
     * @phpstan-impure
     */
    private function stored(string $key): ?array
    {
        $stored = Cache::get($key);

        return is_array($stored) ? $stored : null;
    }

    /**
     * @param  array{fingerprint: string, status: int, body: string}  $stored
     */
    private function replay(array $stored, string $fingerprint): Response
    {
        if (! hash_equals($stored['fingerprint'], $fingerprint)) {
            return response()->json(['message' => 'This request id was already used for a different request.', 'code' => 'request_id_reused'], 422);
        }

        return response($stored['body'], $stored['status'], ['Content-Type' => 'application/json', 'Backoffice-Replayed' => 'true']);
    }
}
