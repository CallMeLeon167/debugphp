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
 * Represents a single debug entry sent to the dashboard.
 *
 * Instances are created by {@see Debug::send()} and returned to allow
 * fluent method chaining for setting color and type:
 *
 *     Debug::send($query, 'SQL')->color('blue')->type('sql');
 *
 * Each call to {@see color()} or {@see type()} immediately dispatches
 * an updated payload to the server so the dashboard reflects changes
 * in real-time.
 *
 * Data is serialized using PHP's native serialize() and base64-encoded
 * for safe transport. This preserves full PHP type information including
 * objects, class instances, and complex nested structures.
 *
 * This class is not intended to be instantiated directly.
 */
final class Entry
{
    /**
     * List of valid color names accepted by the dashboard.
     *
     * @var list<string>
     */
    private const VALID_COLORS = [
        'red',
        'blue',
        'green',
        'orange',
        'purple',
        'gray',
    ];

    /**
     * The HTTP client used to send data to the server.
     */
    private Client $client;

    /**
     * The current session token.
     */
    private string $session;

    /**
     * The raw debug data provided by the user.
     */
    private mixed $data;

    /**
     * A descriptive label for categorizing this entry (e.g. "SQL", "Error").
     */
    private string $label;

    /**
     * The display color for this entry in the dashboard.
     */
    private string $color;

    /**
     * The type/category of this entry (e.g. "info", "sql", "error", "timer").
     */
    private string $type;

    /**
     * The source file where Debug::send() was called.
     */
    private string $file;

    /**
     * The directory path of the source file.
     */
    private string $path;

    /**
     * The line number where Debug::send() was called.
     */
    private int $line;

    /**
     * Creates a new debug entry and immediately dispatches it to the server.
     *
     * The source file and line number are automatically resolved from
     * the call stack using {@see resolveCaller()}.
     *
     * @param Client $client  The HTTP client instance.
     * @param string $session The current session token.
     * @param mixed  $data    The debug data to send.
     * @param string $label   A descriptive label for this entry.
     */
    public function __construct(Client $client, string $session, mixed $data, string $label = '')
    {
        $this->client  = $client;
        $this->session = $session;
        $this->data    = $data;
        $this->label   = $label;
        $this->color   = 'gray';
        $this->type    = 'info';

        $caller     = $this->resolveCaller();
        $this->file = $caller['file'];
        $this->path = $caller['path'];
        $this->line = $caller['line'];

        $this->dispatch();
    }

    /**
     * Sets the display color for this entry in the dashboard.
     *
     * Available colors: red, blue, green, orange, purple, gray.
     * Invalid color names are silently ignored.
     *
     * @param string $color The color name to apply.
     *
     * @return $this For method chaining.
     */
    public function color(string $color): self
    {
        if (in_array($color, self::VALID_COLORS, true)) {
            $this->color = $color;
        }

        $this->dispatch();

        return $this;
    }

    /**
     * Sets the type/category for this entry in the dashboard.
     *
     * Common types: info, sql, error, success, cache, timer.
     * Custom types are allowed and will be displayed as-is.
     *
     * @param string $type The type name to apply.
     *
     * @return $this For method chaining.
     */
    public function type(string $type): self
    {
        $this->type = $type;

        $this->dispatch();

        return $this;
    }

    /**
     * Sends the current entry payload to the DebugPHP server.
     *
     * Called automatically on construction and after each chained
     * method call ({@see color()}, {@see type()}) to ensure the
     * dashboard always shows the latest state.
     */
    private function dispatch(): void
    {
        $this->client->send('/api/debug', [
            'session'   => $this->session,
            'data'      => $this->encodeData($this->data),
            'label'     => $this->label,
            'color'     => $this->color,
            'type'      => $this->type,
            'origin'    => [
                'file' => $this->file,
                'path' => $this->path,
                'line' => $this->line,
            ],
            'timestamp' => microtime(true),
        ]);
    }


    /**
     * Encodes the debug data for safe transport to the server.
     * 
     * Exceptions are converted to structured arrays containing class, message,
     * code, file, line, and a simplified stack trace. This ensures all
     * relevant information is preserved without relying on fragile string representations.
     * All data is then serialized using PHP's native serialize() and base64-encoded
     * to maintain full type fidelity, including objects and complex structures.
     * 
     * @param mixed $data The original debug data provided by the user.
     * @return mixed The encoded data ready for transmission to the server.
     */
    private function encodeData(mixed $data): mixed
    {
        if ($data instanceof \Throwable) {
            $data = [
                'exception' => $data::class,
                'message' => $data->getMessage(),
                'code' => $data->getCode(),
                'file' => $data->getFile(),
                'line' => $data->getLine(),
                'trace' => array_map(
                    static fn(array $frame): string => ($frame['file'] ?? 'unknown')
                        . ':' . ($frame['line'] ?? 0),
                    $data->getTrace()
                ),
            ];
        }

        return base64_encode(serialize($data));
    }

    /**
     * Resolves the original file and line number where Debug::send() was called.
     *
     * Walks up the debug backtrace and returns the first frame that is
     * not inside the DebugPHP source directory. This ensures the dashboard
     * shows the actual caller location, not internal package files.
     *
     * @return array{file: string, path: string, line: int} The resolved file basename, path, and line number.
     */
    private function resolveCaller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            $fullpath = $frame['file'] ?? '';

            $file = basename($fullpath);
            $path = dirname($fullpath);

            $function = $frame['function'] ?? '';

            if ($function === 'send' && str_contains($class, 'DebugPHP\\Debug')) {
                return [
                    'file' => $file,
                    'path' => $path,
                    'line' => $frame['line'] ?? 0,
                ];
            }
        }

        return [
            'file' => 'unknown',
            'path' => 'unknown',
            'line' => 0,
        ];
    }
}
