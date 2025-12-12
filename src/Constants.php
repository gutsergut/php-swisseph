<?php

namespace Swisseph;

final class Constants
{
    public const SE_JUL_CAL = 0;
    public const SE_GREG_CAL = 1;
    // Error codes
    public const SE_OK = 0;    // success / no error
    public const OK = 0;       // alias for heliacal modules compatibility
    public const SE_ERR = -1; // generic error code to mirror Swiss Ephemeris style
    public const ERR = -1;     // alias for heliacal modules compatibility

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
    public const SE_CHIRON = 15;
    public const SE_PHOLUS = 16;
    public const SE_CERES = 17;
    public const SE_PALLAS = 18;
    public const SE_JUNO = 19;
    public const SE_VESTA = 20;
    public const SE_INTP_APOG = 21;  // Interpolated lunar apogee
    public const SE_INTP_PERG = 22;  // Interpolated lunar perigee
    public const SE_NPLANETS = 23;   // Number of planets

    // Special calculation object for ecliptic and nutation (swephexp.h:99)
    public const SE_ECL_NUT = -1;

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
    // Custom extension: use VSOP87 analytical series as source (exclusive with SWIEPH/JPLEPH/MOSEPH)
    // Bit chosen outside original Swiss Ephemeris range to avoid collision
    public const SEFLG_VSOP87 = (2048*1024); // 2097152

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

    // Azimuth/Altitude conversion modes (from swephexp.h:363-368)
    // Note: ECL and EQU have same values for forward/reverse to match C API
    public const SE_ECL2HOR = 0; // input is ecliptic (lon/lat) -> output horizontal (Az/Alt)
    public const SE_EQU2HOR = 1; // input is equatorial (RA/Dec) -> output horizontal (Az/Alt)
    public const SE_HOR2ECL = 0; // input is horizontal (Az/Alt) -> output ecliptic (lon/lat)
    public const SE_HOR2EQU = 1; // input is horizontal (Az/Alt) -> output equatorial (RA/Dec)

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

    // DE ephemeris number (default)
    public const SE_DE_NUMBER = 431;                // Default ephemeris version (DE431)

    // Physical constants (from Swiss Ephemeris sweph.h)
    public const AUNIT = 1.49597870700e+11;         // AU in meters (DE431)
    public const CLIGHT = 2.99792458e+8;            // Speed of light in m/s (AA 1996 K6)
    public const EARTH_RADIUS = 6378136.6;          // Earth radius in meters (AA 2006 K6)
    public const EARTH_OBLATENESS = 0.0033528106647474807; // 1.0 / 298.25642 (AA 2006 K6)
    public const EARTH_ROT_SPEED = 6.30038061277862; // 7.2921151467e-5 * 86400 (rad/day)

    // Eclipse-specific constants (swecl.c:84-91)
    // NOTE: These are in AU, not meters! The C code divides by AUNIT
    public const DMOON = 3476300.0 / self::AUNIT;   // Moon diameter in AU (swecl.c:87: 3476300.0 / AUNIT)
    public const RMOON = self::DMOON / 2.0;         // Moon radius in AU (swecl.c:89: DMOON / 2)
    public const DSUN = 1392000000.0 / self::AUNIT; // Sun diameter in AU (swecl.c:85: 1392000000.0 / AUNIT)
    public const RSUN = self::DSUN / 2.0;           // Sun radius in AU (swecl.c:89: DSUN / 2)
    public const DEARTH = 6378140.0 * 2 / self::AUNIT; // Earth diameter in AU (swecl.c:88)
    public const REARTH = self::DEARTH / 2.0;       // Earth radius in AU (swecl.c:90)

    // Number of planets with known diameters (sweph.h:314)
    public const NDIAM = 19; // SE_VESTA + 1 (up to asteroid Vesta)

    // Planetary diameters in meters (sweph.h:315-327)
    // Indexed by planet number (SE_SUN, SE_MOON, etc.)
    public const PLA_DIAM = [
        1392000000.0,  // SE_SUN (0)
        3475000.0,     // SE_MOON (1)
        4878800.0,     // SE_MERCURY (2) - diameter = 2 * radius
        12103600.0,    // SE_VENUS (3)
        6779000.0,     // SE_MARS (4)
        139822000.0,   // SE_JUPITER (5)
        116464000.0,   // SE_SATURN (6)
        50724000.0,    // SE_URANUS (7)
        49244000.0,    // SE_NEPTUNE (8)
        2376600.0,     // SE_PLUTO (9)
        0.0,           // SE_MEAN_NODE (10) - no diameter
        0.0,           // SE_TRUE_NODE (11)
        0.0,           // SE_MEAN_APOG (12)
        0.0,           // SE_OSCU_APOG (13)
        12742016.8,    // SE_EARTH (14)
        271370.0,      // SE_CHIRON (15)
        290000.0,      // SE_PHOLUS (16)
        939400.0,      // SE_CERES (17)
        545000.0,      // SE_PALLAS (18)
    ];

    public const RADTODEG = 57.2957795130823;       // Radians to degrees conversion
    public const DEGTORAD = 0.0174532925199433;     // Degrees to radians conversion
    public const SE_LAPSE_RATE = 0.0065;            // Temperature lapse rate (deg K/m) for refraction
    public const EARTH_MOON_MRAT = 81.30056907419062; // Earth/Moon mass ratio (DE431)
    public const HELGRAVCONST = 1.32712440017987e+20; // G * M(sun), m^3/sec^2 (AA 2006 K6)
    public const GEOGCONST = 3.98600448e+14;        // G * M(earth), m^3/sec^2 (AA 1996 K6)
    public const NODE_CALC_INTV = 0.0001;           // Interval for node calculation
    public const KM_S_TO_AU_CTY = 21.095;           // km/s to AU/century conversion factor
    public const PLAN_SPEED_INTV = 0.0001;          // 8.64 seconds (in days) for speed calculation
    // Interval for nutation speed calculation (days), matches NUT_SPEED_INTV in C
    public const NUT_SPEED_INTV = 0.0001;
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

    // Fixed star catalog filenames (swephexp.h:386-387)
    public const SE_STARFILE_OLD = 'fixstars.cat';  // Old binary format
    public const SE_STARFILE = 'sefstars.txt';      // New text format

    // Eclipse computation constants (swephexp.h:307-330)
    // Eclipse types
    public const SE_ECL_CENTRAL = 1;
    public const SE_ECL_NONCENTRAL = 2;
    public const SE_ECL_TOTAL = 4;
    public const SE_ECL_ANNULAR = 8;
    public const SE_ECL_PARTIAL = 16;
    public const SE_ECL_ANNULAR_TOTAL = 32;
    public const SE_ECL_HYBRID = 32;  // = annular-total
    public const SE_ECL_PENUMBRAL = 64;

    // Eclipse type combinations
    public const SE_ECL_ALLTYPES_SOLAR = self::SE_ECL_CENTRAL | self::SE_ECL_NONCENTRAL |
                                          self::SE_ECL_TOTAL | self::SE_ECL_ANNULAR |
                                          self::SE_ECL_PARTIAL | self::SE_ECL_ANNULAR_TOTAL;
    public const SE_ECL_ALLTYPES_LUNAR = self::SE_ECL_TOTAL | self::SE_ECL_PARTIAL |
                                          self::SE_ECL_PENUMBRAL;

    // Eclipse visibility flags
    public const SE_ECL_VISIBLE = 128;
    public const SE_ECL_MAX_VISIBLE = 256;
    public const SE_ECL_1ST_VISIBLE = 512;       // begin of partial eclipse
    public const SE_ECL_PARTBEG_VISIBLE = 512;   // begin of partial eclipse
    public const SE_ECL_2ND_VISIBLE = 1024;      // begin of total eclipse
    public const SE_ECL_TOTBEG_VISIBLE = 1024;   // begin of total eclipse
    public const SE_ECL_3RD_VISIBLE = 2048;      // end of total eclipse
    public const SE_ECL_TOTEND_VISIBLE = 2048;   // end of total eclipse
    public const SE_ECL_4TH_VISIBLE = 4096;      // end of partial eclipse
    public const SE_ECL_PARTEND_VISIBLE = 4096;  // end of partial eclipse
    public const SE_ECL_PENUMBBEG_VISIBLE = 8192;   // begin of penumbral eclipse
    public const SE_ECL_PENUMBEND_VISIBLE = 16384;  // end of penumbral eclipse
    public const SE_ECL_OCC_BEG_DAYLIGHT = 8192;    // occultation begins during the day
    public const SE_ECL_OCC_END_DAYLIGHT = 16384;   // occultation ends during the day
    public const SE_ECL_ONE_TRY = 32768;  // check only next conjunction, don't search further

    // Geographic altitude limits for eclipse calculations (from sweph.h:198-199)
    public const SEI_ECL_GEOALT_MAX = 25000.0;    // maximum altitude in meters
    public const SEI_ECL_GEOALT_MIN = -500.0;     // minimum altitude in meters

    // Heliacal event flags (from swephexp.h:434-449 + heliacal module)
    public const SE_HELFLAG_LONG_SEARCH = 128;          // Extended search period (up to 20 synodic periods)
    public const SE_HELFLAG_HIGH_PRECISION = 256;       // High precision mode (use nutation)
    public const SE_HELFLAG_OPTICAL_PARAMS = 512;       // Use optical instrument parameters (telescope)
    public const SE_HELFLAG_NO_DETAILS = 1024;          // Don't return detailed data (t_optimum, t_last)
    public const SE_HELFLAG_SEARCH_1_PERIOD = 2048;     // Search only 1 synodic period
    public const SE_HELFLAG_VISLIM_DARK = 4096;         // Visibility limit for dark conditions
    public const SE_HELFLAG_VISLIM_NOMOON = 8192;       // Visibility limit without moon
    public const SE_HELFLAG_VISLIM_PHOTOPIC = 16384;    // Force photopic (light-adapted) vision
    public const SE_HELFLAG_VISLIM_SCOTOPIC = 32768;    // Force scotopic (dark-adapted) vision
    public const SE_HELFLAG_AV = 65536;                 // Use arcus visionis method
    public const SE_HELFLAG_AVKIND_VR = 65536;          // Arcus visionis: VR method (minimum TAV walk)
    public const SE_HELFLAG_AVKIND_PTO = 131072;        // Arcus visionis: PTO method (Ptolemaic horizon) (1 << 17)
    public const SE_HELFLAG_AVKIND_MIN7 = 262144;       // Arcus visionis: MIN7 method (solar alt -7°) (1 << 18)
    public const SE_HELFLAG_AVKIND_MIN9 = 524288;       // Arcus visionis: MIN9 method (solar alt -9°) (1 << 19)
    public const SE_HELFLAG_AVKIND = 983040;            // All arcus visionis methods (VR|PTO|MIN7|MIN9)

    // Heliacal event types (from internal code flow)
    public const SE_ACRONYCHAL_RISING = 5;              // Acronychal rising (object rises as sun sets)
    public const SE_ACRONYCHAL_SETTING = 6;             // Acronychal setting (object sets as sun rises)
}

