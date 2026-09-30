# PHP Swiss Ephemeris

[![CI](https://github.com/gutsergut/php-swisseph/actions/workflows/ci.yml/badge.svg)](https://github.com/gutsergut/php-swisseph/actions/workflows/ci.yml)
[![License: AGPL-3.0-or-later](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://www.php.net/)

An experimental, dependency-free-at-runtime PHP 8.1+ port of Swiss Ephemeris 2.10.03. It exposes both familiar `swe_*` functions and a typed object-oriented facade.

> **Pre-1.0 status:** this repository is under active correctness work. CI is configured to check the self-contained regression suite, and the full local suite passes with the documented ephemeris fixture set. That still does not establish independent drop-in or bit-for-bit parity with the C library. See [Compatibility status](docs/COMPATIBILITY.md).

## Why this project exists

- Run calendar, house, sidereal and selected planetary calculations without a PHP extension or subprocess.
- Keep a C-compatible function surface while offering a modern `Swisseph\OO` API.
- Make numerical limitations visible and reproducible instead of hiding them behind coverage claims.
- Publish a reproducible implementation and parity corpus that other language ports can reuse.

## Installation

No stable Packagist release exists yet. Install the development branch directly from GitHub:

```bash
composer config repositories.php-swisseph vcs https://github.com/gutsergut/php-swisseph
composer require gutsergut/php-swisseph:dev-main
```

After the first stable tag is published to Packagist, the intended command is:

```bash
composer require gutsergut/php-swisseph:^0.1
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Swisseph\Constants;
use Swisseph\OO\Swisseph;

$swe = new Swisseph('/path/to/ephemeris/files');
$jd = $swe->julianDay(2000, 1, 1, 12.0);
$sun = $swe->planet(Constants::SE_SUN, $jd);

if ($sun->isError()) {
    throw new RuntimeException($sun->error ?? 'Calculation failed');
}

printf("Sun longitude: %.8f°\n", $sun->longitude);

$houses = $swe->houses($jd, 52.52, 13.405, 'P');
printf("Ascendant: %.8f°\n", $houses->ascendant);
```

The procedural API remains available:

```php
$xx = [];
$error = null;
$flags = swe_calc_ut($jd, SE_SUN, SEFLG_SWIEPH | SEFLG_SPEED, $xx, $error);
```

## Ephemeris data

Some calculations work from analytical Moshier/VSOP data shipped in this repository. Swiss Ephemeris `.se1` files, JPL files and star catalogues are not bundled; configure their directory with `swe_set_ephe_path()` or the `Swisseph` constructor.

The active backend and returned flags matter. A successful call does not by itself prove that the requested backend was used. Consumers should retain flags, errors and backend metadata in their own result contract.

## Development

```bash
composer install
composer check       # syntax, PHPStan (with explicit legacy baseline), CI regression suite
composer test:full   # diagnostic compatibility suite; requires external data for all checks
```

Set `SWEPH_EPHE_DIR` to a directory containing licensed ephemeris files when running file-backed tests:

```bash
SWEPH_EPHE_DIR=/opt/swisseph/ephe composer test:full
```

Read [CONTRIBUTING.md](CONTRIBUTING.md) before changing numerical code. A calculation fix needs a reference vector, provenance, tolerance and a regression test.

## Project status and plans

- [Compatibility matrix and known gaps](docs/COMPATIBILITY.md)
- [PHP implementation roadmap](docs/ROADMAP.md)
- [Modern object-oriented API](docs/MODERN_API.md)
- [API reference](docs/API_Reference.md)
- [Release process](docs/RELEASING.md)

## Licensing and provenance

This derivative port is distributed under the **GNU Affero General Public License v3.0 or later**. Swiss Ephemeris is dual-licensed by Astrodienst AG; proprietary distribution may require its professional license. See [LICENSE](LICENSE), [NOTICE](NOTICE) and [license notes](docs/LICENSE-NOTES.md).

The name “Swiss Ephemeris” identifies the upstream project; this repository is an independent community port and is not endorsed by Astrodienst AG.
