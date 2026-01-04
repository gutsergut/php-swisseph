<?php

declare(strict_types=1);

namespace Swisseph\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony Bundle for Swiss Ephemeris
 *
 * @example
 * Add to config/bundles.php:
 * ```php
 * return [
 *     // ...
 *     Swisseph\Symfony\SwissephBundle::class => ['all' => true],
 * ];
 * ```
 */
class SwissephBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__, 2);
    }
}
