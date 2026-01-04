<?php

use Swisseph\Constants as C;

return [

    /*
    |--------------------------------------------------------------------------
    | Ephemeris Files Path
    |--------------------------------------------------------------------------
    |
    | Path to the directory containing Swiss Ephemeris data files (.se1).
    | These files are required for high-precision planetary calculations.
    |
    | Download from: https://www.astro.com/ftp/swisseph/ephe/
    |
    */

    'ephe_path' => env('SWISSEPH_EPHE_PATH', storage_path('app/swisseph/ephe')),

    /*
    |--------------------------------------------------------------------------
    | Default Calculation Flags
    |--------------------------------------------------------------------------
    |
    | Default flags used for planetary calculations.
    | Combine multiple flags using bitwise OR (|).
    |
    | Common flags:
    | - SEFLG_SWIEPH: Use Swiss Ephemeris files
    | - SEFLG_SPEED: Calculate daily motion
    | - SEFLG_EQUATORIAL: Return RA/Dec instead of Lon/Lat
    | - SEFLG_TOPOCTR: Use topocentric coordinates
    | - SEFLG_SIDEREAL: Use sidereal zodiac
    |
    */

    'default_flags' => C::SEFLG_SWIEPH | C::SEFLG_SPEED,

    /*
    |--------------------------------------------------------------------------
    | Sidereal Mode Configuration
    |--------------------------------------------------------------------------
    |
    | Configure sidereal/tropical zodiac settings.
    |
    | Popular ayanamshas:
    | - SE_SIDM_FAGAN_BRADLEY: Western sidereal (Fagan-Bradley)
    | - SE_SIDM_LAHIRI: Vedic astrology (Lahiri)
    | - SE_SIDM_RAMAN: K.S. Krishnamurti
    | - SE_SIDM_KRISHNAMURTI: Krishnamurti ayanamsha
    |
    */

    'sidereal_mode' => env('SWISSEPH_SIDEREAL_MODE', C::SE_SIDM_LAHIRI),
    'enable_sidereal' => env('SWISSEPH_ENABLE_SIDEREAL', false),
    'sidereal_t0' => 0.0,
    'sidereal_ayan_t0' => 0.0,

    /*
    |--------------------------------------------------------------------------
    | Topocentric Configuration
    |--------------------------------------------------------------------------
    |
    | Observer location for topocentric calculations (geocentric parallax).
    | Coordinates in degrees, altitude in meters above sea level.
    |
    | Example (London): longitude: -0.1276, latitude: 51.5074, altitude: 11
    |
    */

    'topocentric' => [
        'enabled' => env('SWISSEPH_TOPOCENTRIC_ENABLED', false),
        'longitude' => env('SWISSEPH_TOPOCENTRIC_LON', 0.0),
        'latitude' => env('SWISSEPH_TOPOCENTRIC_LAT', 0.0),
        'altitude' => env('SWISSEPH_TOPOCENTRIC_ALT', 0.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Enable caching for planetary positions to improve performance.
    | Cached results are stored per Julian Day + planet + flags combination.
    |
    */

    'cache' => [
        'enabled' => env('SWISSEPH_CACHE_ENABLED', false),
        'ttl' => env('SWISSEPH_CACHE_TTL', 86400), // 24 hours in seconds
        'driver' => env('SWISSEPH_CACHE_DRIVER', 'file'),
    ],

];
