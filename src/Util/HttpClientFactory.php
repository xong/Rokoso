<?php

declare(strict_types=1);

namespace App\Util;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory of the HttpClient transport (see Kernel::process): some web hostings load the curl extension
 * but disable curl_multi_exec(), which HttpClient::create() does not check – then use the stream-based client.
 */
final class HttpClientFactory
{
    /**
     * @param array<string, mixed> $defaultOptions
     */
    public static function create(array $defaultOptions = [], int $maxHostConnections = 6, int $maxPendingPushes = 50, ?bool $curlUsable = null): HttpClientInterface
    {
        $curlUsable ??= \function_exists('curl_multi_exec');
        if (!$curlUsable) {
            return new NativeHttpClient($defaultOptions, $maxHostConnections);
        }

        return HttpClient::create($defaultOptions, $maxHostConnections, $maxPendingPushes);
    }
}
