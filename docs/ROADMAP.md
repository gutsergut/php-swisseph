# Roadmap: Swiss Ephemeris PHP Port

Обновлено: 2025-10-27

## Реализовано (48 функций)

### Базовые функции времени/календаря
- ✅ `swe_julday`, `swe_revjul` — преобразования JD ↔ календарь
- ✅ `swe_utc_to_jd`, `swe_jd_to_utc` — UTC ↔ JD с валидацией
- ✅ `swe_deltat`, `swe_deltat_ex` — ΔT (аппроксимации Espenak/Meeus)
- ✅ `swe_sidtime` — GMST (звёздное время)
- ✅ `swe_date_conversion` — преобразование календарной даты в JD с валидацией
- ✅ `swe_day_of_week` — день недели по JD (Monday=0, ..., Sunday=6)
- ✅ `swe_time_equ` — equation of time (разница между средним и истинным солнечным временем)
- ✅ `swe_lmt_to_lat` — Local Mean Time → Local Apparent Time
- ✅ `swe_lat_to_lmt` — Local Apparent Time → Local Mean Time

### Сидерика и аянáмша
- ✅ `swe_set_sid_mode` — установка режима аянамши (47 режимов `SE_SIDM_*`)
- ✅ `swe_get_ayanamsa_ex`, `swe_get_ayanamsa_ex_ut` — аянáмша с флагами
- ✅ `swe_get_ayanamsa`, `swe_get_ayanamsa_ut` — упрощённые обёртки
- ✅ `swe_get_ayanamsa_name` — имена режимов
- ✅ `swe_time_equ` — equation of time (дни)

### Планетарные расчёты
- ✅ `swe_calc`, `swe_calc_ut` — геоцентрические координаты планет (Sun, Moon, Mercury-Pluto)
  - Поддержка: `SEFLG_RADIANS`, `SEFLG_EQUATORIAL`, `SEFLG_XYZ`, `SEFLG_SPEED`
  - Приблизительные формулы (Meeus); без нутации/аберрации
  - Скорости через центральную разность ±0.5 сут

### Системы домов
- ✅ `swe_houses` — 20+ систем домов (Placidus, Koch, Equal, Whole Sign, Gauquelin, APC, Sunshine, Savard-A и др.)
- ✅ `swe_house_pos` — позиция объекта в доме для заданной системы
- ✅ `swe_house_name` — имя системы домов

### Горизонтальные преобразования и рефракция
- ✅ `swe_azalt`, `swe_azalt_rev` — экваториальные/эклиптические ↔ горизонтальные (азимут/высота)
- ✅ `swe_refrac` — атмосферная рефракция (Saemundsson; TRUE_TO_APP/APP_TO_TRUE)
- ✅ `swe_refrac_extended` — расширенная рефракция с lapse rate (Newton iteration, Sinclair formula, dip of horizon)

### Координатные преобразования
- ✅ `swe_cotrans`, `swe_cotrans_sp` — ортогональная X-ротация для позиций и скоростей

### Rise/Set/Transit
- ✅ `swe_rise_trans`, `swe_rise_trans_true_hor` — восход/заход/транзит (Sun, Moon)
  - Грубый скан + бисекция (субсекундная точность JD)
  - Топоцентрический параллакс для Луны
  - Полярные гварды (NOT_FOUND для полярного дня/ночи)

### Узлы и апсиды
- ✅ `swe_nod_aps`, `swe_nod_aps_ut` — **базовая реализация** mean nodes/apsides
  - Поддержка: Sun, Moon (упрощённо), Mercury-Neptune, Earth
  - Таблицы VSOP87 для орбитальных элементов
  - Опция `SE_NODBIT_FOPOINT` (фокус вместо афелия)
  - Osculating nodes/apsides — не реализованы

### Орбитальные элементы
- ✅ `swe_get_orbital_elements` — полная реализация кеплеровских элементов (a, e, i, Ω, ω, ϖ, M, ν, E, L, periods)
- ✅ `swe_orbit_max_min_true_distance` — расстояния min/max/true между планетой и Землей/Солнцем
  - Newton iteration для поиска экстремумов
  - Поддержка geocentric и heliocentric режимов

### Планетарные явления
- ✅ `swe_pheno`, `swe_pheno_ut` — **полная реализация** фаза, блеск, угловой диаметр планет
  - Формулы Mallama 2018 для всех планет (Mercury, Venus, Mars, Jupiter, Saturn, Uranus, Neptune)
  - Формулы Allen/Vreijs для Луны
  - Сатурн с учётом колец (Meeus)
  - Горизонтальный параллакс для Луны

### Утилиты
- ✅ `swe_get_planet_name` — имена тел по константе `ipl`
- ✅ `swe_version` — версия библиотеки
- ✅ `swe_close` — no-op (для совместимости)
- ✅ `swe_set_ephe_path`, `swe_set_jpl_file`, `swe_set_topo`, `swe_set_tid_acc` — настройки (заглушки)
- ✅ `swe_degnorm` — нормализация градусов [0, 360)
- ✅ `swe_radnorm` — нормализация радиан [0, 2π)
- ✅ `swe_deg_midp` — средняя точка между углами (градусы)
- ✅ `swe_rad_midp` — средняя точка между углами (радианы)
- ✅ `swe_split_deg` — разбор градусов на °'", знак + флаги округления/зодиакального режима

## Не реализовано (43+ функций из swephexp.h)

### Высокий приоритет
- ⬜ **Точные режимы аянамши**
  - "True" режимы (True Citra, True Revati и др.) — требуют `swe_fixstar`
  - Опции `SE_SIDBIT_*` (ECL_T0, SSY_PLANE, PREC_ORIG, ECL_DATE, NO_PREC_OFFSET, USER_UT)

- ⬜ **Полная реализация узлов/апсид**
  - Координатные преобразования (mean ecliptic of date → J2000 → ecliptic of date с нутацией)
  - Osculating nodes/apsides (численное интегрирование орбит)

- ⬜ **Расширение Rise/Set/Transit**
  - Поддержка флагов диска (центр/нижняя кромка)
  - Twilight-биты (гражданские/астрономические сумерки)
  - Геоцентр/топоцентр

### Средний приоритет
- ⬜ `swe_fixstar`, `swe_fixstar_ut`, `swe_fixstar_mag` — звёздные каталоги
- ⬜ `swe_gauquelin_sector` — сектор Gauquelin для планеты
- ✅ `swe_refrac_extended` — расширенная рефракция с lapse rate (Newton iteration, Sinclair formula, dip calculation)
- ✅ `swe_get_orbital_elements` — орбитальные элементы (полностью реализовано)
- ✅ `swe_orbit_max_min_true_distance` — экстремумы расстояний (полностью реализовано)

### Низкий приоритет (специализированные)
- ⬜ Затмения и покрытия (10+ функций: `swe_sol_eclipse_*`, `swe_lun_eclipse_*`, `swe_lun_occult_*`)
- ⬜ Гелиоцентрические crossing: `swe_helio_cross*`, `swe_solcross*`, `swe_mooncross*`
- ⬜ Heliacal events: `swe_heliacal_*`, `swe_vis_limit_mag`, `swe_topo_arcus_visionis`
- ⬜ Astronomical models: `swe_set_astro_models`, `swe_get_astro_models`
- ⬜ Settings: `swe_set_interpolate_nut`, `swe_set_lapse_rate`, `swe_set_delta_t_userdef`
- ⬜ Info: `swe_get_library_path`, `swe_get_current_file_data`, `swe_get_tid_acc`

## Заметки по переносу C → PHP

### Архитектурные решения
1. **Strategy Pattern для систем домов**: каждая система — отдельный класс в `src/Domain/Houses/Systems/*`
2. **Централизованный Registry**: маппинг код → стратегия в `src/Domain/Houses/Registry.php`
3. **Тонкие фасады**: глобальные функции `swe_*` в `src/functions.php` делегируют в статические методы классов
4. **Координатные утилиты**: отдельные классы `Math`, `Coordinates`, `Horizontal`, `Obliquity`, `DeltaT`

### Ключевые отличия от C-кода
- **Единицы**: внутри классов — радианы, на выходе фасадов — градусы (как в SWE)
- **Ошибки**: вместо кодов возврата — `SE_OK`/`SE_ERR` + `$serr` (reference parameter)
- **Массивы**: вместо указателей — pass-by-reference (`&$cusp`, `&$ascmc`, `&$xx`)
- **Скорости**: для домов — заглушки (массивы нулей); для планет — центральная разность

### Типичные паттерны переноса
```php
// C-функция:
int swe_houses(double jd_ut, double geolat, double geolon, int hsys, double *cusp, double *ascmc);

// PHP-обёртка:
function swe_houses(float $jd_ut, float $geolat, float $geolon, string $hsys, array &$cusp, array &$ascmc): int

// Делегирование в класс:
return HousesFunctions::houses($jd_ut, $geolat, $geolon, $hsys, $cusp, $ascmc);
```

### Тестирование
- **Unit-тесты**: PHPUnit для функциональности (105 tests, 798 assertions)
- **Parity-тесты**: сравнение с `swetest64.exe` (guarded: `RUN_SWETEST_PARITY=1`)
- **Smoke-тесты**: проверка разумности значений для планет (Moon, Sun, Mercury-Pluto)
- **Скриптовые harness**: `scripts/parity_*.php` для массовой проверки

### Известные ограничения
- **Точность**: приблизительные формулы (Meeus) вместо полных VSOP87/JPL эфемерид
- **Полярные широты**: Placidus/Koch могут возвращать ошибку (SE_ERR)
- **Нутация/аберрация**: пока не реализованы
- **Прецессия**: базовая (IAU 1976/2000/2006, Newcomb, Bretagnon)

## Следующие шаги (приоритет)

1. **swe_fixstar**: полная реализация координатных преобразований (proper motion, parallax, precession, nutation, aberration) - базовая версия готова
2. **Сидерика**: довести "True" режимы аянамши (теперь возможно с `swe_fixstar`)
3. **Сидерика**: применение опций `SE_SIDBIT_*` в расчётах
4. **Узлы/апсиды**: полная реализация координатных преобразований
5. **Rise/Set/Transit**: расширенная поддержка флагов и twilight
6. **Тесты**: больше parity-тестов со swetest для всех функций
7. **Документация**: примеры использования, таблица точности, FAQ

## Технический долг

- Линт/стиль: PHP CS Fixer/PHPCS + composer script + CI шаг
- Документация: «Как добавить систему домов», детальные примеры API
- CI: расширить матрицу тестирования (PHP 8.1–8.4)
- Performance: профилирование, кэширование вычислений
