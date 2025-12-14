<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\DeltaT;
use Swisseph\ErrorCodes;
use Swisseph\Output;
use Swisseph\Swe\Planets\EphemerisStrategyFactory;
use Swisseph\Swe\Planets\FictitiousPlanets;
use Swisseph\VectorMath;
use Swisseph\Coordinates;
use Swisseph\Bias;
use Swisseph\Precession;
use Swisseph\SwephFile\SwedState;
use Swisseph\SwephFile\SwephUtils;
use Swisseph\Sidereal;
use Swisseph\Swe\Functions\SiderealFunctions;

/**
 * Тонкий фасад: валидация аргументов + делегирование стратегиям.
 * Полная физика (прецессия, нутация, световые поправки) внутри стратегий.
 */
final class PlanetsFunctions
{
    /**
     * Delegates to appropriate ephemeris strategy.
     * Maintains C API contract: returns iflag (>=0) или SE_ERR (<0).
     */
    public static function calc(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $xx = Output::emptyForFlags($iflag);

        // Диапазон поддерживаемых планет (расширен для Node, Apogee, Chiron, main belt asteroids,
        // numbered asteroids, planetary moons и фиктивных планет)
        // SE_SUN..SE_PLUTO (0-9), SE_MEAN_NODE (10), SE_TRUE_NODE (11),
        // SE_MEAN_APOG (12), SE_OSCU_APOG (13), SE_EARTH (14),
        // SE_CHIRON..SE_VESTA (15-20) - Main belt asteroids
        // SE_FICT_OFFSET..SE_FICT_MAX (40-999) - Uranian/fictitious bodies
        // SE_PLMOON_OFFSET + n (9001-9999) - Planetary moons (Io=9501, Titan=9606, etc.)
        // SE_AST_OFFSET + n (10001+) - Numbered asteroids (Eros=10433, Ceres=10001, etc.)
        $validRange = ($ipl >= Constants::SE_SUN && $ipl <= Constants::SE_PLUTO)
            || $ipl === Constants::SE_MEAN_NODE
            || $ipl === Constants::SE_TRUE_NODE
            || $ipl === Constants::SE_MEAN_APOG
            || $ipl === Constants::SE_OSCU_APOG
            || $ipl === Constants::SE_EARTH
            || ($ipl >= Constants::SE_CHIRON && $ipl <= Constants::SE_VESTA)
            || $ipl === Constants::SE_INTP_APOG
            || $ipl === Constants::SE_INTP_PERG
            || ($ipl > Constants::SE_PLMOON_OFFSET && $ipl < Constants::SE_AST_OFFSET) // Planetary moons
            || $ipl > Constants::SE_AST_OFFSET  // Numbered asteroids: SE_AST_OFFSET + asteroid_number
            || FictitiousPlanets::isFictitious($ipl);

        if (!$validRange) {
            $serr = ErrorCodes::compose(ErrorCodes::INVALID_ARG, "ipl=$ipl out of supported range");
            return Constants::SE_ERR;
        }

        // Специальная обработка для Mean Node - делегируем к swe_nod_aps
        if ($ipl === Constants::SE_MEAN_NODE) {
            return self::calcMeanNode($jd_tt, $iflag, $xx, $serr);
        }

        // Специальная обработка для True Node (osculating lunar node)
        if ($ipl === Constants::SE_TRUE_NODE) {
            return self::calcTrueNode($jd_tt, $iflag, $xx, $serr);
        }

        // Специальная обработка для Mean Apogee (Mean Black Moon Lilith)
        if ($ipl === Constants::SE_MEAN_APOG) {
            return self::calcMeanApogee($jd_tt, $iflag, $xx, $serr);
        }

        // Специальная обработка для Oscu Apogee (True Black Moon Lilith)
        if ($ipl === Constants::SE_OSCU_APOG) {
            return self::calcOscuApogee($jd_tt, $iflag, $xx, $serr);
        }

        // Специальная обработка для Interpolated Lunar Apogee
        if ($ipl === Constants::SE_INTP_APOG) {
            return self::calcIntpApogee($jd_tt, $iflag, $xx, $serr);
        }

        // Специальная обработка для Interpolated Lunar Perigee
        if ($ipl === Constants::SE_INTP_PERG) {
            return self::calcIntpPerigee($jd_tt, $iflag, $xx, $serr);
        }

        // Специальная обработка для main belt asteroids (Chiron through Vesta)
        if ($ipl >= Constants::SE_CHIRON && $ipl <= Constants::SE_VESTA) {
            return self::calcAsteroid($jd_tt, $ipl, $iflag, $xx, $serr);
        }

        // Специальная обработка для planetary moons (SE_PLMOON_OFFSET + moon_id)
        // For example: SE_PLMOON_OFFSET + 501 = Io (9501)
        //              SE_PLMOON_OFFSET + 606 = Titan (9606)
        // Format: PPNN where PP = planet (4=Mars, 5=Jupiter, 6=Saturn, etc.), NN = moon number
        if ($ipl > Constants::SE_PLMOON_OFFSET && $ipl < Constants::SE_AST_OFFSET) {
            return self::calcPlanetaryMoon($jd_tt, $ipl, $iflag, $xx, $serr);
        }

        // Специальная обработка для numbered asteroids (SE_AST_OFFSET + asteroid_number)
        // For example: SE_AST_OFFSET + 433 = Eros (10433)
        //              SE_AST_OFFSET + 1 = Ceres by MPC number (10001)
        // Note: asteroids 1-4 can also be accessed via SE_CERES..SE_VESTA (16-19)
        if ($ipl > Constants::SE_AST_OFFSET) {
            return self::calcNumberedAsteroid($jd_tt, $ipl, $iflag, $xx, $serr);
        }

        // Специальная обработка для Uranian/fictitious planets
        if (FictitiousPlanets::isFictitious($ipl)) {
            return self::calcFictitious($jd_tt, $ipl, $iflag, $xx, $serr);
        }

        // Проверка взаимоисключающих источников эфемерид
        $srcCount = (($iflag & Constants::SEFLG_JPLEPH) ? 1 : 0)
            + (($iflag & Constants::SEFLG_SWIEPH) ? 1 : 0)
            + (($iflag & Constants::SEFLG_MOSEPH) ? 1 : 0);
        if ($srcCount > 1) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                'Conflicting ephemeris source flags (choose one of JPLEPH/SWIEPH/MOSEPH)'
            );
            return Constants::SE_ERR;
        }

        // VSOP87 конфликтует с любым другим источником (эксклюзивный путь)
        if (($iflag & Constants::SEFLG_VSOP87) && $srcCount > 0) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                'VSOP87 flag conflicts with other ephemeris source flags'
            );
            return Constants::SE_ERR;
        }

        // По умолчанию используем SWIEPH, если ничего не выставлено и не выбран VSOP87
        if (!(($iflag & Constants::SEFLG_VSOP87)
            || ($iflag & Constants::SEFLG_JPLEPH)
            || ($iflag & Constants::SEFLG_SWIEPH)
            || ($iflag & Constants::SEFLG_MOSEPH))) {
            $iflag |= Constants::SEFLG_SWIEPH;
        }

        $strategy = EphemerisStrategyFactory::forFlags($iflag, $ipl);
        if ($strategy === null) {
            // Источник не реализован (например JPLEPH/MOSEPH в текущем порте)
            $serr = ErrorCodes::compose(ErrorCodes::UNSUPPORTED, 'Ephemeris source not implemented yet');
            return Constants::SE_ERR;
        }

        $res = $strategy->compute($jd_tt, $ipl, $iflag);
        if ($res->retc < 0) {
            if ($serr === null) {
                $serr = $res->serr;
            }
            return Constants::SE_ERR;
        }

        // Берём финальный блок координат (с учётом флагов) из стратегии
        $xx = $res->x;
        return $iflag;
    }

    /**
     * Calculate Mean Node by delegating to swe_nod_aps
     * Returns ascending node position
     *
     * @param float $jd_tt Julian day in TT
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcMeanNode(float $jd_tt, int $iflag, array &$xx, ?string &$serr): int
    {
        // Use NodesApsidesFunctions to get mean node for Moon
        $xnasc = null;  // Ascending node
        $xndsc = null;  // Descending node (not needed)
        $xperi = null;  // Perihelion (not needed)
        $xaphe = null;  // Aphelion (not needed)

        $ret = NodesApsidesFunctions::nodAps(
            $jd_tt,
            Constants::SE_MOON,  // Mean node is for the Moon
            $iflag,
            Constants::SE_NODBIT_MEAN,  // Request mean nodes
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );

        if ($ret < 0) {
            return Constants::SE_ERR;
        }

        // Copy ascending node coordinates to output
        $xx = $xnasc;

        return $iflag;
    }

    /**
     * Calculate True Node (osculating lunar ascending node)
     *
     * Uses LunarOsculatingCalculator which ports lunar_osc_elem() from sweph.c
     *
     * @param float $jd_tt Julian day in TT
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcTrueNode(float $jd_tt, int $iflag, array &$xx, ?string &$serr): int
    {
        $xreturn = [];
        $ret = \Swisseph\Domain\NodesApsides\LunarOsculatingCalculator::calculate(
            $jd_tt,
            Constants::SE_TRUE_NODE,
            $iflag,
            $xreturn,
            $serr
        );

        if ($ret < 0) {
            return Constants::SE_ERR;
        }

        // LunarOsculatingCalculator returns 24-element array
        // xx[0-5] = ecliptic polar (lon, lat, dist, speed_lon, speed_lat, speed_dist)
        // Copy first 6 elements to output
        for ($i = 0; $i < 6 && $i < count($xreturn); $i++) {
            $xx[$i] = $xreturn[$i];
        }

        return $iflag;
    }

    /**
     * Calculate Mean Apogee (Mean Black Moon Lilith)
     *
     * Uses NodesApsidesFunctions::nodAps with SE_NODBIT_MEAN
     *
     * @param float $jd_tt Julian day in TT
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcMeanApogee(float $jd_tt, int $iflag, array &$xx, ?string &$serr): int
    {
        // Use NodesApsidesFunctions to get mean apogee for Moon
        $xnasc = null;
        $xndsc = null;
        $xperi = null;  // Perigee
        $xaphe = null;  // Apogee (what we want)

        $ret = NodesApsidesFunctions::nodAps(
            $jd_tt,
            Constants::SE_MOON,
            $iflag,
            Constants::SE_NODBIT_MEAN,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );

        if ($ret < 0) {
            return Constants::SE_ERR;
        }

        // Copy apogee coordinates to output
        $xx = $xaphe;

        return $iflag;
    }

    /**
     * Calculate Osculating Apogee (True Black Moon Lilith)
     *
     * Uses LunarOsculatingCalculator which ports lunar_osc_elem() from sweph.c
     *
     * @param float $jd_tt Julian day in TT
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcOscuApogee(float $jd_tt, int $iflag, array &$xx, ?string &$serr): int
    {
        $xreturn = [];
        $ret = \Swisseph\Domain\NodesApsides\LunarOsculatingCalculator::calculate(
            $jd_tt,
            Constants::SE_OSCU_APOG,
            $iflag,
            $xreturn,
            $serr
        );

        if ($ret < 0) {
            return Constants::SE_ERR;
        }

        // LunarOsculatingCalculator returns 24-element array
        // xx[0-5] = ecliptic polar (lon, lat, dist, speed_lon, speed_lat, speed_dist)
        // Copy first 6 elements to output
        for ($i = 0; $i < 6 && $i < count($xreturn); $i++) {
            $xx[$i] = $xreturn[$i];
        }

        return $iflag;
    }

    /**
     * Calculate Interpolated Lunar Apogee
     *
     * Uses IntpApsidesCalculator which ports intp_apsides() from sweph.c
     * Only works with Moshier ephemeris.
     *
     * @param float $jd_tt Julian day in TT
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcIntpApogee(float $jd_tt, int $iflag, array &$xx, ?string &$serr): int
    {
        $xreturn = [];
        $ret = \Swisseph\Domain\NodesApsides\IntpApsidesCalculator::calculate(
            $jd_tt,
            Constants::SE_INTP_APOG,
            $iflag,
            $xreturn,
            $serr
        );

        if ($ret < 0) {
            return Constants::SE_ERR;
        }

        // IntpApsidesCalculator returns 24-element array
        // xx[0-5] = ecliptic polar (lon, lat, dist, speed_lon, speed_lat, speed_dist)
        for ($i = 0; $i < 6 && $i < count($xreturn); $i++) {
            $xx[$i] = $xreturn[$i];
        }

        return $iflag;
    }

    /**
     * Calculate Interpolated Lunar Perigee
     *
     * Uses IntpApsidesCalculator which ports intp_apsides() from sweph.c
     * Only works with Moshier ephemeris.
     *
     * @param float $jd_tt Julian day in TT
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcIntpPerigee(float $jd_tt, int $iflag, array &$xx, ?string &$serr): int
    {
        $xreturn = [];
        $ret = \Swisseph\Domain\NodesApsides\IntpApsidesCalculator::calculate(
            $jd_tt,
            Constants::SE_INTP_PERG,
            $iflag,
            $xreturn,
            $serr
        );

        if ($ret < 0) {
            return Constants::SE_ERR;
        }

        // IntpApsidesCalculator returns 24-element array
        // xx[0-5] = ecliptic polar (lon, lat, dist, speed_lon, speed_lat, speed_dist)
        for ($i = 0; $i < 6 && $i < count($xreturn); $i++) {
            $xx[$i] = $xreturn[$i];
        }

        return $iflag;
    }

    /**
     * Calculate main belt asteroids (Chiron, Pholus, Ceres, Pallas, Juno, Vesta)
     *
     * These are stored in seas_*.se1 files (main asteroid ephemeris files)
     * Uses SwephPlanCalculator → SwephCalculator to read ephemeris data
     *
     * @param float $jd_tt Julian day in TT
     * @param int $ipl Planet number (SE_CHIRON..SE_VESTA)
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcAsteroid(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr): int
    {
        // Use SwephStrategy which supports reading asteroid ephemeris files
        $strategy = EphemerisStrategyFactory::forFlags($iflag | Constants::SEFLG_SWIEPH, $ipl);

        if ($strategy === null) {
            $name = self::getPlanetName($ipl);
            $serr = "$name: Swiss Ephemeris strategy not available";
            return Constants::SE_ERR;
        }

        $res = $strategy->compute($jd_tt, $ipl, $iflag | Constants::SEFLG_SWIEPH);

        if ($res->retc < 0) {
            $serr = $res->serr;
            return Constants::SE_ERR;
        }

        $xx = $res->x;
        return $iflag;
    }

    /**
     * Calculate numbered asteroid positions (SE_AST_OFFSET + asteroid_number)
     *
     * Port of sweph.c minor planets section (lines 1018-1099)
     * Uses SEI_ANYBODY internal planet index and SEI_FILE_ANY_AST for individual asteroid files.
     *
     * Asteroid number examples:
     * - SE_AST_OFFSET + 1 = 10001 = Ceres (by MPC number)
     * - SE_AST_OFFSET + 433 = 10433 = Eros
     * - SE_AST_OFFSET + 134340 = 144340 = Pluto (dwarf planet by MPC)
     *
     * Note: For asteroids 1-4 (Ceres, Pallas, Juno, Vesta), prefer using SE_CERES..SE_VESTA
     * as they have extended ephemeris ranges. SE_AST_OFFSET + 1..4 will be remapped
     * internally to SEI_CERES..SEI_VESTA for consistency with C implementation.
     *
     * @param float $jd_tt Julian day in TT
     * @param int $ipl Planet number (must be > SE_AST_OFFSET)
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcNumberedAsteroid(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr): int
    {
        // Validate that this is indeed a numbered asteroid
        if ($ipl <= Constants::SE_AST_OFFSET) {
            $serr = "calcNumberedAsteroid: ipl=$ipl must be > SE_AST_OFFSET";
            return Constants::SE_ERR;
        }

        $asteroidNum = $ipl - Constants::SE_AST_OFFSET;

        // Per C implementation (sweph.c ~1030):
        // Asteroids 1-4 (Ceres, Pallas, Juno, Vesta) can be remapped to SE_CERES..SE_VESTA
        // which have better ephemeris coverage. But we still support them via SE_AST_OFFSET + n.
        // The SwephStrategy handles this internally.

        // Use SwephStrategy which supports reading individual asteroid ephemeris files
        // Files are stored in ast0/se00001.se1, ast0/se00433.se1, etc.
        $strategy = EphemerisStrategyFactory::forFlags($iflag | Constants::SEFLG_SWIEPH, $ipl);

        if ($strategy === null) {
            $serr = "Asteroid #$asteroidNum: Swiss Ephemeris strategy not available";
            return Constants::SE_ERR;
        }

        $res = $strategy->compute($jd_tt, $ipl, $iflag | Constants::SEFLG_SWIEPH);

        if ($res->retc < 0) {
            $serr = $res->serr ?? "Asteroid #$asteroidNum computation failed";
            return Constants::SE_ERR;
        }

        $xx = $res->x;
        return $iflag;
    }

    /**
     * Calculate planetary moon positions (SE_PLMOON_OFFSET + moon_id)
     *
     * Port of sweph.c planetary moons section (lines 426-433, 1046-1048, 1569, etc.)
     * Uses SEI_ANYBODY internal planet index and SEI_FILE_ANY_AST for individual moon files.
     *
     * Moon ID format: PPNN where:
     * - PP = parent planet number (4=Mars, 5=Jupiter, 6=Saturn, 7=Uranus, 8=Neptune, 9=Pluto)
     * - NN = moon number within planet system (01, 02, 03, etc.)
     * - 99 = Center of Body (COB) - planetary barycenter
     *
     * Examples:
     * - 9401 = Phobos/Mars
     * - 9501 = Io/Jupiter
     * - 9502 = Europa/Jupiter
     * - 9606 = Titan/Saturn
     * - 9599 = Jupiter Center of Body
     *
     * Files are stored in sat/ subdirectory: sat/sepm9501.se1, etc.
     *
     * @param float $jd_tt Julian day in TT
     * @param int $ipl Planet number (must be > SE_PLMOON_OFFSET && < SE_AST_OFFSET)
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcPlanetaryMoon(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr): int
    {
        // Validate that this is indeed a planetary moon
        if ($ipl <= Constants::SE_PLMOON_OFFSET || $ipl >= Constants::SE_AST_OFFSET) {
            $serr = "calcPlanetaryMoon: ipl=$ipl must be in range (SE_PLMOON_OFFSET, SE_AST_OFFSET)";
            return Constants::SE_ERR;
        }

        // Extract moon info from ipl
        // Format: 9PPP where PPP = planet*100 + moon_number
        // C sweph.c:428: ipl = (int) ((ipl - 9000) / 100);
        $moonCode = $ipl; // e.g., 9501 for Io
        $moonSubCode = $ipl - Constants::SE_PLMOON_OFFSET; // e.g., 501
        $parentPlanet = (int)($moonSubCode / 100); // e.g., 5 for Jupiter
        $moonNumber = $moonSubCode % 100; // e.g., 01 for Io

        // Get moon name for error messages
        $moonName = self::getPlanetaryMoonName($ipl) ?? "Moon #$moonCode";

        // CRITICAL: Planetary moon files contain coordinates RELATIVE to planet barycenter
        // Algorithm per C sweph.c:
        // 1. Compute parent planet RAW barycentric J2000 equatorial coords via SwephPlanCalculator
        // 2. Read moon relative coords (also J2000 equatorial) via SwephCalculator
        // 3. Add them together to get moon barycentric J2000 equatorial coords
        // 4. Apply full pipeline (light-time, precession, nutation, ecliptic) to the sum

        // Step 1: Get RAW parent planet coordinates (J2000 equatorial barycentric)
        // Use SwephPlanCalculator directly to avoid full pipeline
        $parentIpl = $parentPlanet; // SE_JUPITER = 5, SE_SATURN = 6, etc.
        $parentIpli = \Swisseph\SwephFile\SwephConstants::PNOEXT2INT[$parentIpl] ?? null;

        if ($parentIpli === null) {
            $serr = "$moonName: unknown parent planet $parentIpl";
            return Constants::SE_ERR;
        }

        $xpParent = [];
        $xpEarth = [];
        $xpSun = [];
        $xpMoon = null;
        $serrParent = null;

        $retc = \Swisseph\SwephFile\SwephPlanCalculator::calculate(
            $jd_tt,
            $parentIpli,
            $parentIpl,
            \Swisseph\SwephFile\SwephConstants::SEI_FILE_PLANET,
            $iflag | Constants::SEFLG_SPEED,
            true, // doSave
            $xpParent,
            $xpEarth,
            $xpSun,
            $xpMoon,
            $serrParent
        );

        if ($retc < 0) {
            $serr = "$moonName: parent planet ($parentIpl) raw coords failed: " . ($serrParent ?? '');
            return Constants::SE_ERR;
        }

        // Step 2: Read moon relative coordinates from ephemeris file
        // Files are in sat/sepm9501.se1 format
        // These are coordinates relative to planet barycenter (J2000 equatorial)
        $xMoonRel = [];
        $serrMoon = null;

        $retc = \Swisseph\SwephFile\SwephCalculator::calculate(
            $jd_tt,
            \Swisseph\SwephFile\SwephConstants::SEI_ANYBODY,
            $ipl, // e.g., 9501
            \Swisseph\SwephFile\SwephConstants::SEI_FILE_ANY_AST,
            $iflag | Constants::SEFLG_SPEED,
            null, // No xsunb - moon is relative to planet, not Sun
            true, // doSave
            $xMoonRel,
            $serrMoon
        );

        if ($retc < 0) {
            $serr = "$moonName ephemeris file error: " . ($serrMoon ?? 'unknown');
            return Constants::SE_ERR;
        }

        // Step 3: Add relative moon coords to parent planet coords
        // C sweph.c:2451-2453: calc_center_body() does xx[i] += xcom[i]
        // Both are in J2000 equatorial barycentric frame
        $xxRaw = array_fill(0, 6, 0.0);
        for ($i = 0; $i < 6; $i++) {
            $xxRaw[$i] = ($xpParent[$i] ?? 0.0) + ($xMoonRel[$i] ?? 0.0);
        }

        // Step 4: Apply full apparent position pipeline
        // This handles: light-time, deflection, aberration, precession, nutation, ecliptic conversion
        $xx = \Swisseph\Swe\Planets\PlanetApparentPipeline::computeFinal($jd_tt, $ipl, $iflag, $xxRaw);

        return $iflag;
    }

    /**
     * Get name for planetary moon
     *
     * @param int $ipl Planetary moon ID (SE_PLMOON_OFFSET + moon_code)
     * @return string|null Moon name or null if unknown
     */
    private static function getPlanetaryMoonName(int $ipl): ?string
    {
        // Lookup table for known planetary moons
        // From plmolist.txt in sat/ directory
        $names = [
            // Mars moons
            9401 => 'Phobos/Mars',
            9402 => 'Deimos/Mars',
            // Jupiter moons
            9501 => 'Io/Jupiter',
            9502 => 'Europa/Jupiter',
            9503 => 'Ganymede/Jupiter',
            9504 => 'Callisto/Jupiter',
            9599 => 'Jupiter/COB',
            // Saturn moons
            9601 => 'Mimas/Saturn',
            9602 => 'Enceladus/Saturn',
            9603 => 'Tethys/Saturn',
            9604 => 'Dione/Saturn',
            9605 => 'Rhea/Saturn',
            9606 => 'Titan/Saturn',
            9607 => 'Hyperion/Saturn',
            9608 => 'Iapetus/Saturn',
            9699 => 'Saturn/COB',
            // Uranus moons
            9701 => 'Ariel/Uranus',
            9702 => 'Umbriel/Uranus',
            9703 => 'Titania/Uranus',
            9704 => 'Oberon/Uranus',
            9705 => 'Miranda/Uranus',
            9799 => 'Uranus/COB',
            // Neptune moons
            9801 => 'Triton/Neptune',
            9802 => 'Nereid/Neptune',
            9808 => 'Proteus/Neptune',
            9899 => 'Neptune/COB',
            // Pluto moons
            9901 => 'Charon/Pluto',
            9902 => 'Nix/Pluto',
            9903 => 'Hydra/Pluto',
            9904 => 'Kerberos/Pluto',
            9905 => 'Styx/Pluto',
            9999 => 'Pluto/COB',
        ];

        return $names[$ipl] ?? null;
    }

    /**
     * Calculate Uranian/fictitious planets from osculating orbital elements
     *
     * Port of swemplan.c:swi_osc_el_plan() logic
     * Supports SE_CUPIDO through SE_WALDEMATH (bodies 40-58) using Neely orbital elements
     *
     * @param float $jd_tt Julian day in TT
     * @param int $ipl Planet number (SE_CUPIDO..SE_WALDEMATH)
     * @param int $iflag Calculation flags
     * @param array &$xx Output coordinates
     * @param string|null &$serr Error message
     * @return int iflag on success, SE_ERR on error
     */
    private static function calcFictitious(float $jd_tt, int $ipl, int $iflag, array &$xx, ?string &$serr): int
    {
        // Validate fictitious planet range
        if (!FictitiousPlanets::isFictitious($ipl)) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                "Planet ipl=$ipl is not a fictitious body"
            );
            return Constants::SE_ERR;
        }

        // Check if planet is in built-in elements table
        $iplFict = $ipl - Constants::SE_FICT_OFFSET;
        if ($iplFict >= Constants::SE_NFICT_ELEM) {
            $serr = ErrorCodes::compose(
                ErrorCodes::INVALID_ARG,
                "Fictitious planet ipl=$ipl (index=$iplFict) not in built-in elements table"
            );
            return Constants::SE_ERR;
        }

        // Compute heliocentric equatorial J2000 coordinates from osculating elements
        // FictitiousPlanets::compute() returns equatorial J2000 cartesian
        $xp = FictitiousPlanets::compute($jd_tt, $ipl, $serr);
        if ($xp === null) {
            return Constants::SE_ERR;
        }

        // Now apply coordinate transformations based on flags
        // Similar to app_pos_etc_plan_osc in sweph.c

        // Get SwedState for transformations
        $state = SwedState::getInstance();
        $state->oec->calculate($jd_tt);  // Ensure obliquity is calculated for current date

        // 1. Convert heliocentric to geocentric if not heliocentric flag
        if (!($iflag & Constants::SEFLG_HELCTR)) {
            // Get Earth heliocentric position from SwedState cache or compute it
            // The Earth position is cached after computing any planet position
            // NOTE: earth_pd->x is equatorial J2000
            $earth_pd = &$state->pldat[\Swisseph\SwephFile\SwephConstants::SEI_EARTH] ?? null;

            // If Earth not yet computed, compute it now
            if ($earth_pd === null || $earth_pd->teval !== $jd_tt) {
                // Force Earth calculation by computing Sun (which requires Earth)
                $xdummy = [];
                $dummy_serr = null;
                $earthStrategy = EphemerisStrategyFactory::forFlags(
                    Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR,
                    Constants::SE_SUN
                );
                if ($earthStrategy !== null) {
                    $earthStrategy->compute($jd_tt, Constants::SE_SUN, Constants::SEFLG_SWIEPH | Constants::SEFLG_HELCTR);
                }
                // Refresh reference
                $earth_pd = &$state->pldat[\Swisseph\SwephFile\SwephConstants::SEI_EARTH] ?? null;
            }

            if ($earth_pd === null || !isset($earth_pd->x)) {
                $serr = 'Earth heliocentric position not available for geocentric conversion';
                return Constants::SE_ERR;
            }

            // Earth coordinates in earth_pd->x are heliocentric equatorial J2000
            $xe = $earth_pd->x;

            // Geocentric = heliocentric planet - heliocentric Earth
            // Result: geocentric equatorial J2000
            for ($i = 0; $i < 6; $i++) {
                $xp[$i] = $xp[$i] - $xe[$i];
            }
        }

        // At this point we have geocentric (or heliocentric) equatorial J2000 coordinates

        // 2. Precess from J2000 to current date
        $pos = [$xp[0], $xp[1], $xp[2]];
        Precession::precess($pos, $jd_tt, 0, Constants::J2000_TO_J);
        $xp[0] = $pos[0];
        $xp[1] = $pos[1];
        $xp[2] = $pos[2];

        $vel = [$xp[3], $xp[4], $xp[5]];
        Precession::precess($vel, $jd_tt, 0, Constants::J2000_TO_J);
        $xp[3] = $vel[0];
        $xp[4] = $vel[1];
        $xp[5] = $vel[2];

        // 3. Convert equatorial to ecliptic of date (unless SEFLG_EQUATORIAL requested)
        if (!($iflag & Constants::SEFLG_EQUATORIAL)) {
            $eps = $state->oec->eps;  // Obliquity of ecliptic for current date (radians)
            // Rotate from equatorial to ecliptic (positive rotation around X-axis)
            $xpn = $xp;
            Coordinates::coortrf($xp, $xpn, $eps);  // Positive for equ->ecl
            $xp[0] = $xpn[0];
            $xp[1] = $xpn[1];
            $xp[2] = $xpn[2];
            // Also transform velocity
            $xps = [$xp[3], $xp[4], $xp[5]];
            $xpsn = [];
            Coordinates::coortrf($xps, $xpsn, $eps);
            $xp[3] = $xpsn[0];
            $xp[4] = $xpsn[1];
            $xp[5] = $xpsn[2];
        }
        // If SEFLG_EQUATORIAL is set, we keep the equatorial coordinates of date

        // 3. Convert to XYZ format if requested
        if ($iflag & Constants::SEFLG_XYZ) {
            // Already in cartesian, just copy
            // [x, y, z, vx, vy, vz]
        } else {
            // Convert to spherical (default output format)
            // [lon, lat, dist, lon_speed, lat_speed, dist_speed]
            $l = [];
            Coordinates::cartPolSp($xp, $l);
            // cartPolSp returns radians, convert to degrees
            $xp = [
                rad2deg($l[0]),  // longitude in degrees
                rad2deg($l[1]),  // latitude in degrees
                $l[2],           // distance in AU
                rad2deg($l[3]),  // longitude speed in deg/day
                rad2deg($l[4]),  // latitude speed in deg/day
                $l[5],           // distance speed in AU/day
            ];
        }

        // 4. Convert to radians if requested (and not XYZ)
        if (($iflag & Constants::SEFLG_RADIANS) && !($iflag & Constants::SEFLG_XYZ)) {
            $xp[0] = deg2rad($xp[0]);
            $xp[1] = deg2rad($xp[1]);
            $xp[3] = deg2rad($xp[3]);
            $xp[4] = deg2rad($xp[4]);
        }

        // 5. Apply sidereal transformation if requested
        if ($iflag & Constants::SEFLG_SIDEREAL) {
            $xp[0] = Sidereal::adjustLongitude($xp[0], $jd_tt, $iflag);
        }

        $xx = $xp;
        return $iflag;
    }

    /**
     * UT -> TT конверсия затем делегирование стратегии.
     */
    public static function calcUt(float $jd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $dt_sec = DeltaT::deltaTSecondsFromJd($jd_ut);
        $jd_tt = $jd_ut + $dt_sec / 86400.0;
        return self::calc($jd_tt, $ipl, $iflag, $xx, $serr);
    }

    /**
     * Planetocentric calculation - calculate positions relative to another planet.
     *
     * Port of swe_calc_pctr() from sweph.c:8096-8340 (~250 lines).
     * Full C API compatibility - NO SIMPLIFICATIONS.
     *
     * @param float $tjd Julian day number (TT/ET)
     * @param int $ipl Target planet number
     * @param int $iplctr Center planet number (viewing position)
     * @param int $iflag Calculation flags
     * @param array &$xxret Output array [6] for coordinates
     * @param string|null &$serr Error string
     * @return int iflag on success, SE_ERR on error
     */
    public static function calcPctr(
        float $tjd,
        int $ipl,
        int $iplctr,
        int $iflag,
        array &$xxret,
        ?string &$serr = null
    ): int {
        // Validation: planets must be different
        if ($ipl === $iplctr) {
            $serr = sprintf("ipl and iplctr (= %d) must not be identical", $ipl);
            return Constants::SE_ERR;
        }

        // Validate flags (plaus_iflag equivalent)
        $iflag = self::plausibleIflag($iflag, $ipl, $tjd, $serr);
        $epheflag = $iflag & Constants::SEFLG_EPHMASK;

        // Fill obliquity and nutation values in swed
        // C: swe_calc(tjd + swe_deltat_ex(tjd, epheflag, serr), SE_ECL_NUT, iflag, xx, serr);
        $swed = SwedState::getInstance();
        $swed->oec->calculate($tjd, $iflag);
        $swed->ensureNutation($tjd, $iflag, $swed->oec->seps, $swed->oec->ceps);

        // Remove HELCTR/BARYCTR from iflag for internal calculations
        $iflag &= ~(Constants::SEFLG_HELCTR | Constants::SEFLG_BARYCTR);

        // Build flags for barycentric J2000 ICRS calculations
        $iflag2 = $epheflag;
        $iflag2 |= (Constants::SEFLG_BARYCTR | Constants::SEFLG_J2000 | Constants::SEFLG_ICRS |
                   Constants::SEFLG_TRUEPOS | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_XYZ |
                   Constants::SEFLG_SPEED);
        $iflag2 |= (Constants::SEFLG_NOABERR | Constants::SEFLG_NOGDEFL);

        // Calculate center planet (barycentric)
        $xxctr = [];
        $retc = self::calc($tjd, $iplctr, $iflag2, $xxctr, $serr);
        if ($retc < 0) {
            $serr = "calc_pctr: Failed to calculate center planet $iplctr: " . ($serr ?: 'unknown error');
            return Constants::SE_ERR;
        }
        // Convert to polar for debug
        $xxctr_polar = [];
        Coordinates::cartPolSp($xxctr, $xxctr_polar);
        $ra_ctr_deg = $xxctr_polar[0] * Constants::RADTODEG;
        $dec_ctr_deg = $xxctr_polar[1] * Constants::RADTODEG;
        // error_log("DEBUG calc_pctr INITIAL xxctr (barycentric equatorial J2000): RA={$ra_ctr_deg}°, Dec={$dec_ctr_deg}°");
        // error_log("DEBUG calc_pctr INITIAL xxctr[0-2]=[{$xxctr[0]}, {$xxctr[1]}, {$xxctr[2]}]");

        // Calculate target planet (barycentric)
        $xx = [];
        $retc = self::calc($tjd, $ipl, $iflag2, $xx, $serr);
        if ($retc < 0) {
            $serr = "calc_pctr: Failed to calculate target planet $ipl: " . ($serr ?: 'unknown error');
            return Constants::SE_ERR;
        }
        // Convert to polar for debug
        $xx_polar = [];
        Coordinates::cartPolSp($xx, $xx_polar);
        $ra_deg = $xx_polar[0] * Constants::RADTODEG;
        $dec_deg = $xx_polar[1] * Constants::RADTODEG;
        // error_log("DEBUG calc_pctr INITIAL xx (barycentric equatorial J2000): RA={$ra_deg}°, Dec={$dec_deg}°");
        // error_log("DEBUG calc_pctr INITIAL xx[0-2]=[{$xx[0]}, {$xx[1]}, {$xx[2]}]");

        // Save initial position
        $xx0 = array_slice($xx, 0, 6);

        // Initialize arrays
        $xxsp = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xxsv = array_fill(0, 24, 0.0);
        $xreturn = array_fill(0, 24, 0.0);
        $xxctr2 = [];
        $dtsave_for_defl = 0.0;
        $t = 0.0;

        /*******************************
         * light-time geocentric       *
         *******************************/
        if (!($iflag & Constants::SEFLG_TRUEPOS)) {
            // number of iterations - 1
            $niter = 1;

            if ($iflag & Constants::SEFLG_SPEED) {
                /*
                 * Apparent speed is influenced by the fact that dt changes with
                 * time. This makes a difference of several hundredths of an
                 * arc second / day. To take this into account, we compute
                 * 1. true position - apparent position at time t - 1.
                 * 2. true position - apparent position at time t.
                 * 3. the difference between the two is the part of the daily motion
                 * that results from the change of dt.
                 */
                for ($i = 0; $i <= 2; $i++) {
                    $xxsv[$i] = $xxsp[$i] = $xx[$i] - $xx[$i + 3];
                }
                for ($j = 0; $j <= $niter; $j++) {
                    $dx = [0.0, 0.0, 0.0];
                    for ($i = 0; $i <= 2; $i++) {
                        $dx[$i] = $xxsp[$i];
                        $dx[$i] -= ($xxctr[$i] - $xxctr[$i + 3]);
                    }
                    // new dt
                    $dt = sqrt(VectorMath::squareSum($dx)) * Constants::AUNIT / Constants::CLIGHT / 86400.0;
                    for ($i = 0; $i <= 2; $i++) {
                        // rough apparent position at t-1
                        $xxsp[$i] = $xxsv[$i] - $dt * $xx0[$i + 3];
                    }
                }
                // true position - apparent position at time t-1
                for ($i = 0; $i <= 2; $i++) {
                    $xxsp[$i] = $xxsv[$i] - $xxsp[$i];
                }
            }

            // dt and t(apparent)
            for ($j = 0; $j <= $niter; $j++) {
                $dx = [0.0, 0.0, 0.0];
                for ($i = 0; $i <= 2; $i++) {
                    $dx[$i] = $xx[$i];
                    $dx[$i] -= $xxctr[$i];
                }
                $dt = sqrt(VectorMath::squareSum($dx)) * Constants::AUNIT / Constants::CLIGHT / 86400.0;
                // new t
                $t = $tjd - $dt;
                $dtsave_for_defl = $dt;
                for ($i = 0; $i <= 2; $i++) {
                    // rough apparent position at t
                    $xx[$i] = $xx0[$i] - $dt * $xx0[$i + 3];
                }
            }

            // part of daily motion resulting from change of dt
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 0; $i <= 2; $i++) {
                    $xxsp[$i] = $xx0[$i] - $xx[$i] - $xxsp[$i];
                }
            }

            $retc = self::calc($t, $iplctr, $iflag2, $xxctr2, $serr);
            if ($retc < 0) {
                $serr = "calc_pctr: Failed to calculate center planet at t-dt: " . ($serr ?: 'unknown error');
                return Constants::SE_ERR;
            }

            $retc = self::calc($t, $ipl, $iflag2, $xx, $serr);
            if ($retc < 0) {
                $serr = "calc_pctr: Failed to calculate target planet at t-dt: " . ($serr ?: 'unknown error');
                return Constants::SE_ERR;
            }
        }

        /*******************************
         * conversion to planetocenter *
         *******************************/
        if (!($iflag & Constants::SEFLG_HELCTR) && !($iflag & Constants::SEFLG_BARYCTR)) {
            // subtract earth
            for ($i = 0; $i <= 5; $i++) {
                $xx[$i] -= $xxctr[$i];
            }
            if (!($iflag & Constants::SEFLG_TRUEPOS)) {
                /*
                 * Apparent speed is also influenced by
                 * the change of dt during motion.
                 * Neglect of this would result in an error of several 0.01"
                 */
                if ($iflag & Constants::SEFLG_SPEED) {
                    for ($i = 3; $i <= 5; $i++) {
                        $xx[$i] -= $xxsp[$i - 3];
                    }
                }
            }
        }

        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        /************************************
         * relativistic deflection of light *
         ************************************/
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOGDEFL)) {
            // SEFLG_NOGDEFL is on, if SEFLG_HELCTR or SEFLG_BARYCTR
            Coordinates::deflectLight($xx, $dtsave_for_defl, $iflag);
        }

        /**********************************
         * 'annual' aberration of light   *
         **********************************/
        if (!($iflag & Constants::SEFLG_TRUEPOS) && !($iflag & Constants::SEFLG_NOABERR)) {
            // SEFLG_NOABERR is on, if SEFLG_HELCTR or SEFLG_BARYCTR
            // For planetocentric: use center planet (xxctr) for aberration, not Earth
            \Swisseph\Swe\FixedStars\StarTransforms::aberrLight($xx, $xxctr, $iflag);
            /*
             * Apparent speed is also influenced by
             * the difference of speed of the earth between t and t-dt.
             * Neglecting this would involve an error of several 0.1"
             */
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 3; $i <= 5; $i++) {
                    $xx[$i] += $xxctr[$i] - $xxctr2[$i];
                }
            }
        }

        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i <= 5; $i++) {
                $xx[$i] = 0.0;
            }
        }

        // ICRS to J2000
        if (!($iflag & Constants::SEFLG_ICRS) && SwephUtils::getDenum($ipl, $epheflag) >= 403) {
            Bias::bias($xx, $t, $iflag, false);
        }

        // save J2000 coordinates; required for sidereal positions
        for ($i = 0; $i <= 5; $i++) {
            $xxsv[$i] = $xx[$i];
        }

        /************************************************
         * precession, equator 2000 -> equator of date *
         ************************************************/
        if (!($iflag & Constants::SEFLG_J2000)) {
            Precession::precess($xx, $tjd, $iflag, Constants::J2000_TO_J, null);
            if ($iflag & Constants::SEFLG_SPEED) {
                Precession::precessSpeed($xx, $tjd, $iflag, Constants::J2000_TO_J);
            }
            $oe = SwedState::getInstance()->oec;
        } else {
            $oe = SwedState::getInstance()->oec2000;
        }

        /************************************************
         * nutation                                     *
         ************************************************/
        if (!($iflag & Constants::SEFLG_NONUT)) {
            $swed = SwedState::getInstance();
            Coordinates::nutate($xx, $swed->nutMatrix, $swed->nutMatrixVelocity, $iflag, false);
        }

        // now we have equatorial cartesian coordinates; save them
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[18 + $i] = $xx[$i];
        }

        /************************************************
         * transformation to ecliptic.                  *
         * with sidereal calc. this will be overwritten *
         * afterwards.                                  *
         ************************************************/
        // error_log("DEBUG calc_pctr BEFORE ecl transform: xx[0-2]=[{$xx[0]}, {$xx[1]}, {$xx[2]}] (equatorial)");
        $pos = [$xx[0], $xx[1], $xx[2]];
        $out = [];
        Coordinates::coortrf2($pos, $out, $oe->seps, $oe->ceps);
        $xx[0] = $out[0];
        $xx[1] = $out[1];
        $xx[2] = $out[2];
        // error_log("DEBUG calc_pctr AFTER ecl transform: xx[0-2]=[{$xx[0]}, {$xx[1]}, {$xx[2]}] (ecliptic)");

        if ($iflag & Constants::SEFLG_SPEED) {
            $vel = [$xx[3], $xx[4], $xx[5]];
            $outv = [];
            Coordinates::coortrf2($vel, $outv, $oe->seps, $oe->ceps);
            $xx[3] = $outv[0];
            $xx[4] = $outv[1];
            $xx[5] = $outv[2];
        }

        if (!($iflag & Constants::SEFLG_NONUT)) {
            $swed = SwedState::getInstance();
            $pos = [$xx[0], $xx[1], $xx[2]];
            $out = [];
            Coordinates::coortrf2($pos, $out, $swed->snut, $swed->cnut);
            $xx[0] = $out[0];
            $xx[1] = $out[1];
            $xx[2] = $out[2];

            if ($iflag & Constants::SEFLG_SPEED) {
                $vel = [$xx[3], $xx[4], $xx[5]];
                $outv = [];
                Coordinates::coortrf2($vel, $outv, $swed->snut, $swed->cnut);
                $xx[3] = $outv[0];
                $xx[4] = $outv[1];
                $xx[5] = $outv[2];
            }
        }

        // now we have ecliptic cartesian coordinates
        // error_log("DEBUG calc_pctr ECLIPTIC CARTESIAN xx[0-2]=[{$xx[0]}, {$xx[1]}, {$xx[2]}]");
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[6 + $i] = $xx[$i];
        }

        /************************************
         * sidereal positions               *
         ************************************/
        if ($iflag & Constants::SEFLG_SIDEREAL) {
            $swed = SwedState::getInstance();
            // project onto ecliptic t0
            if ($swed->sidd->sid_mode & Constants::SE_SIDBIT_ECL_T0) {
                $xxsv_arr = array_slice($xxsv, 0, 6);
                $xret6 = array_slice($xreturn, 6, 6);
                $xret18 = array_slice($xreturn, 18, 6);
                if (SiderealFunctions::tropRa2sidLon($xxsv_arr, $xret6, $xret18, $iflag) !== Constants::OK) {
                    return Constants::SE_ERR;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xreturn[6 + $i] = $xret6[$i];
                }
            // project onto solar system equator
            } elseif ($swed->sidd->sid_mode & Constants::SE_SIDBIT_SSY_PLANE) {
                $xxsv_arr = array_slice($xxsv, 0, 6);
                $xret6 = array_slice($xreturn, 6, 6);
                if (SiderealFunctions::tropRa2sidLonSosy($xxsv_arr, $xret6, $iflag) !== Constants::OK) {
                    return Constants::SE_ERR;
                }
                for ($i = 0; $i <= 5; $i++) {
                    $xreturn[6 + $i] = $xret6[$i];
                }
            } else {
                // traditional algorithm
                $xret_slice = array_slice($xreturn, 6, 6);
                $polar_temp = [];
                Coordinates::cartPolSp($xret_slice, $polar_temp);
                for ($i = 0; $i <= 5; $i++) {
                    $xreturn[$i] = $polar_temp[$i];
                }

                // note, swi_get_ayanamsa_ex() disturbs present calculations, if sun is calculated with
                // TRUE_CHITRA ayanamsha, because the ayanamsha also calculates the sun.
                // Therefore current values are saved...
                $xxsv_temp = $xreturn;
                $daya = [0.0, 0.0];
                if (Sidereal::getAyanamsaWithSpeed($tjd, $iflag, $daya, $serr) === Constants::SE_ERR) {
                    return Constants::SE_ERR;
                }
                // ... and restored
                $xreturn = $xxsv_temp;

                $xreturn[0] -= $daya[0] * Constants::DEGTORAD;
                $xreturn[3] -= $daya[1] * Constants::DEGTORAD;

                $xret_polar = array_slice($xreturn, 0, 6);
                $cart_temp = [];
                Coordinates::polCartSp($xret_polar, $cart_temp);
                for ($i = 0; $i <= 5; $i++) {
                    $xreturn[6 + $i] = $cart_temp[$i];
                }
            }
        }

        /************************************************
         * transformation to polar coordinates          *
         ************************************************/
        $xret18_slice = array_slice($xreturn, 18, 6);
        $polar18_temp = [];
        Coordinates::cartPolSp($xret18_slice, $polar18_temp);
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[12 + $i] = $polar18_temp[$i];
        }

        $xret6_slice = array_slice($xreturn, 6, 6);
        // error_log("DEBUG calc_pctr BEFORE cartPolSp: xret6_slice[0-2]=[{$xret6_slice[0]}, {$xret6_slice[1]}, {$xret6_slice[2]}]");
        $polar6_temp = [];
        Coordinates::cartPolSp($xret6_slice, $polar6_temp);
        // error_log("DEBUG calc_pctr AFTER cartPolSp: polar6_temp[0-2]=[{$polar6_temp[0]}, {$polar6_temp[1]}, {$polar6_temp[2]}] (radians)");
        for ($i = 0; $i <= 5; $i++) {
            $xreturn[$i] = $polar6_temp[$i];
        }

        /**********************
         * radians to degrees *
         **********************/
        for ($i = 0; $i < 2; $i++) {
            $xreturn[$i] *= Constants::RADTODEG;        // ecliptic
            $xreturn[$i + 3] *= Constants::RADTODEG;
            $xreturn[$i + 12] *= Constants::RADTODEG;   // equator
            $xreturn[$i + 15] *= Constants::RADTODEG;
        }

        // return values
        if ($iflag & Constants::SEFLG_EQUATORIAL) {
            $xs = array_slice($xreturn, 12, 6); // equatorial coordinates
        } else {
            $xs = array_slice($xreturn, 0, 6);  // ecliptic coordinates
        }

        if ($iflag & Constants::SEFLG_XYZ) {
            $xs = array_slice($xreturn, ($iflag & Constants::SEFLG_EQUATORIAL) ? 18 : 6, 6); // cartesian coordinates
        }

        for ($i = 0; $i < 6; $i++) {
            $xxret[$i] = $xs[$i];
        }

        if (!($iflag & Constants::SEFLG_SPEED)) {
            for ($i = 3; $i < 6; $i++) {
                $xxret[$i] = 0.0;
            }
        }

        if ($iflag & Constants::SEFLG_RADIANS) {
            for ($i = 0; $i < 2; $i++) {
                $xxret[$i] *= Constants::DEGTORAD;
            }
            if ($iflag & Constants::SEFLG_SPEED) {
                for ($i = 3; $i < 5; $i++) {
                    $xxret[$i] *= Constants::DEGTORAD;
                }
            }
        }

        if ($retc < 0) {
            return Constants::SE_ERR;
        }

        return $iflag;
    }

    /**
     * Validate and adjust calculation flags (port of plaus_iflag).
     */
    private static function plausibleIflag(int $iflag, int $ipl, float $tjd, ?string &$serr): int
    {
        // For now, just return iflag as-is
        // Full implementation would validate flag combinations
        return $iflag;
    }
}
