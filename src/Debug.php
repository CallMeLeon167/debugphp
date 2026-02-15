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
 * Main static facade for DebugPHP.
 *
 * Provides a simple, static API for sending debug data to the
 * DebugPHP dashboard in real-time via Server-Sent Events (SSE).
 *
 * Quick start:
 *
 *     use DebugPHP\Debug;
 *
 *     Debug::init('your-session-token');
 *     Debug::send('Hello World!');
 *     Debug::send($query, 'SQL')->color('blue');
 *
 * All methods are designed to fail silently if DebugPHP is not
 * initialized or is disabled, ensuring it never interrupts
 * the host application.
 *
 * @see https://debugphp.dev/docs
 *
 * @phpstan-type ConfigArray array{
 *     host?: string,
 *     timeout?: int,
 *     enabled?: bool,
 * }
 */
final class Debug
{
    /**
     * The current configuration instance, or null if not initialized.
     */
    private static ?Config $config = null;

    /**
     * The HTTP client instance, or null if not initialized.
     */
    private static ?Client $client = null;

    /**
     * Whether debug output is temporarily paused.
     */
    private static bool $paused = false;

    /**
     * Active timers indexed by name, storing the start timestamp.
     *
     * @var array<string, float>
     */
    private static array $timers = [];

    /**
     * Prevent instantiation.
     *
     * This class is purely static and should not be instantiated.
     */
    private function __construct() {}

    /**
     * Initializes DebugPHP with a session token and optional configuration.
     *
     * Must be called before any other Debug methods. Typically placed
     * at the top of your application's entry point or bootstrap file.
     *
     * Example:
     *
     *     Debug::init('a3f8c1');
     *     Debug::init('a3f8c1', ['host' => 'https://my-server.dev']);
     *
     * @param string      $session The session token from the dashboard.
     * @param ConfigArray  $options Optional configuration overrides.
     */
    public static function init(string $session, array $options = []): void
    {
        self::$config = new Config($session, $options);
        self::$client = new Client(self::$config);
        self::$paused = false;
        self::$timers = [];
    }

    /**
     * Sends debug data to the dashboard.
     *
     * Accepts any data type: strings, integers, floats, booleans,
     * arrays, objects, and exceptions. Objects implementing a toArray()
     * method (e.g. Laravel models) are automatically converted.
     *
     * Returns an {@see Entry} instance for optional method chaining:
     *
     *     Debug::send($data);
     *     Debug::send($query, 'SQL')->color('blue');
     *     Debug::send($error, 'Error')->color('red')->type('error');
     *
     * Returns null if DebugPHP is not initialized, disabled, or paused.
     *
     * @param mixed  $data  The data to debug.
     * @param string $label Optional label for categorization in the dashboard.
     *
     * @return Entry|null The created entry, or null if not ready.
     */
    public static function send(mixed $data, string $label = ''): ?Entry
    {
        if (!self::isReady()) {
            return null;
        }

        if (self::$client === null || self::$config === null) {
            return null;
        }

        return new Entry(self::$client, self::$config->getSession(), $data, $label);
    }

    /**
     * Starts a named timer for performance measurement.
     *
     * Use {@see stopTimer()} with the same name to measure the elapsed
     * time and send it to the dashboard.
     *
     * Example:
     *
     *     Debug::startTimer('db-query');
     *     $results = $db->query($sql);
     *     Debug::stopTimer('db-query');
     *
     * @param string $name A unique name to identify this timer.
     */
    public static function startTimer(string $name): void
    {
        if (!self::isReady()) {
            return;
        }

        self::$timers[$name] = microtime(true);
    }

    /**
     * Stops a named timer and sends the elapsed time to the dashboard.
     *
     * The timer must have been started with {@see startTimer()} using
     * the same name. Returns the elapsed time in milliseconds, or null
     * if the timer was not found or DebugPHP is not ready.
     *
     * The result is automatically sent to the dashboard as a timer
     * entry with the label "Timer" and the color orange.
     *
     * @param string $name The name of the timer to stop.
     *
     * @return float|null Elapsed time in milliseconds, or null if not found.
     */
    public static function stopTimer(string $name): ?float
    {
        if (!self::isReady()) {
            return null;
        }

        if (!isset(self::$timers[$name])) {
            return null;
        }

        if (self::$client === null || self::$config === null) {
            return null;
        }

        $elapsed = (microtime(true) - self::$timers[$name]) * 1000;
        $elapsed = round($elapsed, 2);

        unset(self::$timers[$name]);

        $entry = new Entry(
            self::$client,
            self::$config->getSession(),
            $name . ': ' . $elapsed . 'ms',
            'Timer'
        );
        $entry->type('timer')->color('orange');

        return $elapsed;
    }

    /**
     * Sends tabular data to the dashboard.
     *
     * Expects an array of associative arrays (rows). Each row should
     * have the same keys for proper table rendering in the dashboard.
     *
     * Example:
     *
     *     Debug::table([
     *         ['name' => 'Leon', 'role' => 'Developer'],
     *         ['name' => 'Sarah', 'role' => 'Designer'],
     *     ]);
     *
     * @param array<int, array<string, mixed>> $rows The table rows to display.
     *
     * @return Entry|null The created entry, or null if not ready.
     */
    public static function table(array $rows): ?Entry
    {
        if (!self::isReady()) {
            return null;
        }

        if (self::$client === null || self::$config === null) {
            return null;
        }

        $entry = new Entry(
            self::$client,
            self::$config->getSession(),
            $rows,
            'Table'
        );
        $entry->type('table');

        return $entry;
    }

    /**
     * Clears all entries in the current dashboard session.
     *
     * Sends a clear command to the server. Does nothing if
     * DebugPHP is not initialized.
     */
    public static function clear(): void
    {
        if (self::$client === null || self::$config === null) {
            return;
        }

        self::$client->send('/api/clear', [
            'session' => self::$config->getSession(),
        ]);
    }

    /**
     * Temporarily pauses all debug output.
     *
     * While paused, all Debug methods silently return null without
     * sending any data. Use {@see resume()} to re-enable output.
     *
     * This does not affect the server-side session or dashboard.
     */
    public static function pause(): void
    {
        self::$paused = true;
    }

    /**
     * Resumes debug output after a call to {@see pause()}.
     */
    public static function resume(): void
    {
        self::$paused = false;
    }

    /**
     * Checks whether DebugPHP is ready to send debug data.
     *
     * Returns true only if all conditions are met:
     * - {@see init()} has been called with a valid session token.
     * - Debugging is enabled in the configuration.
     * - Output is not currently paused.
     *
     * @return bool True if ready to send, false otherwise.
     */
    private static function isReady(): bool
    {
        if (self::$config === null || self::$client === null) {
            return false;
        }

        if (self::$paused) {
            return false;
        }

        return self::$config->isEnabled();
    }
}
