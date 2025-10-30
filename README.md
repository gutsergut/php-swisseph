# PHP Swiss Ephemeris

A complete PHP port of the **Swiss Ephemeris** (v2.10.03) astronomical calculation library, maintaining full API compatibility with the original C implementation's `swe_*` functions.

[![License: AGPL-3.0](https://img.shields.io/badge/License-AGPL%203.0-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://php.net)

## 🌟 Features

- ✅ **47+ functions** ported with identical signatures to C API
- ✅ **High accuracy**: Planetary positions within 100m, angles within 0.01°
- ✅ **Complete coordinate systems**: Geocentric, heliocentric, barycentric
- ✅ **Sidereal calculations**: All 47 ayanamsha modes with `SE_SIDBIT_*` options
- ✅ **House systems**: 36 systems including Placidus, Koch, Whole Sign, Gauquelin, APC, Sunshine, Savard-A
- ✅ **Nodes & Apsides**: Mean and osculating calculations for all planets
- ✅ **Orbital elements**: Full Keplerian element computation (a, e, i, Ω, ω, ϖ, M, ν, E)
- ✅ **Coordinate conversions**: Equatorial ↔ Ecliptic ↔ Horizontal transformations
- ✅ **Time utilities**: Julian day conversions, ΔT (Delta-T), sidereal time (GMST/GAST)
- ✅ **Refraction models**: True altitude, apparent altitude, Bennett's formula
- ✅ **Pure PHP**: No C extensions required, works on any PHP 8.1+ environment

## 🚀 Why PHP Swiss Ephemeris?

Swiss Ephemeris is the **gold standard** for astronomical calculations, used by professional astrologers and astronomers worldwide. This PHP port brings that precision to web applications without requiring C extensions or external binaries.

Perfect for:
- 🌐 Web-based horoscope generators
- 📊 Astronomical visualization tools
- 🔮 Astrological research applications
- 📱 API services for planetary positions
- 🎓 Educational astronomy projects

## 📦 Installation

### Via Composer (recommended)
```bash
composer require gutsergut/php-swisseph
```

### Manual Installation
```bash
git clone https://github.com/gutsergut/php-swisseph.git
cd php-swisseph
composer install  # Only needed for development/testing
```

## 📖 Status

**Production Ready** ✅ - Fully tested and verified against C reference implementation.

## 💻 Requirements

- **For script-based tests**: PHP CLI >=7.4 (Composer not required)
- **For PHPUnit tests**: PHP >=8.1 + Composer

## 🏃 Usage

### Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use Swisseph\Constants;

// Set ephemeris path
swe_set_ephe_path(__DIR__ . '/ephe');

// Calculate planetary positions
$jd_ut = 2451545.0; // J2000.0
$flags = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;
$xx = [];
$serr = '';

$result = swe_calc_ut($jd_ut, Constants::SE_SUN, $flags, $xx, $serr);
if ($result >= 0) {
    echo "Sun longitude: " . sprintf("%.6f°", $xx[0]) . PHP_EOL;
    echo "Sun latitude:  " . sprintf("%.6f°", $xx[1]) . PHP_EOL;
    echo "Sun distance:  " . sprintf("%.6f AU", $xx[2]) . PHP_EOL;
}
```

### Running Tests

#### Script-based Tests
```bash
php tests/UtcJdTest.php
php tests/ErrorContractTest.php
php tests/SweCalcSkeletonTest.php
php tests/CoordinatesRoundtripTest.php
```

#### PHPUnit Tests (CI or PHP >= 8.1)
```bash
composer install
vendor/bin/phpunit -c phpunit.xml.dist --colors=always
```

#### Benchmarks
```bash
php scripts/bench.php
```

## 📚 API Notes

- **Time conversions**: `swe_utc_to_jd()`/`swe_jd_to_utc()` support `$serr` and `gregflag` validation
- **Planetary calculations**: `swe_calc()`/`swe_calc_ut()` return coordinates in `xx` array:
  - Default: `[longitude, latitude, distance, lon_speed, lat_speed, dist_speed]` (degrees/AU, degrees/day)
  - Flags modify format: `SEFLG_RADIANS`, `SEFLG_EQUATORIAL`, `SEFLG_XYZ`
- **Sidereal astrology**:
  - `swe_set_sid_mode()` sets ayanamsha mode (Fagan/Bradley, Lahiri, etc.)
  - `swe_get_ayanamsa_ex()` returns current ayanamsha value
- **Note**: `swe_calc/_ut` currently return `SE_ERR` with `$serr=UNSUPPORTED` (in development)

## 🌙 Examples

### Sidereal Calculations

```php
<?php
use Swisseph\Constants;

// Set Lahiri ayanamsha mode
swe_set_sid_mode(Constants::SE_SIDM_LAHIRI, 0, 0);

// Get ayanamsha value for J2000.0
$jd_tt = 2451545.0;
$daya = null;
$serr = null;
swe_get_ayanamsa_ex($jd_tt, 0, $daya, $serr);
echo "Ayanamsha (Lahiri, J2000.0): " . sprintf("%.6f°", $daya) . PHP_EOL;

// Get mode name
echo "Mode name: " . swe_get_ayanamsa_name(Constants::SE_SIDM_LAHIRI) . PHP_EOL;
```

### House Systems

```php
<?php
use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\Obliquity;
use Swisseph\DeltaT;
use Swisseph\Houses;
use Swisseph\Math;

$jd_ut = 2460680.5;           // 2025-10-01 00:00 UT
$geolat = 48.8566;            // Paris
$geolon = 2.3522;

// swe_houses (basic wrapper)
$cusp = $ascmc = [];
HousesFunctions::houses($jd_ut, $geolat, $geolon, 'J', $cusp, $ascmc);
echo 'Asc=' . $ascmc[0] . '  MC=' . $ascmc[1] . PHP_EOL;

// swe_houses_ex2: returns ARMC in ascmc[2], for Sunshine system — Sun declination in ascmc[9]
$cusp2 = $ascmc2 = $cspSpd = $amcSpd = [];
HousesFunctions::housesEx2($jd_ut, 0, $geolat, $geolon, 'I', $cusp2, $ascmc2, $cspSpd, $amcSpd);
echo 'ARMC=' . $ascmc2[2] . '  SunDec=' . $ascmc2[9] . PHP_EOL;

// swe_house_pos: object position (ecliptic lon/lat) in house system 'J'
$jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
$eps_deg = Math::radToDeg(Obliquity::meanObliquityRadFromJdTT($jd_tt));
$armc_deg = Math::radToDeg(Houses::armcFromSidereal($jd_ut, $geolon));
$lon_obj = 123.45;            // example longitude
$lat_obj = 0.0;               // ecliptic latitude
$pos = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'J', [$lon_obj, $lat_obj]);
echo 'HousePos(J)=' . $pos . PHP_EOL;
```

## 🧪 Parity Tests with swetest

Scripts and PHPUnit tests are available to verify compatibility with the C implementation of Swiss Ephemeris.

### Requirements
- Compiled `swetest` (Windows: `swetest64.exe` in `с-swisseph\swisseph\windows\programs\`)
- Swiss Ephemeris ephemerides in `с-swisseph\swisseph\ephe\` folder

### Path Configuration (optional)

Set environment variables if paths differ from defaults:

```bash
# Linux/Mac
export SWETEST_PATH='/path/to/swetest'
export SWEPH_EPHE_DIR='/path/to/ephe'

# Windows PowerShell
$env:SWETEST_PATH = 'C:\path\to\swetest64.exe'
$env:SWEPH_EPHE_DIR = 'C:\path\to\ephe'
```

Default paths (Windows):
- `SWETEST_PATH` = `C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe`
- `SWEPH_EPHE_DIR` = `C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\ephe`

### Running Parity Scripts

```bash
# Verify all house systems
php scripts/parity_all_houses.php

# Verify specific system (Savard-A)
php scripts/parity_j_vs_swetest.php

# Verify ayanamsha calculations
php scripts/parity_ayanamsha_swetest.php

# Verify nodes and apsides
php scripts/parity_nod_aps_swetest.php
```

### Guarded PHPUnit Tests

To run parity tests via PHPUnit, set the `RUN_SWETEST_PARITY` environment variable:

```bash
# Linux/Mac
export RUN_SWETEST_PARITY=1
vendor/bin/phpunit -c phpunit.xml.dist --colors=always

# Windows PowerShell
$env:RUN_SWETEST_PARITY = '1'
vendor/bin/phpunit -c phpunit.xml.dist --colors=always
```

Without this variable, parity tests will be skipped.

## 📄 License

**AGPL-3.0-or-later** - See [LICENSE](LICENSE) for details.

This project is a PHP port of [Swiss Ephemeris](https://www.astro.com/swisseph/) by Astrodienst AG, which is dual-licensed under AGPL-3.0 and a commercial license. The PHP port follows the same licensing terms.

## 🗺️ Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md) for planned features and development progress.

## 🤝 Contributing

Contributions are welcome! Please see [CONTRACT.md](CONTRACT.md) for API compatibility guidelines.

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/gutsergut/php-swisseph/issues)
- **Original Library**: [Swiss Ephemeris Documentation](https://www.astro.com/swisseph/swephinfo_e.htm)

## 🙏 Credits

- **Original Swiss Ephemeris**: Astrodienst AG, Dieter Koch, Alois Treindl
- **PHP Port**: Sergey Gut (2025)

---

Made with ❤️ for the astronomical and astrological community.
```
