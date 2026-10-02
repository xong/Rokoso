<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Symmetric encryption for stored secrets (mail account passwords).
 *
 * The key is derived from APP_SECRET; changing APP_SECRET makes stored passwords unreadable.
 */
final readonly class SecretBox
{
    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->key = sodium_crypto_generichash('coop-secretbox|'.$secret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $encrypted): string
    {
        $data = base64_decode($encrypted, true);
        if (false === $data || \strlen($data) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted value.');
        }
        $plain = sodium_crypto_secretbox_open(substr($data, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($data, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        if (false === $plain) {
            throw new \RuntimeException('Secret could not be decrypted (APP_SECRET changed?).');
        }

        return $plain;
    }
}
