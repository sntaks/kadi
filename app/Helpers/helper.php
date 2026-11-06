<?php

if (! function_exists('encryptOpenSSL')) {
    /**
     * Encrypts a given value using OpenSSL, with an initialization vector (IV).
     * The encrypted value is URL-safe.
     *
     * @param  string  $plainValue  The value to encrypt
     * @return string The encrypted value, with the IV prepended and URL-safe encoded
     */
    function encryptOpenSSL(string $plainValue): string
    {
        $method = config('openssl.method'); // You can use AES-128, AES-192, etc.
        $key = config('openssl.key');
        // Generate an initialization vector (IV) for AES
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        // Encrypt the data
        $encrypted = openssl_encrypt($plainValue, $method, $key, 0, $iv);
        // Combine the IV and encrypted string (needed for decryption)
        $combined = $iv.$encrypted;

        // Use URL-safe base64 encoding
        return base64url_encode($combined);
    }
}

if (! function_exists('encrypt_account_id')) {
    /**
     * Encrypts a KadiApi customer id for the `account_id` field posted to slot and scratch-card launches:
     * AES-256-CBC with a random IV, then HMAC-SHA256 over `account-id:v1:` + IV + ciphertext, sent as
     * `v1.` + URL-safe base64 (no padding) of IV + ciphertext + tag. The game servers verify and decrypt it
     * with the same key pair, read from config (`games.launch.account_id_key` / `account_id_mac_key`).
     *
     * @param  mixed  $accountId  an int or digit string, 1 to 9007199254740991 (JavaScript's largest safe integer); anything else throws
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    function encrypt_account_id($accountId): string
    {
        if (! is_string($accountId) && ! is_int($accountId)) {
            throw new InvalidArgumentException('accountId must be an integer or digit string');
        }
        $value = (string) $accountId;
        if (! preg_match('/^[1-9][0-9]{0,15}$/D', $value) ||
            (strlen($value) === 16 && strcmp($value, '9007199254740991') > 0)) {
            throw new InvalidArgumentException('accountId must be between 1 and 9007199254740991');
        }

        $key = (string) config('games.launch.account_id_key');
        $macKey = (string) config('games.launch.account_id_mac_key');
        if (! preg_match('/^[0-9a-f]{64}$/Di', $key) || ! preg_match('/^[0-9a-f]{64}$/Di', $macKey)) {
            throw new RuntimeException('Account ID keys are not configured (GAMES_ACCOUNT_ID_KEY, GAMES_ACCOUNT_ID_MAC_KEY)');
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'aes-256-cbc', hex2bin($key), OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException('Account ID encryption failed');
        }
        $body = $iv.$ciphertext;
        $tag = hash_hmac('sha256', 'account-id:v1:'.$body, hex2bin($macKey), true);

        return 'v1.'.rtrim(strtr(base64_encode($body.$tag), '+/', '-_'), '=');
    }
}

if (! function_exists('decryptOpenSSL')) {
    /**
     * Decrypts a given value using OpenSSL, with an initialization vector (IV).
     * The input value is expected to be URL-safe encoded.
     *
     * @param  string  $encryptedWithIvValue  The value to decrypt, with the IV prepended and URL-safe encoded
     * @return string|false The decrypted value, or `false` on failure
     */
    function decryptOpenSSL(string $encryptedWithIvValue): false|string
    {
        $method = config('openssl.method'); // You can use AES-128, AES-192, etc.
        $key = config('openssl.key');
        // Decode the URL-safe base64 encoded string
        $decodedData = base64url_decode($encryptedWithIvValue);
        $iv = substr($decodedData, 0, openssl_cipher_iv_length($method));
        $encryptedData = substr($decodedData, openssl_cipher_iv_length($method));

        return openssl_decrypt($encryptedData, $method, $key, 0, $iv);
    }
}

if (! function_exists('base64url_encode')) {
    /**
     * Encodes data using URL-safe base64 encoding.
     *
     * @param  string  $data  The data to encode
     * @return string The URL-safe base64 encoded data
     */
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (! function_exists('base64url_decode')) {
    /**
     * Decodes data using URL-safe base64 encoding.
     *
     * @param  string  $data  The URL-safe base64 encoded data
     * @return string The decoded data
     */
    function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

function format_number(int $number): string
{
    if ($number < 1000) {
        return (string) Number::format($number, 0);
    }

    if ($number < 1000000) {
        return Number::format($number / 1000, 2).'k';
    }

    return Number::format($number / 1000000, 2).'M';
};

/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param  string  $email  The email address
 * @param  int  $size  Size in pixels, defaults to 64px [ 1 - 2048 ]
 * @param  string  $default_image_type  Default imageset to use [ 404 | mp | identicon | monsterid | wavatar ]
 * @param  bool  $force_default  Force default image always. By default false.
 * @param  string  $rating  Maximum rating (inclusive) [ g | pg | r | x ]
 * @param  bool  $return_image  True to return a complete IMG tag False for just the URL
 * @param  array  $html_tag_attributes  Optional, additional key/value attributes to include in the IMG tag
 * @return string containing either just a URL or a complete image tag
 *
 * @source https://gravatar.com/site/implement/images/php/
 */
function get_gravatar(
    $email,
    $size = 64,
    $default_image_type = 'mp',
    $force_default = false,
    $rating = 'g',
    $return_image = false,
    $html_tag_attributes = []
) {
    // Prepare parameters.
    $params = [
        's' => htmlentities($size),
        'd' => htmlentities($default_image_type),
        'r' => htmlentities($rating),
    ];
    if ($force_default) {
        $params['f'] = 'y';
    }

    // Generate url.
    $base_url = 'https://www.gravatar.com/avatar';
    $hash = hash('sha256', strtolower(trim($email)));
    $query = http_build_query($params);
    $url = sprintf('%s/%s?%s', $base_url, $hash, $query);

    // Return image tag if necessary.
    if ($return_image) {
        $attributes = '';
        foreach ($html_tag_attributes as $key => $value) {
            $value = htmlentities($value, ENT_QUOTES, 'UTF-8');
            $attributes .= sprintf('%s="%s" ', $key, $value);
        }

        return sprintf('<img src="%s" %s/>', $url, $attributes);
    }

    return $url;
}
