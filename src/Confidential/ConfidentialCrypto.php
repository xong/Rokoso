<?php

declare(strict_types=1);

namespace App\Confidential;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encryption of confidential conversations: every case has a random key, stored encrypted with a master key
 * derived from APP_SECRET; the access code is only stored as keyed hash. Changing APP_SECRET makes all
 * conversations unreadable.
 */
final readonly class ConfidentialCrypto
{
    /** Crockford base32: no I, L, O, U */
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const int CODE_LENGTH = 16;

    private string $masterKey;
    private string $lookupKey;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->masterKey = sodium_crypto_generichash('rokoso-confidential-key|'.$secret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $this->lookupKey = sodium_crypto_generichash('rokoso-confidential-code|'.$secret, '', 32);
    }

    /**
     * Random access code (80 bits) in groups of four: "7KQ2-…".
     */
    public function newCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        return implode('-', str_split($code, 4));
    }

    /**
     * Upper case without separators; letters easily confused with digits count as those digits.
     */
    public static function normalize(string $code): string
    {
        return strtr((string) preg_replace('/[^0-9A-Z]/', '', strtoupper($code)), ['I' => '1', 'L' => '1', 'O' => '0']);
    }

    public static function isWellFormed(string $code): bool
    {
        return 1 === preg_match('/^['.self::ALPHABET.']{'.self::CODE_LENGTH.'}$/', self::normalize($code));
    }

    public function lookupHash(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), $this->lookupKey);
    }

    public function newKey(): string
    {
        return sodium_crypto_secretbox_keygen();
    }

    public function wrapKey(string $key): string
    {
        return $this->encrypt($key, $this->masterKey);
    }

    public function unwrapKey(string $wrapped): string
    {
        return $this->decrypt($wrapped, $this->masterKey);
    }

    public function encrypt(string $plain, string $key): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public function decrypt(string $encrypted, string $key): string
    {
        $data = base64_decode($encrypted, true);
        if (false === $data || \strlen($data) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted value.');
        }
        $plain = sodium_crypto_secretbox_open(substr($data, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($data, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
        if (false === $plain) {
            throw new \RuntimeException('Confidential value could not be decrypted (APP_SECRET changed?).');
        }

        return $plain;
    }
}
