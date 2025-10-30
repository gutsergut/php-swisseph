<?php
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
