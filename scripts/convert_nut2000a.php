<?php

/**
 * Script to convert swenut2000a.h C arrays to PHP class
 */

$inputFile = __DIR__ . '/../../с-swisseph/swisseph/swenut2000a.h';
$outputFile = __DIR__ . '/../src/Data/NutationTables2000.php';

if (!file_exists($inputFile)) {
    die("Input file not found: $inputFile\n");
}

$content = file_get_contents($inputFile);

// Extract arrays
preg_match('/static const int16 nls\[\] = \{([^}]+)\};/s', $content, $nlsMatch);
preg_match('/static const int32 cls\[\] = \{([^}]+)\};/s', $content, $clsMatch);
preg_match('/static const int16 npl\[\] = \{([^}]+)\};/s', $content, $nplMatch);
preg_match('/static const int16 icpl\[\] = \{([^}]+)\};/s', $content, $icplMatch);

if (!$nlsMatch || !$clsMatch || !$nplMatch || !$icplMatch) {
    die("Failed to extract arrays\n");
}

// Function to convert C array to PHP array
function convertArray(string $cData, int $itemsPerRow): string {
    // Remove comments and extra whitespace
    $cData = preg_replace('/\/\*.*?\*\//s', '', $cData);

    // Extract numbers
    preg_match_all('/-?\d+/', $cData, $matches);
    $numbers = $matches[0];

    $rows = [];
    $current = [];

    foreach ($numbers as $num) {
        $current[] = $num;
        if (count($current) == $itemsPerRow) {
            $rows[] = '[' . implode(', ', $current) . ']';
            $current = [];
        }
    }

    return implode(",\n            ", $rows);
}

// Convert arrays
$nlsPhp = convertArray($nlsMatch[1], 5);  // L, L', F, D, Om (5 values per term)
$clsPhp = convertArray($clsMatch[1], 6);  // sin, t*sin, cos for longitude + cos, t*cos, sin for obliquity (6 values per term)
$nplPhp = convertArray($nplMatch[1], 14); // 14 planetary arguments
$icplPhp = convertArray($icplMatch[1], 4); // 4 coefficient indices

// Count terms
$nlsCount = substr_count($nlsPhp, '[');
$clsCount = substr_count($clsPhp, '[');
$nplCount = substr_count($nplPhp, '[');
$icplCount = substr_count($icplPhp, '[');

echo "Extracted:\n";
echo "  NLS (luni-solar args): $nlsCount terms\n";
echo "  CLS (luni-solar coefs): $clsCount terms\n";
echo "  NPL (planetary args): $nplCount terms\n";
echo "  ICPL (planetary coefs): $icplCount terms\n";

// Generate PHP class
$phpCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace Swisseph\Data;

/**
 * IAU 2000A/B Nutation Series Tables
 *
 * Port of nls[], cls[], npl[], icpl[] arrays from Swiss Ephemeris swenut2000a.h
 *
 * IAU 2000A is the high-precision model with:
 * - 678 luni-solar terms (NLS/CLS)
 * - 687 planetary terms (NPL/ICPL)
 *
 * IAU 2000B is a truncated version using only the first 77 luni-solar terms.
 *
 * Units:
 * - Coefficients in cls[] are in 0.1 microarcseconds (1e-7 arcsec)
 *
 * References:
 * - IERS Conventions 2000
 * - http://www.astro.com/swisseph/
 */
class NutationTables2000
{
    /**
     * Number of luni-solar terms in IAU 2000A
     */
    public const NLS = 678;

    /**
     * Number of luni-solar terms in IAU 2000B (truncated)
     */
    public const NLS_2000B = 77;

    /**
     * Number of planetary nutation terms
     */
    public const NPL = 687;

    /**
     * Conversion factor: 0.1 microarcsecond to degrees
     */
    public const O1MAS2DEG = 1.0 / 3600.0 / 10000000.0;

    /**
     * Luni-Solar argument multipliers
     *
     * Format: [L, L', F, D, Om]
     * L   = Mean anomaly of the Moon
     * L'  = Mean anomaly of the Sun
     * F   = Mean argument of latitude of the Moon
     * D   = Mean elongation of the Moon from the Sun
     * Om  = Mean longitude of the ascending node of the Moon
     *
     * @return array<int, array<int, int>>
     */
    public static function getNls(): array
    {
        return [
PHP;

$phpCode .= "\n            " . $nlsPhp . "\n";

$phpCode .= <<<'PHP'
        ];
    }

    /**
     * Luni-Solar nutation coefficients
     *
     * Format: [sin, t*sin, cos, cos_eps, t*cos_eps, sin_eps]
     * First 3 values: longitude (sin, t*sin, cos)
     * Last 3 values: obliquity (cos, t*cos, sin)
     *
     * Units: 0.1 microarcseconds (1e-7 arcsec)
     *
     * @return array<int, array<int, int>>
     */
    public static function getCls(): array
    {
        return [
PHP;

$phpCode .= "\n            " . $clsPhp . "\n";

$phpCode .= <<<'PHP'
        ];
    }

    /**
     * Planetary nutation argument multipliers
     *
     * Format: [L, L', F, D, Om, LMe, LVe, LEa, LMa, LJu, LSa, LUr, LNe, pA]
     * First 5: luni-solar arguments (same as nls)
     * Next 8: mean longitudes of planets Mercury through Neptune
     * Last: general accumulated precession in longitude
     *
     * @return array<int, array<int, int>>
     */
    public static function getNpl(): array
    {
        return [
PHP;

$phpCode .= "\n            " . $nplPhp . "\n";

$phpCode .= <<<'PHP'
        ];
    }

    /**
     * Planetary nutation coefficient indices
     *
     * Format: [iS_psi, iC_psi, iS_eps, iC_eps]
     * Indices into coefficient arrays for:
     * - sine of nutation in longitude
     * - cosine of nutation in longitude
     * - sine of nutation in obliquity
     * - cosine of nutation in obliquity
     *
     * @return array<int, array<int, int>>
     */
    public static function getIcpl(): array
    {
        return [
PHP;

$phpCode .= "\n            " . $icplPhp . "\n";

$phpCode .= <<<'PHP'
        ];
    }
}
PHP;

// Write output file
file_put_contents($outputFile, $phpCode);

echo "\nGenerated: $outputFile\n";
echo "File size: " . number_format(filesize($outputFile)) . " bytes\n";
