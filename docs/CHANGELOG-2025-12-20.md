# Changelog — 20 декабря 2025

> Historical development note. The pass counts below came from the test harness available on that date and are not a current compatibility guarantee. See [COMPATIBILITY.md](COMPATIBILITY.md) for the presently enforced status.

## 🎯 Comprehensive Testing & Delta T Fix

### Исправления

#### Delta T (ΔT) — Полное исправление
Исправлена таблица DT_TABLE в `DeltaT.php` для точного соответствия C-коду `swephlib.c`.

**Проблема:**
- Неверные значения для 1957 и 1973 годов
- Значения в таблице были в 100x больше чем нужно

**Решение:**
- Таблица DT_TABLE теперь содержит точные значения в секундах (как в C)
- Удалено деление `/100.0` в методе `besselInterpolation()`
- Обновлена формула `polynomial2005_2050()` до Stephenson 2016

**Результат:**
- Максимальная ошибка: 0.018 arcsec на 140 случайных датах (1800-2100)

---

### Новые тесты

#### test_all_parameters.php — 327 тестов (100% pass)

Комплексный скрипт тестирования всех параметров Swiss Ephemeris:

| # | Секция | Тестов | Результат | Макс. ошибка |
|---|--------|--------|-----------|--------------|
| 1 | Ecliptic Geocentric | 80 | ✓ 100% | — |
| 2 | Equatorial (RA/Dec) | 33 | ✓ 100% | 0.006 arcsec |
| 3 | Heliocentric | 20 | ✓ 100% | 0.551 arcsec |
| 4 | Barycentric | 4 | ✓ 100% | 0.005 arcsec |
| 5 | True Position | 40 | ✓ 100% | 0.005 arcsec |
| 6 | No Nutation | 40 | ✓ 100% | 0.000 arcsec |
| 7 | Speed Calculations | 20 | ✓ 100% | 0.000098 deg/day |
| 8 | Lunar Nodes | 6 | ✓ 100% | — |
| 9 | Asteroids | 12 | ✓ 100% | 0.005 arcsec |
| 10 | House Systems | 24 | ✓ 100% | — |
| 11 | Delta T | 5 | ✓ 100% | — |
| 12 | Sidereal Time | 2 | ✓ 100% | — |
| 13 | Calendar Conversions | 3 | ✓ 100% | — |
| 14 | Combined Flags | 3 | ✓ 100% | — |
| 15 | Edge Cases | 7 | ✓ 100% | — |
| 16 | Radians Output | 2 | ✓ 100% | — |
| 17 | XYZ Coordinates | 3 | ✓ 100% | — |
| 18 | Extended Date Range | 10 | ✓ 100% | 0.009 arcsec |
| 19 | Reverse Calendar | 3 | ✓ 100% | — |
| 20 | Planet Names | 10 | ✓ 100% | — |

**Покрытие:**
- 10 планет (Солнце-Плутон) + Луна
- 6 астероидов (Chiron, Pholus, Ceres, Pallas, Juno, Vesta)
- Лунные узлы (mean/true)
- 8 систем домов (Placidus, Koch, Equal, Whole Sign, etc.)
- Все основные флаги: SEFLG_EQUATORIAL, SEFLG_HELCTR, SEFLG_BARYCTR, SEFLG_TRUEPOS, SEFLG_NONUT, SEFLG_SPEED, SEFLG_RADIANS, SEFLG_XYZ
- Диапазон дат: 1900-2100

---

#### test_comprehensive.php — 802 теста

Расширенный тест с детальной статистикой:
- Множественные даты (10+ дат от 1950 до 2100)
- Все планеты
- Различные комбинации флагов

---

#### test_delta_t.php — 140 случайных дат

Верификация Delta T на случайных датах 1800-2100:
- Сравнение с `swetest64.exe -deltat`
- Максимальная ошибка: 0.018 arcsec

---

### JPL Ephemeris

#### Установка DE440
- Файл `linux_p1550p2650.440` скопирован в `eph/ephe/de440.eph`
- Все 10 тестов JPL проходят

---

### Итоговая статистика

| Тестовый набор | Результат |
|----------------|-----------|
| PHPUnit | **209/209 (100%)** |
| test_all_parameters.php | **327/327 (100%)** |
| **ИТОГО** | **536 тестов (100%)** |

---

### Файлы изменены

- `src/Time/DeltaT.php` — исправлена таблица DT_TABLE
- `scripts/test_all_parameters.php` — новый комплексный тест
- `scripts/test_comprehensive.php` — расширенный тест
- `scripts/test_delta_t.php` — тест Delta T
- `docs/TESTING-SUMMARY.md` — обновлена документация
- `README.md` — обновлена статистика тестов
