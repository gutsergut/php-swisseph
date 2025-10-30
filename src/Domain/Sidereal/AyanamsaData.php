<?php

namespace Swisseph\Domain\Sidereal;

/**
 * Ayanamsha data table ported from Swiss Ephemeris C code (sweph.h).
 *
 * For each ayanamsa, there are the following values:
 * - t0: epoch of ayanamsa, TDT (can be ET or UT)
 * - ayan_t0: ayanamsa value at epoch
 * - t0_is_UT: true if t0 is UT
 * - prec_offset: precession model for which the ayanamsha has to be corrected
 *   (0 if no correction needed, -1 if unclear/not investigated)
 */
final class AyanamsaData
{
    // Precession model constants (from swephexp.h) - must match Precession class
    public const SEMOD_PREC_IAU_1976 = 1;
    public const SEMOD_PREC_LASKAR_1986 = 2;
    public const SEMOD_PREC_WILL_EPS_LASK = 3;
    public const SEMOD_PREC_WILLIAMS_1994 = 4;
    public const SEMOD_PREC_SIMON_1994 = 5;
    public const SEMOD_PREC_IAU_2000 = 6;
    public const SEMOD_PREC_BRETAGNON_2003 = 7;
    public const SEMOD_PREC_IAU_2006 = 8;
    public const SEMOD_PREC_VONDRAK_2011 = 9;
    public const SEMOD_PREC_OWEN_1990 = 10;
    public const SEMOD_PREC_NEWCOMB = 11;

    /**
     * Ayanamsha table indexed by SE_SIDM_* constants.
     * Format: [t0, ayan_t0, t0_is_UT, prec_offset]
     */
    public const DATA = [
        // 0: SE_SIDM_FAGAN_BRADLEY
        [2433282.42346, 24.042044444, false, self::SEMOD_PREC_NEWCOMB],

        // 1: SE_SIDM_LAHIRI
        [2435553.5, 23.250182778 - 0.004658035, false, self::SEMOD_PREC_IAU_1976],

        // 2: SE_SIDM_DELUCE
        [1721057.5, 0.0, true, 0],

        // 3: SE_SIDM_RAMAN
        [2415020.0, 360.0 - 338.98556, false, self::SEMOD_PREC_NEWCOMB],

        // 4: SE_SIDM_USHASHASHI
        [2415020.0, 360.0 - 341.33904, false, -1],

        // 5: SE_SIDM_KRISHNAMURTI
        [2415020.0, 360.0 - 337.636111, false, self::SEMOD_PREC_NEWCOMB],

        // 6: SE_SIDM_DJWHAL_KHUL
        [2415020.0, 360.0 - 333.0369024, false, 0],

        // 7: SE_SIDM_YUKTESHWAR
        [2415020.0, 360.0 - 338.917778, false, -1],

        // 8: SE_SIDM_JN_BHASIN
        [2415020.0, 360.0 - 338.634444, false, -1],

        // 9: SE_SIDM_BABYLONIAN_KUGLER1
        [1684532.5, -5.66667, true, -1],

        // 10: SE_SIDM_BABYLONIAN_KUGLER2
        [1684532.5, -4.26667, true, -1],

        // 11: SE_SIDM_BABYLONIAN_KUGLER3
        [1684532.5, -3.41667, true, -1],

        // 12: SE_SIDM_BABYLONIAN_HUBER
        [1684532.5, -4.46667, true, -1],

        // 13: SE_SIDM_BABYLONIAN_SCHRAM (eta Piscium)
        [1673941.0, -5.079167, true, -1],

        // 14: SE_SIDM_BABYLONIAN_ESHEL (Aldebaran = 15 Tau)
        [1684532.5, -4.44138598, true, 0],

        // 15: SE_SIDM_ARYABHATA (Hipparchos)
        [1674484.0, -9.33333, true, -1],

        // 16: SE_SIDM_ARYABHATA_522 (Sassanian)
        [1927135.8747793, 0.0, true, -1],

        // 17: SE_SIDM_BABYLONIAN_ALDEBARAN (Galactic Center = 0 Sag)
        [0.0, 0.0, false, 0],

        // 18: SE_SIDM_J2000
        [2451545.0, 0.0, false, 0], // J2000

        // 19: SE_SIDM_J1900
        [2415020.0, 0.0, false, 0], // J1900

        // 20: SE_SIDM_B1950
        [2433282.42345905, 0.0, false, 0], // B1950

        // 21: Suryasiddhanta
        [1903396.8128654, 0.0, true, 0],

        // 22: Suryasiddhanta, mean Sun
        [1903396.8128654, -0.21463395, true, 0],

        // 23: Aryabhata
        [1903396.7895321, 0.0, true, 0],

        // 24: Aryabhata, mean Sun
        [1903396.7895321, -0.23763238, true, 0],

        // 25: SS Revati
        [1903396.8128654, -0.79167046, true, 0],

        // 26: SS Citra
        [1903396.8128654, 2.11070444, true, 0],

        // 27: True Citra
        [0.0, 0.0, false, 0],

        // 28: True Revati
        [0.0, 0.0, false, 0],

        // 29: True Pushya
        [0.0, 0.0, false, 0],

        // 30: Gil Brand
        [0.0, 0.0, false, 0],

        // 31: Galactic Equator IAU 1958
        [0.0, 0.0, false, 0],

        // 32: Galactic Equator True
        [0.0, 0.0, false, 0],

        // 33: Galactic Equator Mula
        [0.0, 0.0, false, 0],

        // 34: Skydram/Mardyks
        [2451079.734892000, 30.0, false, 0],

        // 35: Chandra Hari
        [0.0, 0.0, false, 0],

        // 36: Ernst Wilhelm
        [0.0, 0.0, false, 0],

        // 37: Aryabhata 522
        [1911797.740782065, 0.0, true, 0],

        // 38: Babylonian (Britton 2010)
        [1721057.5, -3.2, true, -1],

        // 39: Sunil Sheoran ("Vedic")
        [0.0, 0.0, false, 0],

        // 40: Cochrane
        [0.0, 0.0, false, 0],

        // 41: N.A. Fiorenza
        [2451544.5, 25.0, true, 0],

        // 42: Vettius Valens
        [1775845.5, -2.9422, true, -1],

        // 43: Lahiri (1940)
        [2415020.0, 22.44597222, false, self::SEMOD_PREC_NEWCOMB],

        // 44: Lahiri VP285 (1980)
        [1825235.2458513028, 0.0, false, 0],

        // 45: Krishnamurti VP291
        [1827424.752255678, 0.0, false, 0],

        // 46: Lahiri ICRC
        [2435553.5, 23.25 - 0.00464207, false, self::SEMOD_PREC_NEWCOMB],
    ];

    /**
     * Get ayanamsha data for a given sidereal mode.
     * Returns [t0, ayan_t0, t0_is_UT, prec_offset] or null if not found.
     */
    public static function get(int $sid_mode): ?array
    {
        return self::DATA[$sid_mode] ?? null;
    }
}
