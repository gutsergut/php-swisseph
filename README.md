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

## Status

**Production Ready** ✅ - Fully tested and verified against C reference implementation.

Установка
- Локально для скриптовых тестов Composer не нужен: достаточно `php` CLI (>=7.4 для скриптов; PHPUnit требует PHP >=8.1).
- Для PHPUnit: PHP >=8.1 + Composer.

Запуск
- Скриптовые проверки:
  ```powershell
  php .\tests\UtcJdTest.php
  php .\tests\ErrorContractTest.php
  php .\tests\SweCalcSkeletonTest.php
  php .\tests\CoordinatesRoundtripTest.php
  ```
- PHPUnit (в CI или локально при PHP >= 8.1):
  ```powershell
  composer install -n
  vendor\bin\phpunit -c phpunit.xml.dist --colors=always
  ```
- Бенчмарк (наглядно, без строгих метрик):
  ```powershell
  php .\scripts\bench.php
  ```

API-заметки
- `swe_utc_to_jd/swe_jd_to_utc` поддерживают `$serr` и валидацию `gregflag`.
- `swe_calc/_ut`: форма `xx` — `[a, b, r, da, db, dr]` (углы и их скорости; по умолчанию градусы/день). Флаги `SEFLG_RADIANS`, `SEFLG_EQUATORIAL`, `SEFLG_XYZ` меняют единицы/систему координат.
- Сидерика: `swe_set_sid_mode()` устанавливает режим аянамши (Fagan/Bradley, Lahiri и др.), `swe_get_ayanamsa_ex()` возвращает значение.
- Пока `swe_calc/_ut` возвращают `SE_ERR` и `$serr=UNSUPPORTED` (в разработке).

Пример использования сидерики

```php
<?php
use Swisseph\Constants;

// Установить режим Lahiri
swe_set_sid_mode(Constants::SE_SIDM_LAHIRI, 0, 0);

// Получить аянамшу для J2000.0
$jd_tt = 2451545.0;
$daya = null;
$serr = null;
swe_get_ayanamsa_ex($jd_tt, 0, $daya, $serr);
echo "Ayanamsha (Lahiri, J2000.0): " . sprintf("%.6f°", $daya) . PHP_EOL;

// Получить имя режима
echo "Mode name: " . swe_get_ayanamsa_name(Constants::SE_SIDM_LAHIRI) . PHP_EOL;
```

Пример использования домов

```php
<?php
use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\Obliquity;
use Swisseph\DeltaT;
use Swisseph\Houses;
use Swisseph\Math;

$jd_ut = 2460680.5;           // 2025-10-01 00:00 UT
$geolat = 48.8566;            // Париж
$geolon = 2.3522;

// swe_houses (обёртка)
$cusp = $ascmc = [];
HousesFunctions::houses($jd_ut, $geolat, $geolon, 'J', $cusp, $ascmc);
echo 'Asc=' . $ascmc[0] . '  MC=' . $ascmc[1] . PHP_EOL;

// swe_houses_ex2: вернёт также ARMC в ascmc[2], а для Sunshine — dec☉ в ascmc[9]
$cusp2 = $ascmc2 = $cspSpd = $amcSpd = [];
HousesFunctions::housesEx2($jd_ut, 0, $geolat, $geolon, 'I', $cusp2, $ascmc2, $cspSpd, $amcSpd);
echo 'ARMC=' . $ascmc2[2] . '  SunDec=' . $ascmc2[9] . PHP_EOL;

// swe_house_pos: позиция объекта (долгота/широта на эклиптике) в домах системы 'J'
$jd_tt = $jd_ut + DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
$eps_deg = Math::radToDeg(Obliquity::meanObliquityRadFromJdTT($jd_tt));
$armc_deg = Math::radToDeg(Houses::armcFromSidereal($jd_ut, $geolon));
$lon_obj = 123.45;            // пример долготы
$lat_obj = 0.0;               // эклиптическая широта
$pos = HousesFunctions::housePos($armc_deg, $geolat, $eps_deg, 'J', [$lon_obj, $lat_obj]);
echo 'HousePos(J)=' . $pos . PHP_EOL;
```

## Parity-тесты со swetest

Для проверки соответствия C-реализации Swiss Ephemeris доступны скрипты и PHPUnit-тесты.

### Требования
- Скомпилированный `swetest` (Windows: `swetest64.exe` в `с-swisseph\swisseph\windows\programs\`)
- Эфемериды Swiss Ephemeris в папке `с-swisseph\swisseph\ephe\`

### Настройка путей (опционально)

Установите переменные окружения, если пути отличаются от дефолтных:

```powershell
$env:SWETEST_PATH = 'C:\path\to\swetest64.exe'
$env:SWEPH_EPHE_DIR = 'C:\path\to\ephe'
```

Дефолтные пути (Windows):
- `SWETEST_PATH` = `C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\windows\programs\swetest64.exe`
- `SWEPH_EPHE_DIR` = `C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph\ephe`

### Запуск parity-скриптов

```powershell
# Проверка всех систем домов
php .\scripts\parity_all_houses.php

# Проверка конкретной системы (Savard-A)
php .\scripts\parity_j_vs_swetest.php

# Проверка аянамши
php .\scripts\parity_ayanamsha_swetest.php

# Проверка узлов и апсид
php .\scripts\parity_nod_aps_swetest.php
```

### Guarded PHPUnit-тесты

Для запуска паритет-тестов через PHPUnit установите переменную окружения `RUN_SWETEST_PARITY`:

```powershell
$env:RUN_SWETEST_PARITY = '1'
vendor\bin\phpunit -c phpunit.xml.dist --colors=always
```

Без этой переменной паритет-тесты будут пропущены (skip).

Лицензия
- AGPL-3.0-or-later (подробности см. `docs/LICENSE-NOTES.md`).

Дорожная карта
- См. `docs/ROADMAP.md`.
```
