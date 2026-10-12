<?php

declare(strict_types=1);

namespace App\Model\Security;

use RuntimeException;

/**
 * Symmetric encryption for values that must be read back (personal tokens); the key is derived from APP_SECRET.
 */
final class Crypto
{
    private readonly string $key;

    public function __construct(string $appSecret)
    {
        if ($appSecret === '') {
            throw new RuntimeException('APP_SECRET must be set.');
        }

        $this->key = sodium_crypto_generichash($appSecret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return sodium_bin2base64($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key), \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public function decrypt(string $encrypted): string
    {
        $binary = sodium_base642bin($encrypted, \SODIUM_BASE64_VARIANT_ORIGINAL);
        $nonce = substr($binary, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($binary, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        if ($plain === false) {
            throw new RuntimeException('The value cannot be decrypted with the current APP_SECRET.');
        }

        return $plain;
    }
}
