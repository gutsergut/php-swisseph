<?php
// PHPUnit bootstrap: устанавливаем ephemeris path, автозагрузку и глобальные функции.

// 1. Composer autoload (если доступен)
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
}

// 2. Подключаем глобальные функции (обёртки swe_*)
require_once __DIR__ . '/../src/functions.php';

// 3. Вспомогательный PSR-4 для Swisseph\ если Composer не собран
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool { return $needle === '' || strpos($haystack, $needle) === 0; }
}
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'Swisseph\\')) {
        $path = __DIR__ . '/../src/' . str_replace('Swisseph\\', '', $class) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

// 4. Поиск ephe директории со Swiss Ephemeris .se1 файлами.
// Используем несколько кандидатов относительно phpunit запуска (текущий каталог = php-swisseph).
$candidates = [
    // Репозиторий корень -> eph/ephe
    realpath(__DIR__ . '/../eph/ephe'),
    // Альтернативное размещение исходников C порта (кириллическая 'с')
    realpath(__DIR__ . '/../с-swisseph/swisseph/ephe'),
    realpath(__DIR__ . '/../../с-swisseph/swisseph/ephe'),
];

foreach ($candidates as $cand) {
    if ($cand && is_dir($cand) && is_file($cand . DIRECTORY_SEPARATOR . 'sepl_18.se1')) {
        swe_set_ephe_path($cand);
        define('SWISSEPH_EPHE_SET', true);
        break;
    }
}
if (!defined('SWISSEPH_EPHE_SET')) {
    // Fallback: если файлов нет, все тесты, требующие SWIEPH, будут репортить отсутствие.
    define('SWISSEPH_EPHE_SET', false);
    fwrite(STDERR, "[bootstrap] Warning: Swiss Ephemeris planet file sepl_18.se1 not found; SWIEPH-dependent tests will fail.\n");
}

// Polyfill for PHP < 8 (useful if someone runs bootstrap outside CI)
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
// Simple PSR-4 autoloader for tests without Composer dump-autoload
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'Swisseph\\')) {
        $path = __DIR__ . '/../src/' . str_replace('Swisseph\\', '', $class) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
// Include functions file for global wrappers
require __DIR__ . '/../src/functions.php';

// Auto-set ephemeris path once per test run (shared constant guard)
if (!defined('SWISSEPH_EPHE_SET')) {
    // Preferred relative locations to search (ordered)
    $candidates = [
        __DIR__ . '/../../eph/ephe',  // repo root eph/ephe
        __DIR__ . '/../ephe',          // legacy copy inside tests
    ];
    foreach ($candidates as $cand) {
        if (is_dir($cand) && is_file($cand . '/sepl_18.se1')) {
            swe_set_ephe_path($cand);
            define('SWISSEPH_EPHE_SET', true);
            break;
        }
    }
    if (!defined('SWISSEPH_EPHE_SET')) {
        // Fallback: still define to avoid repeated scanning
        define('SWISSEPH_EPHE_SET', false);
        fwrite(STDERR, "[bootstrap] Warning: ephemeris path not set; sepl_18.se1 not found in candidates\n");
    }
}
