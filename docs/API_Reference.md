# Swiss Ephemeris PHP Port - API Reference

[English](#english) | [Русский](#russian)

This document provides a complete reference for the `swe_*` functions implemented in the PHP port of Swiss Ephemeris.
The API is designed to be compatible with the original C API, with minor adjustments for PHP data types (e.g., arrays instead of pointers).

Этот документ содержит полный справочник по функциям `swe_*`, реализованным в PHP-порте Swiss Ephemeris.
API разработан для совместимости с оригинальным C API, с небольшими изменениями для типов данных PHP (например, массивы вместо указателей).

**Official Documentation / Официальная документация:**
*   [Swiss Ephemeris API (HTML)](https://www.astro.com/swisseph/swephprg.htm)
*   [Swiss Ephemeris General Documentation](https://www.astro.com/swisseph/swisseph.htm)

---

## Table of Contents / Оглавление

1.  [Planets & Calculation / Планеты и вычисления](#1-planets--calculation--планеты-и-вычисления)
2.  [Houses & Angles / Дома и углы](#2-houses--angles--дома-и-углы)
3.  [Sidereal & Ayanamsha / Сидерика и Аянамша](#3-sidereal--ayanamsha--сидерика-и-аянамша)
4.  [Nodes & Apsides / Узлы и Апсиды](#4-nodes--apsides--узлы-и-апсиды)
5.  [Rise/Set/Transit / Восход, Заход, Транзит](#5-risesettransit--восход-заход-транзит)
6.  [Crossings & Transits / Пересечения и Транзиты](#6-crossings--transits--пересечения-и-транзиты)
7.  [Time & Conversions / Время и Конверсии](#7-time--conversions--время-и-конверсии)
8.  [Coordinate Transform / Преобразование координат](#8-coordinate-transform--преобразование-координат)
9.  [Orbital Elements / Орбитальные элементы](#9-orbital-elements--орбитальные-элементы)
10. [Stars & Fixed Objects / Звёзды и неподвижные объекты](#10-stars--fixed-objects--звёзды-и-неподвижные-объекты)
11. [Eclipses & Phenomena / Затмения и явления](#11-eclipses--phenomena--затмения-и-явления)
12. [Heliacal Phenomena / Гелиакальные явления](#12-heliacal-phenomena--гелиакальные-явления)
13. [Misc Utilities / Разные утилиты](#13-misc-utilities--разные-утилиты)

---

## 1. Planets & Calculation / Планеты и вычисления

### `swe_calc`
**Signature**: `swe_calc(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int`

**Description (EN)**: Computes the position of a planet, asteroid, lunar node, or apogee for a given Julian Day (TT).
**Описание (RU)**: Вычисляет позицию планеты, астероида, лунного узла или апогея для заданного Юлианского дня (TT - Terrestrial Time).

**Parameters / Параметры**:
*   `$jd_tt` (float): Julian Day number (TT).
*   `$ipl` (int): Body number (e.g., `SE_SUN`, `SE_MOON`, `SE_MERCURY`...).
*   `$iflag` (int): Calculation flags (e.g., `SEFLG_SWIEPH`, `SEFLG_SPEED`, `SEFLG_EQUATORIAL`).
*   `&$xx` (array): Output array (by reference). Contains 6 floats: longitude, latitude, distance, speed in long, speed in lat, speed in dist.
*   `&$serr` (string|null): Output error string (by reference).

**Return / Возврат**:
*   (int): Flags used for calculation (may differ from requested if fallback occurred), or `SE_ERR` on failure.

---

### `swe_calc_ut`
**Signature**: `swe_calc_ut(float $jd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int`

**Description (EN)**: Same as `swe_calc`, but input time is Universal Time (UT).
**Описание (RU)**: То же, что `swe_calc`, но входное время — Всемирное время (UT).

---

### `swe_calc_pctr`
**Signature**: `swe_calc_pctr(float $jd, int $ipl, int $iplctr, int $iflag, array &$xx, ?string &$serr = null): int`

**Description (EN)**: Computes the planetocentric position of a body (viewed from another body).
**Описание (RU)**: Вычисляет планетоцентрическую позицию тела (вид с другого тела).

**Parameters / Параметры**:
*   `$iplctr` (int): Body number of the center/observer.

---

### `swe_pheno`
**Signature**: `swe_pheno(float $jd, int $ipl, int $iflag, array &$attr, ?string &$serr = null): int`

**Description (EN)**: Computes planetary phenomena (phase, magnitude, etc.).
**Описание (RU)**: Вычисляет планетарные явления (фаза, звездная величина и т.д.).

**Parameters / Параметры**:
*   `&$attr` (array): Output array (20 floats): phase angle, phase, elongation, apparent diameter, apparent magnitude, etc.

---

### `swe_pheno_ut`
**Signature**: `swe_pheno_ut(float $jd_ut, int $ipl, int $iflag, array &$attr, ?string &$serr = null): int`

**Description (EN)**: Same as `swe_pheno`, but input time is UT.
**Описание (RU)**: То же, что `swe_pheno`, но входное время — UT.

---

### `swe_get_planet_name`
**Signature**: `swe_get_planet_name(int $ipl): string`

**Description (EN)**: Returns the name of a planet/body.
**Описание (RU)**: Возвращает имя планеты/тела.

---

### `swe_set_ephe_path`
**Signature**: `swe_set_ephe_path(string $path): void`

**Description (EN)**: Sets the directory path for Swiss Ephemeris data files.
**Описание (RU)**: Устанавливает путь к директории с файлами данных Swiss Ephemeris.

---

### `swe_set_topo`
**Signature**: `swe_set_topo(float $lon, float $lat, float $alt): void`

**Description (EN)**: Sets the topocentric observer position.
**Описание (RU)**: Устанавливает топоцентрическую позицию наблюдателя.

**Parameters / Параметры**:
*   `$lon` (float): Longitude (degrees, East positive).
*   `$lat` (float): Latitude (degrees, North positive).
*   `$alt` (float): Altitude above sea level (meters).

---

### `swe_set_jpl_file`
**Signature**: `swe_set_jpl_file(string $fname): void`

**Description (EN)**: Sets the name of the JPL ephemeris file.
**Описание (RU)**: Устанавливает имя файла эфемерид JPL.

---

### `swe_get_current_file_data`
**Signature**: `swe_get_current_file_data(int $ifno, float &$tfstart, float &$tfend, int &$denum): ?string`

**Description (EN)**: Returns metadata about the currently loaded ephemeris file.
**Описание (RU)**: Возвращает метаданные о текущем загруженном файле эфемерид.

**Parameters / Параметры**:
*   `$ifno` (int): File type (0=planet, 1=moon, 2=asteroid, 4=star).
*   `&$tfstart`, `&$tfend` (float): Output start/end JD of the file.
*   `&$denum` (int): Output DE number (e.g. 431).

**Return / Возврат**:
*   (string|null): Filename or null if no file loaded.

---

### `swe_set_interpolate_nut`
**Signature**: `swe_set_interpolate_nut(bool $do_interpolate): void`

**Description (EN)**: Enables/disables nutation interpolation (performance optimization).
**Описание (RU)**: Включает/отключает интерполяцию нутации (оптимизация производительности).

---

### `swe_set_astro_models`
**Signature**: `swe_set_astro_models(string $samod, int $iflag): void`

**Description (EN)**: Sets astronomical models (Delta T, Precession, Nutation, etc.).
**Описание (RU)**: Устанавливает астрономические модели (Delta T, Прецессия, Нутация и др.).

**Parameters / Параметры**:
*   `$samod` (string): Model configuration string (e.g. "SE2.06" or "3,9,9,4,3,0,0,4").

---

### `swe_get_astro_models`
**Signature**: `swe_get_astro_models(?string &$samod, ?string &$sdet, int $iflag): void`

**Description (EN)**: Gets current astronomical models configuration.
**Описание (RU)**: Получает текущую конфигурацию астрономических моделей.

**Parameters / Параметры**:
*   `&$sdet` (string): Output detailed description of models.

---

### `swe_version`
**Signature**: `swe_version(): string`

**Description (EN)**: Returns the version of the Swiss Ephemeris library.
**Описание (RU)**: Возвращает версию библиотеки Swiss Ephemeris.

---

### `swe_close`
**Signature**: `swe_close(): void`

**Description (EN)**: Closes the library and frees memory (no-op in PHP port, kept for compatibility).
**Описание (RU)**: Закрывает библиотеку и освобождает память (в PHP-порте ничего не делает, оставлена для совместимости).

## 2. Houses & Angles / Дома и углы

### `swe_houses`
**Signature**: `swe_houses(float $jd_ut, float $geolat, float $geolon, string $hsys, array &$cusp, array &$ascmc): int`

**Description (EN)**: Calculates house cusps and Ascendant/MC.
**Описание (RU)**: Вычисляет куспиды домов и Асцендент/MC.

**Parameters / Параметры**:
*   `$jd_ut` (float): Julian Day (UT).
*   `$geolat` (float): Geographic latitude.
*   `$geolon` (float): Geographic longitude.
*   `$hsys` (string): House system code (e.g., 'P' for Placidus, 'K' for Koch).
*   `&$cusp` (array): Output cusps (indices 1-12).
*   `&$ascmc` (array): Output angles (indices 0-9: Asc, MC, ARMC, Vertex, etc.).

---

### `swe_houses_ex`
**Signature**: `swe_houses_ex(float $jd_ut, int $iflag, float $geolat, float $geolon, string $hsys, array &$cusp, array &$ascmc): int`

**Description (EN)**: Extended version of `swe_houses` with flags.
**Описание (RU)**: Расширенная версия `swe_houses` с флагами.

---

### `swe_houses_ex2`
**Signature**: `swe_houses_ex2(float $jd_ut, int $iflag, float $geolat, float $geolon, string $hsys, array &$cusp, array &$ascmc, ?array &$cusp_speed = null, ?array &$ascmc_speed = null, ?string &$serr = null): int`

**Description (EN)**: Extended version of `swe_houses` with speeds.
**Описание (RU)**: Расширенная версия `swe_houses` со скоростями.

---

### `swe_houses_armc`
**Signature**: `swe_houses_armc(float $armc, float $geolat, float $eps, string $hsys, array &$cusp, array &$ascmc): int`

**Description (EN)**: Calculates houses from ARMC (Sidereal Time * 15) and Obliquity.
**Описание (RU)**: Вычисляет дома по ARMC (Звёздное время * 15) и наклону эклиптики.

---

### `swe_house_pos`
**Signature**: `swe_house_pos(float $armc, float $geolat, float $eps, string $hsys, array $xpin, ?string &$serr = null): float`

**Description (EN)**: Calculates the house position of a body (1.0-12.999).
**Описание (RU)**: Вычисляет позицию тела в домах (1.0-12.999).

**Parameters / Параметры**:
*   `$xpin` (array): Body position `[longitude, latitude]`.

---

### `swe_house_name`
**Signature**: `swe_house_name(string $hsys): string`

**Description (EN)**: Returns the full name of a house system.
**Описание (RU)**: Возвращает полное название системы домов.

---

### `swe_gauquelin_sector`
**Signature**: `swe_gauquelin_sector(float $jd_ut, int $ipl, string $starname, int $iflag, int $imeth, array $geopos, float $atpress, float $attemp, float &$dgsect, ?string &$serr = null): int`

**Description (EN)**: Computes Gauquelin sector position (1-36).
**Описание (RU)**: Вычисляет позицию в секторах Гоклена (1-36).

## 3. Sidereal & Ayanamsha / Сидерика и Аянамша

### `swe_set_sid_mode`
**Signature**: `swe_set_sid_mode(int $sid_mode, float $t0, float $ayan_t0): void`

**Description (EN)**: Sets the sidereal mode (Ayanamsha).
**Описание (RU)**: Устанавливает сидерический режим (Аянамшу).

**Parameters / Параметры**:
*   `$sid_mode` (int): Ayanamsha code (e.g., `SE_SIDM_LAHIRI`, `SE_SIDM_FAGAN_BRADLEY`).
*   `$t0`, `$ayan_t0`: Custom epoch and value (if mode is user-defined).

---

### `swe_get_ayanamsa`
**Signature**: `swe_get_ayanamsa(float $jd_tt): float`

**Description (EN)**: Returns the Ayanamsha value for a given JD (TT).
**Описание (RU)**: Возвращает значение Аянамши для заданного JD (TT).

---

### `swe_get_ayanamsa_ut`
**Signature**: `swe_get_ayanamsa_ut(float $jd_ut): float`

**Description (EN)**: Returns the Ayanamsha value for a given JD (UT).
**Описание (RU)**: Возвращает значение Аянамши для заданного JD (UT).

---

### `swe_sidtime`
**Signature**: `swe_sidtime(float $jd_ut): float`

**Description (EN)**: Calculates Greenwich Mean Sidereal Time (GMST).
**Описание (RU)**: Вычисляет Среднее Гринвичское Звёздное Время (GMST).

---

### `swe_time_equ`
**Signature**: `swe_time_equ(float $jd_ut, ?float &$E = null, ?string &$serr = null): int`

**Description (EN)**: Calculates the Equation of Time.
**Описание (RU)**: Вычисляет Уравнение Времени.

---

### `swe_lmt_to_lat`
**Signature**: `swe_lmt_to_lat(float $jd_lmt, float $geolon, float &$jd_lat, ?string &$serr = null): int`

**Description (EN)**: Converts Local Mean Time to Local Apparent Time.
**Описание (RU)**: Конвертирует Местное Среднее Время в Местное Истинное Время.

---

### `swe_lat_to_lmt`
**Signature**: `swe_lat_to_lmt(float $jd_lat, float $geolon, float &$jd_lmt, ?string &$serr = null): int`

**Description (EN)**: Converts Local Apparent Time to Local Mean Time.
**Описание (RU)**: Конвертирует Местное Истинное Время в Местное Среднее Время.

## 4. Nodes & Apsides / Узлы и Апсиды

### `swe_nod_aps`
**Signature**: `swe_nod_aps(float $jd_tt, int $ipl, int $iflag, int $method, array &$xn, array &$xa, array &$xp, ?string &$serr = null): int`

**Description (EN)**: Computes planetary nodes and apsides.
**Описание (RU)**: Вычисляет планетарные узлы и апсиды.

**Parameters / Параметры**:
*   `$ipl` (int): Planet number.
*   `$method` (int): Method (0=mean, 1=true, etc.).
*   `&$xn` (array): Output nodes (ascending/descending).
*   `&$xa` (array): Output apsides (perihelion/aphelion).

---

### `swe_nod_aps_ut`
**Signature**: `swe_nod_aps_ut(float $jd_ut, int $ipl, int $iflag, int $method, array &$xn, array &$xa, array &$xp, ?string &$serr = null): int`

**Description (EN)**: Same as `swe_nod_aps`, but input time is UT.
**Описание (RU)**: То же, что `swe_nod_aps`, но входное время — UT.

## 5. Rise/Set/Transit / Восход, Заход, Транзит

### `swe_rise_trans`
**Signature**: `swe_rise_trans(float $jd_ut, int $ipl, string $starname, int $epheflag, int $rsmi, array $geopos, float $atpress, float $attemp, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Computes rise, set, and transit times.
**Описание (RU)**: Вычисляет время восхода, захода и транзита.

**Parameters / Параметры**:
*   `$rsmi` (int): Event type (e.g., `SE_CALC_RISE`, `SE_CALC_SET`).
*   `$geopos` (array): `[longitude, latitude, height]`.
*   `&$tret` (float): Output Julian Day of the event.

---

### `swe_rise_trans_true_hor`
**Signature**: `swe_rise_trans_true_hor(float $jd_ut, int $ipl, string $starname, int $epheflag, int $rsmi, array $geopos, float $atpress, float $attemp, float $horhgt, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Same as `swe_rise_trans`, but allows specifying horizon height.
**Описание (RU)**: То же, что `swe_rise_trans`, но позволяет указать высоту горизонта.

## 6. Crossings & Transits / Пересечения и Транзиты

### `swe_solcross`
**Signature**: `swe_solcross(float $x2cross, float $jd_et, int $flag, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Finds when the Sun crosses a specific longitude (ET).
**Описание (RU)**: Находит время пересечения Солнцем заданной долготы (ET).

---

### `swe_solcross_ut`
**Signature**: `swe_solcross_ut(float $x2cross, float $jd_ut, int $flag, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Finds when the Sun crosses a specific longitude (UT).
**Описание (RU)**: Находит время пересечения Солнцем заданной долготы (UT).

---

### `swe_mooncross`
**Signature**: `swe_mooncross(float $x2cross, float $jd_et, int $flag, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Finds when the Moon crosses a specific longitude (ET).
**Описание (RU)**: Находит время пересечения Луной заданной долготы (ET).

---

### `swe_mooncross_ut`
**Signature**: `swe_mooncross_ut(float $x2cross, float $jd_ut, int $flag, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Finds when the Moon crosses a specific longitude (UT).
**Описание (RU)**: Находит время пересечения Луной заданной долготы (UT).

---

### `swe_mooncross_node`
**Signature**: `swe_mooncross_node(float $jd_et, int $flag, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Finds when the Moon crosses a node (latitude 0).
**Описание (RU)**: Находит время пересечения Луной узла (широта 0).

---

### `swe_mooncross_node_ut`
**Signature**: `swe_mooncross_node_ut(float $jd_ut, int $flag, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Finds when the Moon crosses a node (UT).
**Описание (RU)**: Находит время пересечения Луной узла (UT).

---

### `swe_helio_cross`
**Signature**: `swe_helio_cross(int $ipl, float $x2cross, float $jd_et, int $iflag, int $dir, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Finds when a planet crosses a heliocentric longitude.
**Описание (RU)**: Находит время пересечения планетой гелиоцентрической долготы.

---

### `swe_helio_cross_ut`
**Signature**: `swe_helio_cross_ut(int $ipl, float $x2cross, float $jd_ut, int $iflag, int $dir, float &$tret, ?string &$serr = null): int`

**Description (EN)**: Finds when a planet crosses a heliocentric longitude (UT).
**Описание (RU)**: Находит время пересечения планетой гелиоцентрической долготы (UT).

## 7. Time & Conversions / Время и Конверсии

### `swe_julday`
**Signature**: `swe_julday(int $year, int $month, int $day, float $hour, int $gregflag): float`

**Description (EN)**: Calculates Julian Day from calendar date.
**Описание (RU)**: Вычисляет Юлианский день по календарной дате.

---

### `swe_revjul`
**Signature**: `swe_revjul(float $jd, int $gregflag): array`

**Description (EN)**: Calculates calendar date from Julian Day.
**Описание (RU)**: Вычисляет календарную дату по Юлианскому дню.

**Return / Возврат**:
*   (array): `['y' => year, 'm' => month, 'd' => day, 'ut' => hour]`.

---

### `swe_utc_to_jd`
**Signature**: `swe_utc_to_jd(int $year, int $month, int $day, int $hour, int $min, float $sec, int $gregflag, ?array &$dret, ?string &$serr = null): int`

**Description (EN)**: Converts UTC date/time to Julian Day (ET and UT).
**Описание (RU)**: Конвертирует дату/время UTC в Юлианский день (ET и UT).

---

### `swe_jd_to_utc`
**Signature**: `swe_jd_to_utc(float $jd_ut, int $gregflag, array &$dret, ?string &$serr = null): int`

**Description (EN)**: Converts Julian Day (UT) to UTC date/time.
**Описание (RU)**: Конвертирует Юлианский день (UT) в дату/время UTC.

---

### `swe_jdet_to_utc`
**Signature**: `swe_jdet_to_utc(float $jd_et, int $gregflag, array &$dret, ?string &$serr = null): int`

**Description (EN)**: Converts Julian Day (ET) to UTC date/time.
**Описание (RU)**: Конвертирует Юлианский день (ET) в дату/время UTC.

---

### `swe_utc_time_zone`
**Signature**: `swe_utc_time_zone(int $year, int $month, int $day, int $hour, int $min, float $sec, float $timezone, array &$dret): void`

**Description (EN)**: Converts between UTC and local time with timezone offset.
**Описание (RU)**: Конвертирует между UTC и местным временем с учетом часового пояса.

---

### `swe_deltat`
**Signature**: `swe_deltat(float $jd_ut): float`

**Description (EN)**: Returns Delta T (TT - UT) in days.
**Описание (RU)**: Возвращает Delta T (TT - UT) в днях.

---

### `swe_deltat_ex`
**Signature**: `swe_deltat_ex(float $jd_ut, int $iflag, ?string &$serr = null): float`

**Description (EN)**: Returns Delta T with flags.
**Описание (RU)**: Возвращает Delta T с флагами.

---

### `swe_set_tid_acc`
**Signature**: `swe_set_tid_acc(float $t_acc): void`

**Description (EN)**: Sets tidal acceleration for Delta T calculation.
**Описание (RU)**: Устанавливает приливное ускорение для расчета Delta T.

---

### `swe_get_tid_acc`
**Signature**: `swe_get_tid_acc(): float`

**Description (EN)**: Returns current tidal acceleration.
**Описание (RU)**: Возвращает текущее приливное ускорение.

## 8. Coordinate Transform / Преобразование координат

### `swe_cotrans`
**Signature**: `swe_cotrans(array $xpo, array &$xpn, float $eps): void`

**Description (EN)**: Coordinate transformation (ecliptic <-> equatorial).
**Описание (RU)**: Преобразование координат (эклиптические <-> экваториальные).

**Parameters / Параметры**:
*   `$xpo` (array): Input coordinates `[long, lat, dist]`.
*   `&$xpn` (array): Output coordinates.
*   `$eps` (float): Obliquity (must be negative for ecl->equ).

---

### `swe_cotrans_sp`
**Signature**: `swe_cotrans_sp(array $xpo, array &$xpn, float $eps): void`

**Description (EN)**: Coordinate transformation with speed.
**Описание (RU)**: Преобразование координат со скоростью.

---

### `swe_azalt`
**Signature**: `swe_azalt(float $jd_ut, int $calc_flag, array $geopos, float $atpress, float $attemp, array $xin, array &$xaz, ?string &$serr = null): int`

**Description (EN)**: Computes Azimuth and Altitude from Ecliptic/Equatorial coordinates.
**Описание (RU)**: Вычисляет Азимут и Высоту из Эклиптических/Экваториальных координат.

**Parameters / Параметры**:
*   `$xin` (array): Input coordinates `[long, lat, dist]`.
*   `&$xaz` (array): Output `[azimuth, true_altitude, apparent_altitude]`.

---

### `swe_azalt_rev`
**Signature**: `swe_azalt_rev(float $jd_ut, int $calc_flag, array $geopos, array $xin, array &$xout, ?string &$serr = null): int`

**Description (EN)**: Computes Ecliptic/Equatorial coordinates from Azimuth/Altitude.
**Описание (RU)**: Вычисляет Эклиптические/Экваториальные координаты из Азимута/Высоты.

## 9. Orbital Elements / Орбитальные элементы

### `swe_get_orbital_elements`
**Signature**: `swe_get_orbital_elements(float $jd_et, int $ipl, int $iflag, array &$dret, ?string &$serr = null): int`

**Description (EN)**: Computes osculating orbital elements.
**Описание (RU)**: Вычисляет оскулирующие орбитальные элементы.

---

### `swe_orbit_max_min_true_distance`
**Signature**: `swe_orbit_max_min_true_distance(float $jd_et, int $ipl, int $iflag, array &$dret, ?string &$serr = null): int`

**Description (EN)**: Computes max/min true distance of a body.
**Описание (RU)**: Вычисляет макс/мин истинное расстояние тела.

## 10. Stars & Fixed Objects / Звёзды и неподвижные объекты

### `swe_fixstar2`
**Signature**: `swe_fixstar2(string $star, float $jd_et, int $iflag, array &$xx, ?string &$serr = null): int`

**Description (EN)**: Computes position of a fixed star (ET).
**Описание (RU)**: Вычисляет позицию неподвижной звезды (ET).

---

### `swe_fixstar2_ut`
**Signature**: `swe_fixstar2_ut(string $star, float $jd_ut, int $iflag, array &$xx, ?string &$serr = null): int`

**Description (EN)**: Computes position of a fixed star (UT).
**Описание (RU)**: Вычисляет позицию неподвижной звезды (UT).

---

### `swe_fixstar2_mag`
**Signature**: `swe_fixstar2_mag(string $star, float &$mag, ?string &$serr = null): int`

**Description (EN)**: Returns magnitude of a fixed star.
**Описание (RU)**: Возвращает звездную величину неподвижной звезды.

## 11. Eclipses & Phenomena / Затмения и явления

### `swe_sol_eclipse_where`
**Signature**: `swe_sol_eclipse_where(float $jd_ut, int $iflag, array &$geopos, array &$attr, ?string &$serr = null): int`

**Description (EN)**: Computes geographic location of solar eclipse maximum.
**Описание (RU)**: Вычисляет географическое положение максимума солнечного затмения.

---

### `swe_sol_eclipse_how`
**Signature**: `swe_sol_eclipse_how(float $jd_ut, int $iflag, array $geopos, array &$attr, ?string &$serr = null): int`

**Description (EN)**: Computes attributes of a solar eclipse at a given location.
**Описание (RU)**: Вычисляет атрибуты солнечного затмения в заданном месте.

---

### `swe_sol_eclipse_when_loc`
**Signature**: `swe_sol_eclipse_when_loc(float $jd_start, int $iflag, array $geopos, array &$tret, array &$attr, int $backward, ?string &$serr = null): int`

**Description (EN)**: Finds next solar eclipse for a given location.
**Описание (RU)**: Находит следующее солнечное затмение для заданного места.

---

### `swe_sol_eclipse_when_glob`
**Signature**: `swe_sol_eclipse_when_glob(float $jd_start, int $iflag, int $ifltype, array &$tret, int $backward, ?string &$serr = null): int`

**Description (EN)**: Finds next solar eclipse globally.
**Описание (RU)**: Находит следующее солнечное затмение глобально.

---

### `swe_lun_eclipse_how`
**Signature**: `swe_lun_eclipse_how(float $jd_ut, int $iflag, array $geopos, array &$attr, ?string &$serr = null): int`

**Description (EN)**: Computes attributes of a lunar eclipse.
**Описание (RU)**: Вычисляет атрибуты лунного затмения.

---

### `swe_lun_eclipse_when`
**Signature**: `swe_lun_eclipse_when(float $jd_start, int $iflag, int $ifltype, array &$tret, int $backward, ?string &$serr = null): int`

**Description (EN)**: Finds next lunar eclipse.
**Описание (RU)**: Находит следующее лунное затмение.

## 12. Heliacal Phenomena / Гелиакальные явления

### `swe_heliacal_ut`
**Signature**: `swe_heliacal_ut(float $jd_ut, array $geopos, array $datm, array $dobs, string $objectname, int $event_type, int $helflag, array &$dret, ?string &$serr = null): int`

**Description (EN)**: Computes heliacal events (rise, set, etc.).
**Описание (RU)**: Вычисляет гелиакальные события (восход, заход и т.д.).

---

### `swe_heliacal_pheno_ut`
**Signature**: `swe_heliacal_pheno_ut(float $jd_ut, array $geopos, array $datm, array $dobs, string $objectname, int $helflag, array &$dret, ?string &$serr = null): int`

**Description (EN)**: Computes heliacal phenomena details.
**Описание (RU)**: Вычисляет детали гелиакальных явлений.

---

### `swe_vis_limit_mag`
**Signature**: `swe_vis_limit_mag(float $jd_ut, array $geopos, array $datm, array $dobs, string $objectname, int $helflag, array &$dret, ?string &$serr = null): int`

**Description (EN)**: Computes limiting magnitude for visibility.
**Описание (RU)**: Вычисляет предельную звездную величину для видимости.

## 13. Misc Utilities / Разные утилиты

### `swe_deg_norm`
**Signature**: `swe_deg_norm(float $x): float`

**Description (EN)**: Normalizes angle to 0..360.
**Описание (RU)**: Нормализует угол в диапазон 0..360.

---

### `swe_rad_norm`
**Signature**: `swe_rad_norm(float $x): float`

**Description (EN)**: Normalizes angle to 0..2PI.
**Описание (RU)**: Нормализует угол в диапазон 0..2PI.

---

### `swe_split_deg`
**Signature**: `swe_split_deg(float $ddeg, int $roundflag, int &$ideg, int &$imin, float &$dsec, int &$isgn): void`

**Description (EN)**: Splits decimal degrees into deg/min/sec.
**Описание (RU)**: Разделяет десятичные градусы на градусы/минуты/секунды.

---

### `swe_day_of_week`
**Signature**: `swe_day_of_week(float $jd): int`

**Description (EN)**: Returns day of week (0=Monday, ... 6=Sunday).
**Описание (RU)**: Возвращает день недели (0=Понедельник, ... 6=Воскресенье).
