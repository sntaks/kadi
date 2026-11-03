<?php

namespace App\Support;

/**
 * The deployed commit, read straight from .git without starting a process
 * (exec may be disabled, and the web user may not own the repository).
 */
class GitRevision
{
    public static function head(?string $gitDir = null): ?string
    {
        $gitDir ??= base_path('.git');
        $head = @file_get_contents($gitDir.'/HEAD');

        if (! is_string($head)) {
            return null;
        }

        $head = trim($head);

        if (! str_starts_with($head, 'ref: ')) {
            return self::sha($head);
        }

        $ref = substr($head, 5);
        $loose = @file_get_contents($gitDir.'/'.$ref);

        if (is_string($loose)) {
            return self::sha(trim($loose));
        }

        $packed = @file_get_contents($gitDir.'/packed-refs');

        if (is_string($packed) && preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/m', $packed, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function sha(string $value): ?string
    {
        return preg_match('/^[0-9a-f]{40}$/', $value) ? $value : null;
    }
}
