<?php

/**
 * This file is part of the DebugPHP package.
 *
 * (c) Leon Schmidt <kontakt@callmeleon.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://github.com/CallMeLeon167/debugphp
 */

declare(strict_types=1);

namespace DebugPHP;

/**
 * Lightweight HTTP client for communicating with the DebugPHP server.
 *
 * Uses plain cURL with no external dependencies. Sends JSON-encoded
 * payloads via POST requests to the configured server endpoint.
 *
 * This class is not intended to be used directly. Use the {@see Debug}
 * facade instead.
 */
final class Client
{
    /**
     * The configuration instance holding server URL, timeout, etc.
     */
    private Config $config;

    /**
     * Creates a new HTTP client instance.
     *
     * @param Config $config The configuration to use for all requests.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Sends a JSON payload to the given API endpoint.
     *
     * Silently fails if debugging is disabled, the JSON encoding fails,
     * or the cURL request cannot be initialized. This ensures that
     * DebugPHP never interrupts the host application.
     *
     * @param string               $path    The API endpoint path (e.g. "/api/debug").
     * @param array<string, mixed> $payload The data to send as JSON body.
     *
     * @return bool True if the request was successful (HTTP 2xx), false otherwise.
     */
    public function send(string $path, array $payload): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $url = $this->config->getEndpoint($path);
        $json = json_encode($payload);

        if ($json === false) {
            return false;
        }

        $ch = curl_init($url);

        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config->getTimeout(),
            CURLOPT_CONNECTTIMEOUT => $this->config->getTimeout(),
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $result !== false && $httpCode >= 200 && $httpCode < 300;
    }
}
