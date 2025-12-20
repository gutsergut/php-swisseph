<?php
/**
 * Parser for swemptab.h to extract Moshier planet tables
 *
 * Converts C static arrays to PHP format
 */

// Use absolute paths
$baseDir = dirname(__DIR__, 2); // Go up to Swisseph folder
$sourceFile = $baseDir . '/с-swisseph/swisseph/swemptab.h';
$outputDir = $baseDir . '/php-swisseph/src/Moshier/Tables';

echo "Source file: $sourceFile\n";
echo "Output dir: $outputDir\n";

if (!file_exists($sourceFile)) {
    die("Source file not found: $sourceFile\n");
}

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
    echo "Created directory: $outputDir\n";
}

$content = file_get_contents($sourceFile);

// Planets to extract
$planets = [
    'mer' => 'Mercury',
    'ven' => 'Venus',
    'ear' => 'Earth',
    'mar' => 'Mars',
    'jup' => 'Jupiter',
    'sat' => 'Saturn',
    'ura' => 'Uranus',
    'nep' => 'Neptune',
    'plu' => 'Pluto',
];

foreach ($planets as $prefix => $planetName) {
    echo "Processing $planetName ($prefix)...\n";

    // Extract longitude table (tabl)
    $lonData = extractArray($content, "{$prefix}tabl");

    // Extract latitude table (tabb)
    $latData = extractArray($content, "{$prefix}tabb");

    // Extract radius table (tabr)
    $radData = extractArray($content, "{$prefix}tabr");

    // Extract argument table (args)
    $argData = extractArray($content, "{$prefix}args");

    // Extract plantbl structure
    $structData = extractPlantbl($content, "{$prefix}404");

    // Generate PHP file
    $phpContent = generatePHPFile($planetName, $lonData, $latData, $radData, $argData, $structData);

    file_put_contents("$outputDir/{$planetName}Table.php", $phpContent);
    echo "  Created {$planetName}Table.php\n";
    echo "  Lon: " . count($lonData) . " values, Lat: " . count($latData) . " values, Rad: " . count($radData) . " values, Args: " . count($argData) . " values\n";
}

echo "\nDone!\n";

function extractArray(string $content, string $name): array
{
    // Match: static double name[] = { ... };
    // or: static signed char name[] = { ... };
    // Use non-greedy match and proper ending
    $pattern = '/static\s+(?:double|signed\s+char)\s+' . preg_quote($name) . '\s*\[\s*\]\s*=\s*\{([\s\S]*?)\n\};/m';

    if (!preg_match($pattern, $content, $matches)) {
        echo "  Warning: Could not find array $name\n";
        return [];
    }

    $arrayContent = $matches[1];

    // Remove comments
    $arrayContent = preg_replace('/\/\*.*?\*\//s', '', $arrayContent);
    $arrayContent = preg_replace('/\/\/.*$/m', '', $arrayContent);

    // Extract numbers (including negative and scientific notation)
    preg_match_all('/(-?\d+\.?\d*(?:e[+-]?\d+)?)/i', $arrayContent, $numbers);

    return array_map('floatval', $numbers[1]);
}

function extractPlantbl(string $content, string $name): array
{
    // Match: static struct plantbl name = { ... };
    // Structure spans multiple lines:
    // static struct plantbl mer404 = {
    //   { 11, 14, 10, 11,  4,  5,  2,  0,  0,},
    //  6,
    //  merargs,
    //  mertabl,
    //  mertabb,
    //  mertabr,
    //  3.8709830979999998e-01,
    // };

    $pattern = '/static\s+struct\s+plantbl\s+' . preg_quote($name) . '\s*=\s*\{([\s\S]*?)\};/m';

    if (!preg_match($pattern, $content, $matches)) {
        echo "  Warning: Could not find plantbl $name\n";
        return [
            'maxHarmonic' => [0,0,0,0,0,0,0,0,0],
            'maxPowerOfT' => 0,
            'distance' => 0.0,
        ];
    }

    $structContent = $matches[1];

    // Extract max_harmonic array: { 11, 14, 10, 11, 4, 5, 2, 0, 0,}
    preg_match('/\{\s*([\d,\s]+)\}/', $structContent, $harmonics);
    $maxHarmonic = [];
    if ($harmonics) {
        preg_match_all('/(\d+)/', $harmonics[1], $nums);
        $maxHarmonic = array_map('intval', $nums[1]);
    }

    // Get all lines
    $lines = explode("\n", $structContent);
    $maxPowerOfT = 0;
    $distance = 0.0;

    foreach ($lines as $line) {
        $line = trim($line);
        // Line with just a number is max_power_of_t
        if (preg_match('/^\s*(\d+)\s*,?\s*$/', $line, $m)) {
            $maxPowerOfT = (int)$m[1];
        }
        // Line with scientific notation is distance
        if (preg_match('/([\d.]+e[+-]?\d+)/i', $line, $m)) {
            $distance = (float)$m[1];
        }
    }

    return [
        'maxHarmonic' => $maxHarmonic,
        'maxPowerOfT' => $maxPowerOfT,
        'distance' => $distance,
    ];
}

function generatePHPFile(string $planetName, array $lon, array $lat, array $rad, array $args, array $struct): string
{
    $lonStr = formatArray($lon, 'float');
    $latStr = formatArray($lat, 'float');
    $radStr = formatArray($rad, 'float');
    $argStr = formatArray($args, 'int');
    $harmonicStr = implode(', ', $struct['maxHarmonic']);

    return <<<PHP
<?php

declare(strict_types=1);

namespace Swisseph\Moshier\Tables;

use Swisseph\Moshier\PlanetTable;

/**
 * Moshier ephemeris coefficients for {$planetName}
 *
 * Auto-generated from swemptab.h
 * DO NOT EDIT - regenerate using parse_moshier_tables.php
 *
 * @see с-swisseph/swisseph/swemptab.h
 */
final class {$planetName}Table
{
    private static ?PlanetTable \$instance = null;

    public static function get(): PlanetTable
    {
        if (self::\$instance === null) {
            self::\$instance = new PlanetTable(
                maxHarmonic: [{$harmonicStr}],
                maxPowerOfT: {$struct['maxPowerOfT']},
                argTbl: self::getArgTbl(),
                lonTbl: self::getLonTbl(),
                latTbl: self::getLatTbl(),
                radTbl: self::getRadTbl(),
                distance: {$struct['distance']}
            );
        }
        return self::\$instance;
    }

    private static function getArgTbl(): array
    {
        return {$argStr};
    }

    private static function getLonTbl(): array
    {
        return {$lonStr};
    }

    private static function getLatTbl(): array
    {
        return {$latStr};
    }

    private static function getRadTbl(): array
    {
        return {$radStr};
    }
}

PHP;
}

function formatArray(array $values, string $type): string
{
    if (empty($values)) {
        return '[]';
    }

    $lines = [];
    $perLine = 8;

    for ($i = 0; $i < count($values); $i += $perLine) {
        $chunk = array_slice($values, $i, $perLine);
        if ($type === 'int') {
            $formatted = array_map(fn($v) => (int)$v, $chunk);
        } else {
            // Use %.17g for full double precision (17 significant digits)
            $formatted = array_map(fn($v) => sprintf('%.17g', $v), $chunk);
        }
        $lines[] = '            ' . implode(', ', $formatted) . ',';
    }

    return "[\n" . implode("\n", $lines) . "\n        ]";
}
