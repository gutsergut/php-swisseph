<?php

namespace Swisseph;

final class Constants
{
    public const SE_JUL_CAL = 0;
    public const SE_GREG_CAL = 1;
    // Error codes
    public const SE_OK = 0;    // success / no error
    public const SE_ERR = -1; // generic error code to mirror Swiss Ephemeris style

    // Minimal subset of planet ids (Swiss Ephemeris compatible mapping)
    public const SE_SUN = 0;
    public const SE_MOON = 1;
    public const SE_MERCURY = 2;
    public const SE_VENUS = 3;
    public const SE_MARS = 4;
    public const SE_JUPITER = 5;
    public const SE_SATURN = 6;
    public const SE_URANUS = 7;
    public const SE_NEPTUNE = 8;
    public const SE_PLUTO = 9;
    public const SE_MEAN_NODE = 10;
    public const SE_TRUE_NODE = 11;
    public const SE_MEAN_APOG = 12;
    public const SE_OSCU_APOG = 13;
    public const SE_EARTH = 14;

    // Offsets for special object types
    public const SE_AST_OFFSET = 10000;      // Numbered asteroids start here
    public const SE_PLMOON_OFFSET = 9000;    // Planetary moons start here

    // Calculation flags (values match Swiss Ephemeris exactly)
    public const SEFLG_JPLEPH = 1;       // use JPL ephemeris
    public const SEFLG_SWIEPH = 2;       // use SWISSEPH ephemeris
    public const SEFLG_MOSEPH = 4;       // use Moshier ephemeris
    public const SEFLG_EPHMASK = (self::SEFLG_JPLEPH | self::SEFLG_SWIEPH | self::SEFLG_MOSEPH); // mask for ephemeris flags
    public const SEFLG_HELCTR = 8;       // heliocentric position
    public const SEFLG_TRUEPOS = 16;     // true/geometric position, not apparent
    public const SEFLG_J2000 = 32;       // no precession, i.e. give J2000 equinox
    public const SEFLG_NONUT = 64;       // no nutation, i.e. mean equinox of date
    public const SEFLG_SPEED3 = 128;     // speed from 3 positions (do not use)
    public const SEFLG_SPEED = 256;      // high precision speed
    public const SEFLG_NOGDEFL = 512;    // turn off gravitational deflection
    public const SEFLG_NOABERR = 1024;   // turn off 'annual' aberration of light
    public const SEFLG_ASTROMETRIC = (self::SEFLG_NOABERR | self::SEFLG_NOGDEFL); // astrometric position
    public const SEFLG_EQUATORIAL = (2*1024);  // equatorial positions are wanted
    public const SEFLG_XYZ = (4*1024);         // cartesian, not polar, coordinates
    public const SEFLG_RADIANS = (8*1024);     // coordinates in radians, not degrees
    public const SEFLG_BARYCTR = (16*1024);    // barycentric position
    public const SEFLG_TOPOCTR = (32*1024);    // topocentric position
    public const SEFLG_TROPICAL = 0;           // tropical position (default)
    public const SEFLG_SIDEREAL = (64*1024);   // sidereal position
    public const SEFLG_ICRS = (128*1024);      // ICRS (DE406 reference frame)
    public const SEFLG_DPSIDEPS_1980 = (256*1024); // reproduce JPL Horizons
    public const SEFLG_JPLHOR = self::SEFLG_DPSIDEPS_1980;
    public const SEFLG_JPLHOR_APPROX = (512*1024);  // approximate JPL Horizons 1962-today
    public const SEFLG_CENTER_BODY = (1024*1024);   // calculate position of center of body
    public const SEFLG_DEFAULTEPH = self::SEFLG_SWIEPH;

    // Rise/Set/Transit request bits (subset of Swiss Ephemeris RISE/SET interface)
    public const SE_CALC_RISE = 1;       // rising event
    public const SE_CALC_SET = 2;        // setting event
    public const SE_CALC_MTRANSIT = 4;   // upper culmination (meridian transit)
    public const SE_CALC_ITRANSIT = 8;   // lower culmination (anti-transit)

    // Rise/Set modifier bits (to be OR'ed with SE_CALC_RISE/SET)
    public const SE_BIT_DISC_CENTER = 256;      // use disc center instead of lower limb
    public const SE_BIT_DISC_BOTTOM = 8192;     // use lower limb of disc (obsolete, use with care)
    public const SE_BIT_GEOCTR_NO_ECL_LAT = 128; // use geocentric position (no ecliptic latitude)
    public const SE_BIT_NO_REFRACTION = 512;    // don't consider atmospheric refraction
    public const SE_BIT_CIVIL_TWILIGHT = 1024;  // civil twilight
    public const SE_BIT_NAUTIC_TWILIGHT = 2048; // nautical twilight
    public const SE_BIT_ASTRO_TWILIGHT = 4096;  // astronomical twilight
    public const SE_BIT_FIXED_DISC_SIZE = 16384; // neglect the effect of distance on disc size
    public const SE_BIT_FORCE_SLOW_METHOD = 32768; // force slow method (internal)
    public const SE_BIT_HINDU_RISING = (self::SE_BIT_DISC_CENTER | self::SE_BIT_NO_REFRACTION | self::SE_BIT_GEOCTR_NO_ECL_LAT); // Hindu rising

    // Sidereal modes (complete set from Swiss Ephemeris)
    public const SE_SIDM_FAGAN_BRADLEY = 0;      // Fagan/Bradley
    public const SE_SIDM_LAHIRI = 1;             // Lahiri
    public const SE_SIDM_DELUCE = 2;             // De Luce
    public const SE_SIDM_RAMAN = 3;              // Raman
    public const SE_SIDM_USHASHASHI = 4;         // Ushashashi
    public const SE_SIDM_KRISHNAMURTI = 5;       // Krishnamurti
    public const SE_SIDM_DJWHAL_KHUL = 6;        // Djwhal Khul
    public const SE_SIDM_YUKTESHWAR = 7;         // Yukteshwar
    public const SE_SIDM_JN_BHASIN = 8;          // JN Bhasin
    public const SE_SIDM_BABYLONIAN_KUGLER1 = 9; // Babylonian Kugler 1
    public const SE_SIDM_BABYLONIAN_KUGLER2 = 10; // Babylonian Kugler 2
    public const SE_SIDM_BABYLONIAN_KUGLER3 = 11; // Babylonian Kugler 3
    public const SE_SIDM_BABYLONIAN_HUBER = 12;   // Babylonian Huber
    public const SE_SIDM_BABYLONIAN_SCHRAM = 13;  // Babylonian Schram (Eta Piscium)
    public const SE_SIDM_BABYLONIAN_ESHEL = 14;   // Babylonian Eshel (Aldebaran = 15 Tau)
    public const SE_SIDM_ARYABHATA = 15;          // Aryabhata (Hipparchos)
    public const SE_SIDM_ARYABHATA_522 = 16;      // Aryabhata 522 (Sassanian)
    public const SE_SIDM_BABYLONIAN_ALDEBARAN = 17; // Babylonian Aldebaran (Galactic Center = 0 Sag)
    public const SE_SIDM_J2000 = 18;              // J2000-based ayanamsha
    public const SE_SIDM_J1900 = 19;              // J1900
    public const SE_SIDM_B1950 = 20;              // B1950
    public const SE_SIDM_SURYASIDDHANTA = 21;     // Suryasiddhanta
    public const SE_SIDM_SURYASIDDHANTA_MSUN = 22; // Suryasiddhanta, mean Sun
    public const SE_SIDM_ARYABHATA_MSUN = 23;     // Aryabhata, mean Sun (reuse index 23)
    public const SE_SIDM_ARYABHATA_MSUN2 = 24;    // Aryabhata, mean Sun variant
    public const SE_SIDM_SS_REVATI = 25;          // SS Revati
    public const SE_SIDM_SS_CITRA = 26;           // SS Citra
    public const SE_SIDM_TRUE_CITRA = 27;         // True Citra
    public const SE_SIDM_TRUE_REVATI = 28;        // True Revati
    public const SE_SIDM_TRUE_PUSHYA = 29;        // True Pushya
    public const SE_SIDM_GALCENT_RGILBRAND = 30; // Galactic Center (Gil Brand)
    public const SE_SIDM_GALEQU_IAU1958 = 31;    // Galactic Equator (IAU1958)
    public const SE_SIDM_GALEQU_TRUE = 32;        // Galactic Equator
    public const SE_SIDM_GALEQU_MULA = 33;        // Galactic Equator mid-Mula
    public const SE_SIDM_GALALIGN_MARDYKS = 34;   // Skydram (Mardyks)
    public const SE_SIDM_TRUE_MULA = 35;          // True Mula (Chandra Hari)
    public const SE_SIDM_GALCENT_MULA_WILHELM = 36; // Dhruva/Gal.Center/Mula (Wilhelm)
    public const SE_SIDM_ARYABHATA_522_ALT = 37;  // Aryabhata 522 alt
    public const SE_SIDM_BABYL_BRITTON = 38;      // Babylonian (Britton)
    public const SE_SIDM_TRUE_SHEORAN = 39;       // "Vedic"/Sheoran
    public const SE_SIDM_GALCENT_COCHRANE = 40;   // Cochrane (Gal.Center = 0 Cap)
    public const SE_SIDM_GALEQU_FIORENZA = 41;    // Galactic Equator (Fiorenza)
    public const SE_SIDM_VALENS_MOON = 42;        // Vettius Valens
    public const SE_SIDM_LAHIRI_1940 = 43;        // Lahiri 1940
    public const SE_SIDM_LAHIRI_VP285 = 44;       // Lahiri VP285
    public const SE_SIDM_KRISHNAMURTI_VP291 = 45; // Krishnamurti-Senthilathiban
    public const SE_SIDM_LAHIRI_ICRC = 46;        // Lahiri ICRC
    public const SE_SIDM_GALCENT_0SAG = 17;       // Alias for BABYLONIAN_ALDEBARAN
    public const SE_SIDM_USER = 255;              // user-defined ayanamsha

    public const SE_NSIDM_PREDEF = 47;            // number of predefined ayanamshas

    // Sidereal option bits (complete set from Swiss Ephemeris)
    public const SE_SIDBITS = 256;                 // mask for sidereal mode bits
    public const SE_SIDBIT_ECL_T0 = 256;           // project onto ecliptic of t0
    public const SE_SIDBIT_SSY_PLANE = 512;        // project onto solar system plane
    public const SE_SIDBIT_USER_UT = 1024;         // user t0 is UT (not TT)
    public const SE_SIDBIT_ECL_DATE = 2048;        // ayanamsha measured on ecliptic of date
    public const SE_SIDBIT_NO_PREC_OFFSET = 4096;  // don't apply constant offset to ayanamsha
    public const SE_SIDBIT_PREC_ORIG = 8192;       // use original precession model for ayanamsha

    // Nodes/Apsides calculation mode bits
    public const SE_NODBIT_MEAN = 1;      // mean nodes/apsides
    public const SE_NODBIT_OSCU = 2;      // osculating nodes/apsides
    public const SE_NODBIT_OSCU_BAR = 4;  // osculating from barycentric ellipses (planets beyond Jupiter)
    public const SE_NODBIT_FOPOINT = 256; // focal point instead of aphelion

    // Precession direction constants
    public const J2000_TO_J = -1;  // Precess from J2000 to date
    public const J_TO_J2000 = 1;   // Precess from date to J2000

    // Azimuth/Altitude conversion modes (compatible with Swiss Ephemeris semantics)
    public const SE_EQU2HOR = 0; // input is equatorial (RA/Dec) -> output horizontal (Az/Alt)
    public const SE_ECL2HOR = 1; // input is ecliptic (lon/lat) -> output horizontal (Az/Alt)
    public const SE_HOR2EQU = 2; // input is horizontal (Az/Alt) -> output equatorial (RA/Dec)
    public const SE_HOR2ECL = 3; // input is horizontal (Az/Alt) -> output ecliptic (lon/lat)

    // Refraction direction
    public const SE_TRUE_TO_APP = 0; // true altitude -> apparent altitude
    public const SE_APP_TO_TRUE = 1; // apparent altitude -> true altitude

    // Split degrees flags (for swe_split_deg)
    public const SE_SPLIT_DEG_ROUND_SEC = 1;    // round to seconds
    public const SE_SPLIT_DEG_ROUND_MIN = 2;    // round to minutes
    public const SE_SPLIT_DEG_ROUND_DEG = 4;    // round to degrees
    public const SE_SPLIT_DEG_ZODIACAL = 8;     // zodiacal format (0-30 degrees per sign)
    public const SE_SPLIT_DEG_NAKSHATRA = 1024; // nakshatra format
    public const SE_SPLIT_DEG_KEEP_SIGN = 16;   // don't round to next sign
    public const SE_SPLIT_DEG_KEEP_DEG = 32;    // don't round to next degree

    // Epoch Julian dates
    public const J2000 = 2451545.0;                 // Julian date of J2000.0 epoch (2000 January 1.5 TT)
    public const B1950 = 2433282.42345905;          // Julian date of B1950.0 epoch (1950.0 Besselian)

    // Physical constants (from Swiss Ephemeris sweph.h)
    public const AUNIT = 1.49597870700e+11;         // AU in meters (DE431)
    public const CLIGHT = 2.99792458e+8;            // Speed of light in m/s (AA 1996 K6)
    public const EARTH_RADIUS = 6378136.6;          // Earth radius in meters (AA 2006 K6)
    public const RADTODEG = 57.2957795130823;       // Radians to degrees conversion
    public const DEGTORAD = 0.0174532925199433;     // Degrees to radians conversion
    public const SE_LAPSE_RATE = 0.0065;            // Temperature lapse rate (deg K/m) for refraction
    public const EARTH_MOON_MRAT = 81.30056907419062; // Earth/Moon mass ratio (DE431)
    public const HELGRAVCONST = 1.32712440017987e+20; // G * M(sun), m^3/sec^2 (AA 2006 K6)
    public const GEOGCONST = 3.98600448e+14;        // G * M(earth), m^3/sec^2 (AA 1996 K6)
    public const NODE_CALC_INTV = 0.0001;           // Interval for node calculation
    public const KM_S_TO_AU_CTY = 21.095;           // km/s to AU/century conversion factor
    public const PLAN_SPEED_INTV = 0.0001;          // 8.64 seconds (in days) for speed calculation
    public const PARSEC_TO_AUNIT = 206264.8062471;  // Parsec to AU conversion (648000/PI, IAU 2016)
    public const SUN_RADIUS = 0.0046542827777777775; // 959.63 / 3600 * DEGTORAD (Meeus p.391)
    public const DEFL_SPEED_INTV = 0.0000005;       // Interval for light deflection speed calculation

    // Ephemeris coverage limits (from sweph.h)
    public const MOSHNDEPH_START = -3100015.5; // 15 Aug -13200 (ET jul)
    public const MOSHNDEPH_END   = 8000016.5;  // 15 Mar 17191 (ET greg)
    public const JPL_DE431_START = -3027215.5;
    public const JPL_DE431_END   = 7930192.5;

    // Astronomical model indices (for astro_models array in SwedState)
    public const SE_MODEL_PREC_LONGTERM = 1;
    public const SE_MODEL_PREC_SHORTTERM = 2;
    public const SE_MODEL_NUT = 3;
    public const SE_MODEL_BIAS = 4;
    public const SE_MODEL_JPLHOR_MODE = 5;
    public const SE_MODEL_JPLHORA_MODE = 6;
    public const SE_MODEL_SIDT = 7;
    public const NSE_MODELS = 8;

    // Precession models
    public const SEMOD_NPREC = 11;
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
    public const SEMOD_PREC_DEFAULT = self::SEMOD_PREC_VONDRAK_2011;
    public const SEMOD_PREC_DEFAULT_SHORT = self::SEMOD_PREC_VONDRAK_2011;

    // Nutation models
    public const SEMOD_NNUT = 5;
    public const SEMOD_NUT_IAU_1980 = 1;
    public const SEMOD_NUT_IAU_CORR_1987 = 2; // Herring's corrections to IAU 1980
    public const SEMOD_NUT_IAU_2000A = 3;     // very time consuming
    public const SEMOD_NUT_IAU_2000B = 4;     // fast, but precision of milli-arcsec
    public const SEMOD_NUT_WOOLARD = 5;
    public const SEMOD_NUT_DEFAULT = self::SEMOD_NUT_IAU_2000B;

    // Sidereal time models
    public const SEMOD_NSIDT = 4;
    public const SEMOD_SIDT_IAU_1976 = 1;
    public const SEMOD_SIDT_IAU_2006 = 2;
    public const SEMOD_SIDT_IERS_CONV_2010 = 3;
    public const SEMOD_SIDT_LONGTERM = 4;
    public const SEMOD_SIDT_DEFAULT = self::SEMOD_SIDT_LONGTERM;

    // Frame bias methods
    public const SEMOD_NBIAS = 3;
    public const SEMOD_BIAS_NONE = 1;           // ignore frame bias
    public const SEMOD_BIAS_IAU2000 = 2;        // IAU 2000 frame bias
    public const SEMOD_BIAS_IAU2006 = 3;        // IAU 2006 frame bias
    public const SEMOD_BIAS_DEFAULT = self::SEMOD_BIAS_IAU2006;
}
