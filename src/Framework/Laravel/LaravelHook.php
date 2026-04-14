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
 * Laravel framework hook.
 *
 * @internal
 */
final class LaravelHook
{
    /**
     * Prevent double-installation in the same request.
     */
    private static bool $installed = false;

    /**
     * Installs the Laravel logging hook.
     *
     * Options:
     * - label: string (default: "Laravel")
     * - include_context: bool (default: true)
     *
     * @param array<string, mixed> $options
     *
     * @return bool True if installed.
     */
    public static function hook(array $options = []): bool
    {
        if (self::$installed) {
            return true;
        }

        try {
            if (!self::isLaravel()) {
                return false;
            }

            $logManager = self::resolveLogManager();
            if (!is_object($logManager)) {
                return false;
            }

            $ok = self::installListenHook($logManager, $options);
            if ($ok) {
                self::$installed = true;
                return true;
            }

            // Fallback: try Monolog handler injection
            $ok = self::installMonologHook($logManager, $options);
            if ($ok) {
                self::$installed = true;
                return true;
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Detect Laravel safely (no hard class imports).
     */
    private static function isLaravel(): bool
    {
        if (!function_exists('app')) {
            return false;
        }

        if (defined('LARAVEL_START')) {
            return true;
        }

        try {
            /** @var mixed $app */
            $app = app();
            if (!is_object($app)) {
                return false;
            }

            if (method_exists($app, 'basePath') && method_exists($app, 'environment')) {
                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Resolve the Laravel log manager / logger from the container.
     *
     * @return object|null
     */
    private static function resolveLogManager(): ?object
    {
        try {
            if (!function_exists('app')) {
                return null;
            }

            /** @var mixed $log */
            $log = app('log');

            return is_object($log) ? $log : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Install via Log::listen if available.
     *
     * Laravel typically calls the listener with ($level, $message, $context) or an event object.
     *
     * @param object               $logManager
     * @param array<string, mixed> $options
     */
    private static function installListenHook(object $logManager, array $options): bool
    {
        try {
            $label = self::optString($options, 'label', 'Laravel');
            $includeContext = self::optBool($options, 'include_context', true);

            $listener = static function (mixed ...$args) use ($label, $includeContext): void {
                try {
                    // Case A: ($level, $message, $context)
                    if (isset($args[0], $args[1]) && is_string($args[0]) && (is_string($args[1]) || is_object($args[1]))) {
                        $level = strtolower($args[0]);
                        $message = is_string($args[1]) ? $args[1] : (method_exists($args[1], '__toString') ? (string) $args[1] : 'Log message');
                        $contextRaw = (isset($args[2]) && is_array($args[2])) ? $args[2] : [];
                        $context = self::normalizeContext($contextRaw);

                        self::mirror($level, $message, $context, $label, $includeContext);

                        self::mirror($level, $message, $context, $label, $includeContext);
                        return;
                    }

                    // Case B: event object (Laravel versions differ)
                    if (isset($args[0]) && is_object($args[0])) {
                        $event = $args[0];

                        /** @var mixed $level */
                        $level = self::readPropOrMethod($event, ['level', 'levelName']);
                        /** @var mixed $message */
                        $message = self::readPropOrMethod($event, ['message']);
                        /** @var mixed $context */
                        $context = self::readPropOrMethod($event, ['context']);

                        $levelStr = is_string($level) ? strtolower($level) : 'info';
                        $msgStr = is_string($message) ? $message : (is_scalar($message) ? (string) $message : 'Log message');
                        $ctxRaw = is_array($context) ? $context : [];
                        $ctxArr = self::normalizeContext($ctxRaw);

                        self::mirror($levelStr, $msgStr, $ctxArr, $label, $includeContext);

                        self::mirror($levelStr, $msgStr, $ctxArr, $label, $includeContext);
                        return;
                    }
                } catch (\Throwable) {
                    // fail silent
                }
            };

            $callable = [$logManager, 'listen'];

            if (!is_callable($callable)) {
                return false;
            }

            /** @var mixed $result */
            $result = call_user_func($callable, $listener);

            return $result === null || $result === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Install via underlying Monolog if accessible.
     *
     * @param object               $logManager
     * @param array<string, mixed> $options
     */
    private static function installMonologHook(object $logManager, array $options): bool
    {
        try {
            $logger = $logManager;

            if (method_exists($logManager, 'getLogger')) {
                /** @var mixed $inner */
                $inner = $logManager->getLogger();
                if (is_object($inner)) {
                    $logger = $inner;
                }
            }

            if (method_exists($logManager, 'channel')) {
                /** @var mixed $ch */
                $ch = $logManager->channel();
                if (is_object($ch) && method_exists($ch, 'getLogger')) {
                    /** @var mixed $inner */
                    $inner = $ch->getLogger();
                    if (is_object($inner)) {
                        $logger = $inner;
                    }
                }
            }

            if (!method_exists($logger, 'pushHandler')) {
                return false;
            }

            $label = self::optString($options, 'label', 'Laravel');
            $includeContext = self::optBool($options, 'include_context', true);

            $handler = new MonologMirrorHandler($label, $includeContext);

            /** @var mixed $res */
            $res = $logger->pushHandler($handler);

            return $res === null || $res === true || is_object($res);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Mirror to DebugPHP using RFC5424 methods when possible.
     *
     * @param string               $level
     * @param string               $message
     * @param array<string, mixed> $context
     * @param string               $label
     */
    private static function mirror(
        string $level,
        string $message,
        array $context,
        string $label,
        bool $includeContext,
    ): void {
        try {
            $payload = $includeContext
                ? ['message' => $message, 'context' => $context]
                : $message;

            if (method_exists(Debug::class, $level)) {
                /** @var callable $call */
                $call = [Debug::class, $level];
                $call($payload, $label);
                return;
            }

            Debug::send($payload, $label);
        } catch (\Throwable) {
            // fail silent
        }
    }

    /**
     * Parse a string option with a default.
     * 
     * @param array<string, mixed> $options
     */
    private static function optString(array $options, string $key, string $default): string
    {
        $v = $options[$key] ?? null;
        return is_string($v) && $v !== '' ? $v : $default;
    }

    /**
     * Parse a boolean option with a default.
     * 
     * @param array<string, mixed> $options
     */
    private static function optBool(array $options, string $key, bool $default): bool
    {
        $v = $options[$key] ?? null;
        return is_bool($v) ? $v : $default;
    }

    /**
     * Normalizes a context array to array<string, mixed>.
     *
     * - Keeps string keys
     * - Converts non-string keys to strings (safe for JSON + dashboard)
     *
     * @param array<mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function normalizeContext(array $context): array
    {
        $out = [];

        foreach ($context as $k => $v) {
            $out[(string) $k] = $v;
        }

        /** @var array<string, mixed> $out */
        return $out;
    }

    /**
     * Reads a property or method from an object without hard dependencies.
     *
     * @param object        $obj
     * @param list<string>  $names
     *
     * @return mixed
     */
    private static function readPropOrMethod(object $obj, array $names): mixed
    {
        foreach ($names as $name) {
            if (property_exists($obj, $name)) {
                return $obj->{$name};
            }

            if (method_exists($obj, $name)) {
                try {
                    return $obj->{$name}();
                } catch (\Throwable) {
                    // continue
                }
            }

            $getter = 'get' . ucfirst($name);
            if (method_exists($obj, $getter)) {
                try {
                    return $obj->{$getter}();
                } catch (\Throwable) {
                    // continue
                }
            }
        }

        return null;
    }
}
