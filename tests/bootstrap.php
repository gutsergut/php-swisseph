<?php

declare(strict_types=1);

// PHPUnit bootstrap shared by the self-contained and ephemeris-backed suites.

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
$hasComposerAutoload = is_file($vendorAutoload);

if ($hasComposerAutoload) {
    require $vendorAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Swisseph\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

require_once dirname(__DIR__) . '/src/functions.php';

/**
 * Resolve optional Swiss Ephemeris test data without machine-specific paths.
 */
function resolveTestEphemerisPath(): ?string
{
    $configuredPath = getenv('SWEPH_EPHE_DIR');
    $candidates = [
        is_string($configuredPath) && $configuredPath !== '' ? $configuredPath : null,
        __DIR__ . '/fixtures/ephe',
        dirname(__DIR__) . '/eph/ephe',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === null) {
            continue;
        }

        $resolved = realpath($candidate);
        if ($resolved !== false && is_file($resolved . DIRECTORY_SEPARATOR . 'sepl_18.se1')) {
            return $resolved;
        }
    }

    return null;
}

$ephemerisPath = resolveTestEphemerisPath();
if ($ephemerisPath !== null) {
    swe_set_ephe_path($ephemerisPath);
    define('SWISSEPH_EPHE_SET', true);
    define('SWISSEPH_TEST_EPHE_PATH', $ephemerisPath);
} else {
    define('SWISSEPH_EPHE_SET', false);
    define('SWISSEPH_TEST_EPHE_PATH', '');
}
