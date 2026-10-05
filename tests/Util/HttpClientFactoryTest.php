<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\HttpClientFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\NativeHttpClient;

final class HttpClientFactoryTest extends TestCase
{
    public function testFallsBackToStreamsWithoutCurlMulti(): void
    {
        self::assertInstanceOf(NativeHttpClient::class, HttpClientFactory::create(curlUsable: false));
    }
}
