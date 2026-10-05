<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Confidential\ConfidentialCrypto;
use PHPUnit\Framework\TestCase;

final class ConfidentialCryptoTest extends TestCase
{
    public function testCodeIsWellFormedAndToleratesTypingVariants(): void
    {
        $crypto = new ConfidentialCrypto('secret');
        $code = $crypto->newCode();

        self::assertMatchesRegularExpression('/^[0-9A-Z]{4}(-[0-9A-Z]{4}){3}$/', $code);
        self::assertTrue(ConfidentialCrypto::isWellFormed($code));
        self::assertSame($crypto->lookupHash($code), $crypto->lookupHash(' '.strtolower(str_replace('-', '', $code)).' '));
        self::assertSame($crypto->lookupHash('0000-1111-2222-3333'), $crypto->lookupHash('OOOO IiLl 2222 3333'));
        self::assertFalse(ConfidentialCrypto::isWellFormed('1234-5678'));
        self::assertFalse(ConfidentialCrypto::isWellFormed('UUUU-UUUU-UUUU-UUUU'));
        self::assertNotSame($crypto->lookupHash($code), (new ConfidentialCrypto('other'))->lookupHash($code));
    }

    public function testEncryptionRoundTripWithWrappedKey(): void
    {
        $crypto = new ConfidentialCrypto('secret');
        $key = $crypto->newKey();
        $wrapped = $crypto->wrapKey($key);
        $encrypted = $crypto->encrypt('Vertraulich', $key);

        self::assertStringNotContainsString('Vertraulich', $encrypted);
        self::assertNotSame($encrypted, $crypto->encrypt('Vertraulich', $key));
        self::assertSame('Vertraulich', $crypto->decrypt($encrypted, $crypto->unwrapKey($wrapped)));

        $this->expectException(\RuntimeException::class);
        (new ConfidentialCrypto('changed'))->unwrapKey($wrapped);
    }
}
