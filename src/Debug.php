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
 *     Debug::metric('Template', 'home.php');
 *     Debug::metric('Maintenance'); // Label only, no value
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
     * Unique identifier for the current PHP request lifecycle.
     *
     * Generated once in init() and sent with every entry and metric(). The server
     * uses this ID to detect new requests so the dashboard can auto-clear stale
     * entries when the "Auto-clear on new request" toggle is active.
     */
    private static string $requestId = '';

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
     * Generates a unique request ID on every call that ties all entries and
     * metrics sent during this request to the same lifecycle. When the dashboard
     * "Auto-clear on new request" toggle is active, a new request ID causes all
     * previous entries to be cleared automatically.
     *
     * @param string      $session The session token from the dashboard.
     * @param ConfigArray $options Optional configuration overrides.
     */
    public static function init(string $session, array $options = []): void
    {
        self::$config    = new Config($session, $options);
        self::$client    = new Client(self::$config);
        self::$paused    = false;
        self::$timers    = [];
        self::$requestId = bin2hex(random_bytes(8));
    }

    /**
     * Sends debug data to the dashboard.
     *
     * Accepts any data type: strings, integers, floats, booleans,
     * arrays, objects, and exceptions.
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

        return new Entry(self::$client, self::$config->getSession(), self::$requestId, $data, $label);
    }

    /**
     * Sends a toolbar metric to the dashboard.
     *
     * Metrics are displayed as small chips in the dashboard topbar.
     * Sending the same key again updates the value live (UPSERT).
     *
     * Each call includes the current request ID. When a metric key is removed
     * from the code, it will no longer carry the latest request ID — the server
     * detects this and automatically removes it from the dashboard on the next request.
     *
     * @param string      $key   The metric name — always shown in the toolbar.
     * @param string|null $value Optional value. Null shows only the key.
     */
    public static function metric(string $key, ?string $value = null): void
    {
        if (!self::isReady()) {
            return;
        }

        if (self::$client === null || self::$config === null) {
            return;
        }

        self::$client->send('/api/metric', [
            'session'    => self::$config->getSession(),
            'key'        => $key,
            'value'      => $value,
            'request_id' => self::$requestId,
        ]);
    }

    /**
     * Starts a named timer for performance measurement.
     *
     * Use {@see stopTimer()} with the same name to measure the elapsed
     * time and send it to the dashboard.
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
            self::$requestId,
            $name . ': ' . $elapsed . 'ms',
            'Timer'
        );
        $entry->type('timer')->color('orange');

        return $elapsed;
    }

    /**
     * Sends tabular data to the dashboard.
     *
     * Expects an array of associative arrays (rows). Column headers are
     * automatically derived from the union of all row keys. Pass an explicit
     * $headers array to override the auto-detected column names.
     *
     * Auto-detect example (headers from row keys):
     *
     *     Debug::table([
     *         ['id' => 1, 'name' => 'Leon', 'role' => 'Dev'],
     *         ['id' => 2, 'name' => 'Sarah', 'role' => 'Design'],
     *     ]);
     *
     * Explicit headers example:
     *
     *     Debug::table($rows, ['ID', 'Full Name', 'Role']);
     *
     * @param array<int, array<string, mixed>> $rows    The table rows to display.
     * @param list<string>|null                $headers Optional column headers. When null,
     *                                                  headers are auto-detected from row keys.
     *
     * @return Entry|null The created entry, or null if not ready.
     */
    public static function table(array $rows, ?array $headers = null): ?Entry
    {
        if (!self::isReady()) {
            return null;
        }

        if (self::$client === null || self::$config === null) {
            return null;
        }

        $data = [
            'headers' => $headers,
            'rows'    => $rows,
        ];

        $entry = new Entry(
            self::$client,
            self::$config->getSession(),
            self::$requestId,
            $data,
            'Table'
        );
        $entry->type('table')->color('blue');

        return $entry;
    }

    /**
     * Clears all entries in the current dashboard session.
     */
    public static function clear(): void
    {
        if (!self::isReady()) {
            return;
        }

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
