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

namespace DebugPHP\Framework;

use DebugPHP\Client;
use DebugPHP\Config;

/**
 * Internal framework integration registry.
 *
 * @internal
 */
final class FrameworkRegistry
{
    /**
     * @param string               $name
     * @param Client|null          $client
     * @param Config|null          $config
     * @param string               $requestId
     * @param array<string, mixed> $options
     *
     * @return bool
     */
    public static function hook(
        string $name,
        ?Client $client,
        ?Config $config,
        string $requestId,
        array $options = [],
    ): bool {
        if ($client === null || $config === null) {
            return false;
        }

        try {
            return match ($name) {
                'laravel' => \DebugPHP\Framework\Laravel\LaravelHook::hook($options),
                default => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }
}
