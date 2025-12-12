<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

/**
 * Constants for heliacal rising/setting calculations
 * Port from swehel.c lines 78-144
 *
 * Heliacal events: first/last visibility of celestial objects near sunrise/sunset
 * Based on Schaefer's visibility model and atmospheric physics
 */
class HeliacalConstants
{
    // Time conversion constants
    public const Y2D = 365.25;           // Days per year
    public const D2Y = 1.0 / self::Y2D;  // Years per day
    public const D2H = 24.0;             // Hours per day
    public const H2S = 3600.0;           // Seconds per hour
    public const D2S = self::D2H * self::H2S;  // Seconds per day
    public const S2H = 1.0 / self::H2S;  // Hours per second
    public const JC2D = 36525.0;         // Days per Julian century
    public const M2S = 60.0;             // Seconds per minute

    // Refraction algorithm choice
    public const REFR_SINCLAIR = 0;      // Sinclair refraction model
    public const REFR_BENNETTH = 1;      // Bennett refraction model
    public const FORM_ASTRO_REFRAC = self::REFR_SINCLAIR;

    // Physical model sources
    public const GRAVITY_SOURCE = 2;     // 0=RGO, 1=Wikipedia, 2=Exp.Suppl.1992, 3=van der Werf
    public const R_EARTH_SOURCE = 1;     // 0=RGO(constant), 1=WGS84 method

    // Delta T model parameters
    public const START_YEAR = 1820;      // [year]
    public const AVERAGE = 1.80546834626888;     // [msec/cy]
    public const PERIODICY = 1443.67123144531;   // [year]
    public const AMPLITUDE = 3.75606495492684;   // [msec]
    public const PHASE = 0.0;            // [deg]

    // Search parameters
    public const MAX_COUNT_SYNPER = 5;            // Search within 5 synodic periods
    public const MAX_COUNT_SYNPER_MAX = 1000000;  // Maximum count (essentially no limit)
    public const AVG_RADIUS_MOON = 15.541 / 60.0; // [Deg] at 2007 CE or BCE

    // WGS84 ellipsoid constants (https://w3sli.wcape.gov.za/Surveys/Mapping/wgs84.htm)
    public const RA = 6378136.6;         // Equatorial radius [m]
    public const RB = 6356752.314;       // Polar radius [m]

    // Schaefer's model constants for light/vision
    public const NL2ERG = 1.02E-15;                    // nanoLambert to erg conversion
    public const ERG2NL = 1.0 / self::NL2ERG;          // erg to nanoLambert
    public const MOON_DISTANCE = 384410.4978;          // [km]
    public const SCALE_H_WATER = 3000.0;               // [m] Water vapor scale height (Ricchiazzi 1997: 8200, Schaefer 2000: 3000)
    public const SCALE_H_RAYLEIGH = 8515.0;            // [m] Rayleigh scattering scale height (Su 2003: 8515, Schaefer 2000: 8200)
    public const SCALE_H_AEROSOL = 3745.0;             // [m] Aerosol scale height (Su 2003: 3745, Schaefer 2000: 1500)
    public const SCALE_H_OZONE = 20000.0;              // [m] Ozone scale height (Schaefer 2000)
    public const ASTR2TAU = 0.921034037197618;         // LN(10^0.4) - magnitude to optical depth conversion
    public const TAU2ASTR = 1.0 / self::ASTR2TAU;      // Optical depth to magnitude

    // Meteorological constants
    public const C2K = 273.15;           // Celsius to Kelvin conversion
    public const DELTA = 18.36;          // Meteorological constant
    public const TEMP_NUL_DIFF = 0.000001;  // Temperature difference threshold
    public const PRESS_REF = 1000.0;     // Reference pressure [mbar]
    public const MD = 28.964;            // Molecular weight of dry air [kg/kmol] (van der Werf)
    public const MW = 18.016;            // Molecular weight of water vapor [kg/kmol]
    public const GCR = 8314.472;         // Gas constant [J/(kmol*K)] (van der Werf)
    public const LAPSE_SA = 0.0065;      // Standard atmosphere lapse rate [K/m]
    public const LAPSE_DA = 0.0098;      // Dry adiabatic lapse rate [K/m]

    // Visibility limits
    public const LOWEST_APP_ALT = -3.5;  // Lowest apparent altitude to calculate [Deg]
    public const BNIGHT = 1479.0;        // Sky brightness threshold [nL]
    public const BNIGHT_FACTOR = 1.0;    // Night brightness factor
    public const CRITICAL_ANGLE = 0.0;   // Critical angle [deg]

    // Optimization parameters
    public const EPSILON = 0.001;        // Optimization delta
    public const STATIC_AIRMASS = 0;     // Use static airmass (0=no, 1=yes)
    public const MAX_TRY_HOURS = 4;      // Maximum hours to try
    public const TIME_STEP_DEFAULT = 1;  // Default time step
    public const LOCAL_MIN_STEP = 8;     // Local minimum step
    public const DONE = 1;               // Completion flag

    // Optical instrument defaults
    public const G_OPTIC_MAG = 1.0;      // Telescope magnification
    public const G_OPTIC_TRANS = 0.8;    // Telescope transmission
    public const G_BINOCULAR = 1;        // 1=binocular, 0=monocular
    public const G_OPTIC_DIA = 50.0;     // Telescope diameter [mm]

    // Vision model flags
    public const PLSV = 0;               // Planet, Lunar and Stellar Visibility formula (0=disabled, 1=enabled)
}
