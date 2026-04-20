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
 * Immutable configuration value object for DebugPHP.
 *
 * Holds all settings required to connect to the DebugPHP server.
 * Once created, the configuration cannot be modified.
 *
 * @phpstan-type ConfigArray array{
 *     host?: string,
 *     timeout?: int,
 *     enabled?: bool,
 *     dockerized?: bool,
 * }
 */
final class Config
{
    /**
     * The current version of the DebugPHP client library.
     */
    private string $version = '1.3.0';

    /**
     * The DebugPHP server URL.
     */
    private string $host;

    /**
     * The cURL request timeout in seconds.
     */
    private int $timeout;

    /**
     * Whether debugging is globally enabled.
     */
    private bool $enabled;

    /**
     * The unique session token from the dashboard.
     */
    private string $session;

    /**
     * Whether the client is running in a Dockerized environment.
     */
    private bool $dockerized = false;

    /**
     * Creates a new configuration instance.
     *
     * @param string      $session The session token from the dashboard.
     * @param ConfigArray  $options Optional configuration overrides.
     *                              - host:    Server URL (default: https://dashboard.debugphp.dev/)
     *                              - timeout: cURL timeout in seconds (default: 3)
     *                              - enabled: Enable/disable debugging (default: true)
     *                              - dockerized: Whether the client is running in a Dockerized environment (default: false)
     * @return void 
     */
    public function __construct(string $session, array $options = [])
    {
        $this->session = $session;
        $this->timeout = $options['timeout'] ?? 3;
        $this->enabled = $options['enabled'] ?? true;
        $this->dockerized = $options['dockerized'] ?? false;

        $this->host = $options['host']
            ?? ($this->dockerized ? $this->resolveDebugServerUrl() : false)
            ?: 'https://dashboard.debugphp.dev/';
    }

    /**
     * Returns the session token.
     *
     * @return string The unique session token.
     */
    public function getSession(): string
    {
        return $this->session;
    }

    /**
     * Returns the DebugPHP server host URL.
     *
     * @return string The server URL (e.g. "https://dashboard.debugphp.dev/").
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Returns the cURL request timeout.
     *
     * @return int Timeout in seconds.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Returns whether debugging is enabled.
     *
     * @return bool True if debugging is active, false otherwise.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Returns the full API endpoint URL for the given path.
     *
     * Concatenates the host URL with the given path, ensuring
     * no double slashes are created.
     *
     * @param string $path The API path (e.g. "/api/debug").
     *
     * @return string The full endpoint URL.
     */
    public function getEndpoint(string $path): string
    {
        return rtrim($this->host, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Returns the current version of the DebugPHP client library.
     * This can be used for debugging and compatibility checks.
     * 
     * @return string The client library version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Returns whether the client is running in a Dockerized environment.
     * This can be used to adjust connection settings when running inside Docker.
     * 
     * @return string|false false if not running in Docker, otherwise the Dockerized server URL.
     */
    function resolveDebugServerUrl(): string|false
    {
        $port = 8787;
        $ttl = 60 * 60; // 1 hour
        $timeoutSeconds = 0.3;
        $cacheFile = sys_get_temp_dir() . '/debugphp_server.cache';

        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            $content = file_get_contents($cacheFile);
            if ($content === 'false') {
                return false;
            }
            if ($content !== false && $content !== '') {
                return $content;
            }
        }

        $candidates = [
            'debugphp-server',
            'host.docker.internal',
            '127.0.0.1',
        ];

        foreach ($candidates as $host) {
            $socket = @fsockopen($host, $port, $errorCode, $errorMessage, $timeoutSeconds);

            if ($socket !== false) {
                fclose($socket);
                $url = "http://{$host}:{$port}";
                file_put_contents($cacheFile, $url);
                return $url;
            }
        }

        file_put_contents($cacheFile, 'false');
        return false;
    }
}
