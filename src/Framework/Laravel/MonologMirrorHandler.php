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

namespace DebugPHP\Framework\Laravel;

use DebugPHP\Debug;

/**
 * Monolog handler that mirrors records to DebugPHP.
 *
 * Implemented without extending Monolog base classes to avoid hard dependencies.
 *
 * @internal
 */
final class MonologMirrorHandler
{
    private string $label;

    private bool $includeContext;

    public function __construct(string $label, bool $includeContext)
    {
        $this->label = $label;
        $this->includeContext = $includeContext;
    }

    /**
     * Monolog calls this method for each log record.
     *
     * Monolog v2: array record
     * Monolog v3: LogRecord object
     *
     * Must return true to stop bubbling, false to continue.
     * We always return false so normal logging continues unaffected.
     *
     * @param mixed $record
     *
     * @return bool
     */
    public function handle(mixed $record): bool
    {
        try {
            $level = 'info';
            $message = 'Log message';
            $context = [];

            if (is_array($record)) {
                $message = isset($record['message']) && is_string($record['message']) ? $record['message'] : $message;
                $context = isset($record['context']) && is_array($record['context']) ? $record['context'] : [];
                $level = self::levelFromMonologArray($record) ?? $level;
            } elseif (is_object($record)) {
                // Monolog v3 LogRecord has ->level, ->message, ->context
                if (property_exists($record, 'message') && is_string($record->message)) {
                    $message = $record->message;
                }

                if (property_exists($record, 'context') && is_array($record->context)) {
                    $context = $record->context;
                }

                $level = self::levelFromMonologObject($record) ?? $level;
            }

            $payload = $this->includeContext
                ? ['message' => $message, 'context' => $context]
                : $message;

            if (method_exists(Debug::class, $level)) {
                /** @var callable $call */
                $call = [Debug::class, $level];
                $call($payload, $this->label);
            } else {
                Debug::send($payload, $this->label);
            }
        } catch (\Throwable) {
            // fail silent
        }

        return false;
    }

    /**
     * @param array<mixed> $record
     */
    private static function levelFromMonologArray(array $record): ?string
    {
        $name = $record['level_name'] ?? null;
        if (is_string($name) && $name !== '') {
            return strtolower($name);
        }

        return null;
    }

    /**
     * @param object $record
     */
    private static function levelFromMonologObject(object $record): ?string
    {
        // Monolog v3 LogRecord: $record->level is a Level enum-like object with ->name
        if (property_exists($record, 'level')) {
            $lvl = $record->level;

            if (is_object($lvl) && property_exists($lvl, 'name') && is_string($lvl->name) && $lvl->name !== '') {
                return strtolower($lvl->name);
            }

            if (is_string($lvl) && $lvl !== '') {
                return strtolower($lvl);
            }
        }

        return null;
    }
}
