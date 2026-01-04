# Modern PHP API Enhancements

This document describes the modern enhancements added to PHP Swiss Ephemeris for improved developer experience.

## Table of Contents

1. [PHPStorm IDE Hints](#phpstorm-ide-hints)
2. [PHPStan Static Analysis](#phpstan-static-analysis)
3. [Object-Oriented API](#object-oriented-api)
4. [Laravel Integration](#laravel-integration)
5. [Symfony Integration](#symfony-integration)

---

## PHPStorm IDE Hints

### `.phpstorm.meta.php`

Added comprehensive IDE metadata for auto-completion and type hints in PHPStorm/IntelliJ IDEA.

**Features**:
- Planet ID auto-completion
- Calculation flags auto-completion
- House system codes auto-completion
- Sidereal mode auto-completion
- Return type hints for arrays
- Out parameter type hints

**Example** (auto-completion will suggest all valid values):
```php
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants as C;

PlanetsFunctions::calc(
    2451545.0,
    C::SE_JUPITER, // ← IDE suggests all planets
    C::SEFLG_SWIEPH | C::SEFLG_SPEED, // ← IDE suggests all flags
    $xx,
    $serr
);
```

---

## PHPStan Static Analysis

### Configuration

PHPStan Level 9 configuration added for maximum type safety.

**Install PHPStan**:
```bash
cd php-swisseph
composer require --dev phpstan/phpstan phpstan/phpstan-strict-rules
```

**Run analysis**:
```bash
# Analyze code
composer analyse

# Generate baseline (ignore existing issues)
composer analyse:baseline

# Run all checks (lint + analyse + test)
composer check
```

**Configuration** (`phpstan.neon`):
- Level 9 (strictest)
- Checks for uninitialized properties
- Checks for missing type hints
- Reports mixed types
- Validates return types

---

## Object-Oriented API

### Overview

Modern, fluent API wrapper on top of C-compatible functions.

### Quick Start

```php
use Swisseph\OO\Swisseph;
use Swisseph\Constants as C;

// Initialize
$sweph = new Swisseph('/path/to/ephemeris');

// Calculate Jupiter
$jupiter = $sweph->jupiter(2451545.0);

if ($jupiter->isSuccess()) {
    echo "Longitude: {$jupiter->longitude}°\n";
    echo "Latitude: {$jupiter->latitude}°\n";
    echo "Distance: {$jupiter->distance} AU\n";
    echo "Speed: {$jupiter->longitudeSpeed}°/day\n";
}
```

### Classes

#### `Swisseph\OO\Swisseph`

Main facade for calculations.

**Methods**:
```php
// Planet calculations
planet(int $planet, float $jd, ?int $flags = null): CalcResult
planetUt(int $planet, float $jdUt, ?int $flags = null): CalcResult

// Fluent planet methods
sun(float $jd): CalcResult
moon(float $jd): CalcResult
mercury(float $jd): CalcResult
venus(float $jd): CalcResult
mars(float $jd): CalcResult
jupiter(float $jd): CalcResult
saturn(float $jd): CalcResult
uranus(float $jd): CalcResult
neptune(float $jd): CalcResult
pluto(float $jd): CalcResult
meanNode(float $jd): CalcResult
trueNode(float $jd): CalcResult
chiron(float $jd): CalcResult

// Houses
houses(float $jdUt, float $lat, float $lon, string $hsys = 'P'): HousesResult
housesEx(float $jdUt, int $flags, float $lat, float $lon, string $hsys = 'P'): HousesResult

// Date conversion
julianDay(int $year, int $month, int $day, float $hour = 12.0, int $cal = SE_GREG_CAL): float
dateFromJulianDay(float $jd, int $cal = SE_GREG_CAL): array

// Configuration
setDefaultFlags(int $flags): self
setSiderealMode(int $mode, float $t0 = 0.0, float $ayanT0 = 0.0): self
enableSidereal(): self
disableSidereal(): self
setTopocentric(float $lon, float $lat, float $alt): self
disableTopocentric(): self
enableEquatorial(): self
disableEquatorial(): self
```

#### `Swisseph\OO\CalcResult`

Planet calculation result with property access.

**Properties**:
```php
$result->longitude          // Ecliptic longitude in degrees
$result->latitude           // Ecliptic latitude in degrees
$result->distance           // Distance in AU
$result->longitudeSpeed     // Daily speed in longitude
$result->latitudeSpeed      // Daily speed in latitude
$result->distanceSpeed      // Daily speed in distance
$result->rightAscension     // RA (if SEFLG_EQUATORIAL)
$result->declination        // Dec (if SEFLG_EQUATORIAL)
$result->x, $result->y, $result->z  // Cartesian (if SEFLG_XYZ)
```

**Methods**:
```php
isSuccess(): bool
isError(): bool
getLongitude(): float
getLatitude(): float
getDistance(): float
toArray(): array
```

#### `Swisseph\OO\HousesResult`

Houses calculation result.

**Properties**:
```php
$result->cusps              // Array of house cusps
$result->ascendant          // Ascendant angle
$result->mc                 // Midheaven
$result->armc               // Sidereal time
$result->vertex             // Vertex
$result->equatorialAscendant
$result->coAscendantKoch
$result->coAscendantMunkasey
$result->polarAscendant
```

**Methods**:
```php
isSuccess(): bool
getCusps(): array
getCusp(int $house): float
getAngles(): array
```

### Examples

#### All Planets
```php
$sweph = new Swisseph('/path/to/eph');
$jd = 2451545.0;

$planets = [
    'Sun' => $sweph->sun($jd),
    'Moon' => $sweph->moon($jd),
    'Mercury' => $sweph->mercury($jd),
    'Venus' => $sweph->venus($jd),
    'Mars' => $sweph->mars($jd),
    'Jupiter' => $sweph->jupiter($jd),
    'Saturn' => $sweph->saturn($jd),
];

foreach ($planets as $name => $result) {
    echo "$name: {$result->longitude}°\n";
}
```

#### Houses
```php
$houses = $sweph->houses(2451545.0, 50.0, 10.0, 'P');

echo "Ascendant: {$houses->ascendant}°\n";
echo "MC: {$houses->mc}°\n";

for ($i = 1; $i <= 12; $i++) {
    echo "House $i: {$houses->getCusp($i)}°\n";
}
```

#### Sidereal Calculations
```php
$sweph->setSiderealMode(C::SE_SIDM_LAHIRI)
      ->enableSidereal();

$jupiter = $sweph->jupiter(2451545.0);
echo "Jupiter (Sidereal/Lahiri): {$jupiter->longitude}°\n";
```

#### Topocentric
```php
$sweph->setTopocentric(10.0, 50.0, 100.0); // Berlin, 100m altitude

$moon = $sweph->moon(2451545.0);
echo "Moon (Topocentric): {$moon->longitude}°\n";
```

---

## Laravel Integration

### Installation

1. **Install package via Composer** (when published to Packagist)
```bash
composer require fractal/swisseph-php
```

2. **Publish configuration**
```bash
php artisan vendor:publish --provider="Swisseph\Laravel\SwissephServiceProvider"
```

3. **Configure** (`.env`)
```env
SWISSEPH_EPHE_PATH=/path/to/ephemeris/files
SWISSEPH_ENABLE_SIDEREAL=false
SWISSEPH_SIDEREAL_MODE=1  # SE_SIDM_LAHIRI
```

### Usage

#### Service Container
```php
use Swisseph\OO\Swisseph;

class AstrologyController extends Controller
{
    public function calculate(Swisseph $swisseph)
    {
        $jupiter = $swisseph->jupiter(2451545.0);

        return response()->json([
            'longitude' => $jupiter->longitude,
            'latitude' => $jupiter->latitude,
            'distance' => $jupiter->distance,
        ]);
    }
}
```

#### Facade
```php
use Swisseph\Laravel\SwissephFacade as Swisseph;

$jupiter = Swisseph::jupiter(2451545.0);
echo "Jupiter: {$jupiter->longitude}°\n";
```

#### Artisan Command
```bash
# Test installation
php artisan swisseph:test

# Test specific planet
php artisan swisseph:test --planet=saturn --jd=2451545.0
```

### Configuration

**`config/swisseph.php`**:
```php
return [
    'ephe_path' => env('SWISSEPH_EPHE_PATH', storage_path('app/swisseph/ephe')),
    'default_flags' => SEFLG_SWIEPH | SEFLG_SPEED,

    'sidereal_mode' => env('SWISSEPH_SIDEREAL_MODE', SE_SIDM_LAHIRI),
    'enable_sidereal' => env('SWISSEPH_ENABLE_SIDEREAL', false),

    'topocentric' => [
        'enabled' => env('SWISSEPH_TOPOCENTRIC_ENABLED', false),
        'longitude' => env('SWISSEPH_TOPOCENTRIC_LON', 0.0),
        'latitude' => env('SWISSEPH_TOPOCENTRIC_LAT', 0.0),
        'altitude' => env('SWISSEPH_TOPOCENTRIC_ALT', 0.0),
    ],
];
```

---

## Symfony Integration

### Installation

1. **Install bundle**
```bash
composer require fractal/swisseph-php
```

2. **Enable bundle** (`config/bundles.php`)
```php
return [
    // ...
    Swisseph\Symfony\SwissephBundle::class => ['all' => true],
];
```

3. **Configure** (`config/packages/swisseph.yaml`)
```yaml
swisseph:
    ephe_path: '%kernel.project_dir%/var/swisseph/ephe'
    default_flags: 258  # SEFLG_SWIEPH | SEFLG_SPEED

    sidereal:
        enabled: false
        mode: 1  # SE_SIDM_LAHIRI

    topocentric:
        enabled: false
        longitude: 0.0
        latitude: 0.0
        altitude: 0.0
```

### Usage

#### Auto-wiring
```php
use Swisseph\OO\Swisseph;
use Symfony\Component\HttpFoundation\JsonResponse;

class AstrologyController
{
    public function __construct(
        private Swisseph $swisseph
    ) {}

    public function calculate(): JsonResponse
    {
        $jupiter = $this->swisseph->jupiter(2451545.0);

        return new JsonResponse([
            'longitude' => $jupiter->longitude,
            'latitude' => $jupiter->latitude,
        ]);
    }
}
```

#### Service Container
```php
$swisseph = $container->get(Swisseph::class);
$jupiter = $swisseph->jupiter(2451545.0);
```

---

## Testing

Run example scripts:
```bash
# OO API example
php php-swisseph/scripts/examples/oo_api.php
```

Run tests:
```bash
# PHPUnit tests
composer test

# Static analysis
composer analyse

# All checks
composer check
```

---

## Migration Guide

### From C-style API to OO API

**Before** (C-style):
```php
use Swisseph\Swe\Functions\PlanetsFunctions;
use Swisseph\Constants as C;

$xx = [];
$serr = null;
$iflag = PlanetsFunctions::calc(2451545.0, C::SE_JUPITER, C::SEFLG_SWIEPH, $xx, $serr);

if ($iflag >= 0) {
    echo "Longitude: {$xx[0]}\n";
}
```

**After** (OO-style):
```php
use Swisseph\OO\Swisseph;

$sweph = new Swisseph('/path/to/eph');
$jupiter = $sweph->jupiter(2451545.0);

if ($jupiter->isSuccess()) {
    echo "Longitude: {$jupiter->longitude}\n";
}
```

**Both APIs are supported!** The C-style API remains for 100% compatibility with Swiss Ephemeris C library.

---

## License

AGPL-3.0 - Same as Swiss Ephemeris and the main library.
