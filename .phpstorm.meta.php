<?php

namespace PHPSTORM_META {

    use Swisseph\Constants;
    use Swisseph\Swe\Functions\PlanetsFunctions;
    use Swisseph\Swe\Functions\HousesFunctions;
    use Swisseph\Swe\Functions\SiderealFunctions;

    // Constants autocomplete for planet IDs
    expectedArguments(
        \Swisseph\Swe\Functions\PlanetsFunctions::calc(),
        1,
        Constants::SE_SUN,
        Constants::SE_MOON,
        Constants::SE_MERCURY,
        Constants::SE_VENUS,
        Constants::SE_MARS,
        Constants::SE_JUPITER,
        Constants::SE_SATURN,
        Constants::SE_URANUS,
        Constants::SE_NEPTUNE,
        Constants::SE_PLUTO,
        Constants::SE_MEAN_NODE,
        Constants::SE_TRUE_NODE,
        Constants::SE_MEAN_APOG,
        Constants::SE_OSCU_APOG,
        Constants::SE_EARTH,
        Constants::SE_CHIRON,
        Constants::SE_PHOLUS,
        Constants::SE_CERES,
        Constants::SE_PALLAS,
        Constants::SE_JUNO,
        Constants::SE_VESTA,
        Constants::SE_INTP_APOG,
        Constants::SE_INTP_PERG
    );

    expectedArguments(
        \Swisseph\Swe\Functions\PlanetsFunctions::calcUt(),
        1,
        Constants::SE_SUN,
        Constants::SE_MOON,
        Constants::SE_MERCURY,
        Constants::SE_VENUS,
        Constants::SE_MARS,
        Constants::SE_JUPITER,
        Constants::SE_SATURN,
        Constants::SE_URANUS,
        Constants::SE_NEPTUNE,
        Constants::SE_PLUTO,
        Constants::SE_MEAN_NODE,
        Constants::SE_TRUE_NODE,
        Constants::SE_MEAN_APOG,
        Constants::SE_OSCU_APOG,
        Constants::SE_EARTH,
        Constants::SE_CHIRON
    );

    // Calculation flags autocomplete
    expectedArguments(
        \Swisseph\Swe\Functions\PlanetsFunctions::calc(),
        2,
        Constants::SEFLG_JPLEPH,
        Constants::SEFLG_SWIEPH,
        Constants::SEFLG_MOSEPH,
        Constants::SEFLG_HELCTR,
        Constants::SEFLG_TRUEPOS,
        Constants::SEFLG_J2000,
        Constants::SEFLG_NONUT,
        Constants::SEFLG_SPEED,
        Constants::SEFLG_SPEED3,
        Constants::SEFLG_NOGDEFL,
        Constants::SEFLG_NOABERR,
        Constants::SEFLG_ASTROMETRIC,
        Constants::SEFLG_EQUATORIAL,
        Constants::SEFLG_XYZ,
        Constants::SEFLG_RADIANS,
        Constants::SEFLG_BARYCTR,
        Constants::SEFLG_TOPOCTR,
        Constants::SEFLG_SIDEREAL,
        Constants::SEFLG_ICRS,
        Constants::SEFLG_DPSIDEPS_1980,
        Constants::SEFLG_JPLHOR,
        Constants::SEFLG_JPLHOR_APPROX,
        Constants::SEFLG_CENTER_BODY,
        Constants::SEFLG_TEST_PLMOON
    );

    expectedArguments(
        \Swisseph\Swe\Functions\PlanetsFunctions::calcUt(),
        2,
        Constants::SEFLG_JPLEPH,
        Constants::SEFLG_SWIEPH,
        Constants::SEFLG_MOSEPH,
        Constants::SEFLG_HELCTR,
        Constants::SEFLG_TRUEPOS,
        Constants::SEFLG_J2000,
        Constants::SEFLG_NONUT,
        Constants::SEFLG_SPEED,
        Constants::SEFLG_NOGDEFL,
        Constants::SEFLG_NOABERR,
        Constants::SEFLG_EQUATORIAL,
        Constants::SEFLG_XYZ,
        Constants::SEFLG_RADIANS,
        Constants::SEFLG_TOPOCTR,
        Constants::SEFLG_SIDEREAL
    );

    // House systems autocomplete
    expectedArguments(
        \Swisseph\Swe\Functions\HousesFunctions::houses(),
        3,
        'P', // Placidus
        'K', // Koch
        'O', // Porphyrius
        'R', // Regiomontanus
        'C', // Campanus
        'A', // Equal (ascendant)
        'E', // Equal
        'V', // Vehlow equal
        'W', // Whole sign
        'X', // Axial rotation system
        'H', // Horizontal system
        'T', // Polich/Page
        'B', // Alcabitus
        'M', // Morinus
        'U', // Krusinski-Pisa-Goelzer
        'G', // Gauquelin sectors
        'I', // Sunshine houses
        'i', // Sunshine alternative
        'N', // Whole sign equal to Aries
        'Y', // APC houses
        'L', // Pullen SD
        'Q', // Pullen SR
        'S', // Sripati
        'J', // Savard-A
    );

    expectedArguments(
        \Swisseph\Swe\Functions\HousesFunctions::housesEx(),
        4,
        'P', 'K', 'O', 'R', 'C', 'A', 'E', 'V', 'W', 'X', 'H', 'T', 'B', 'M', 'U', 'G', 'I', 'i', 'N', 'Y', 'L', 'Q', 'S', 'J'
    );

    expectedArguments(
        \Swisseph\Swe\Functions\HousesFunctions::housesEx2(),
        4,
        'P', 'K', 'O', 'R', 'C', 'A', 'E', 'V', 'W', 'X', 'H', 'T', 'B', 'M', 'U', 'G', 'I', 'i', 'N', 'Y', 'L', 'Q', 'S', 'J'
    );

    expectedArguments(
        \Swisseph\Swe\Functions\HousesFunctions::housesArmc(),
        2,
        'P', 'K', 'O', 'R', 'C', 'A', 'E', 'V', 'W', 'X', 'H', 'T', 'B', 'M', 'U', 'G', 'I', 'i', 'N', 'Y', 'L', 'Q', 'S', 'J'
    );

    expectedArguments(
        \Swisseph\Swe\Functions\HousesFunctions::housesArmcEx2(),
        3,
        'P', 'K', 'O', 'R', 'C', 'A', 'E', 'V', 'W', 'X', 'H', 'T', 'B', 'M', 'U', 'G', 'I', 'i', 'N', 'Y', 'L', 'Q', 'S', 'J'
    );

    // Sidereal mode IDs
    expectedArguments(
        \Swisseph\Swe\Functions\SiderealFunctions::setSidMode(),
        0,
        Constants::SE_SIDM_FAGAN_BRADLEY,
        Constants::SE_SIDM_LAHIRI,
        Constants::SE_SIDM_DELUCE,
        Constants::SE_SIDM_RAMAN,
        Constants::SE_SIDM_USHASHASHI,
        Constants::SE_SIDM_KRISHNAMURTI,
        Constants::SE_SIDM_DJWHAL_KHUL,
        Constants::SE_SIDM_YUKTESHWAR,
        Constants::SE_SIDM_JN_BHASIN,
        Constants::SE_SIDM_BABYL_KUGLER1,
        Constants::SE_SIDM_BABYL_KUGLER2,
        Constants::SE_SIDM_BABYL_KUGLER3,
        Constants::SE_SIDM_BABYL_HUBER,
        Constants::SE_SIDM_BABYL_ETPSC,
        Constants::SE_SIDM_ALDEBARAN_15TAU,
        Constants::SE_SIDM_HIPPARCHOS,
        Constants::SE_SIDM_SASSANIAN,
        Constants::SE_SIDM_GALCENT_0SAG,
        Constants::SE_SIDM_J2000,
        Constants::SE_SIDM_J1900,
        Constants::SE_SIDM_B1950,
        Constants::SE_SIDM_SURYASIDDHANTA,
        Constants::SE_SIDM_SURYASIDDHANTA_MSUN,
        Constants::SE_SIDM_ARYABHATA,
        Constants::SE_SIDM_ARYABHATA_MSUN,
        Constants::SE_SIDM_SS_REVATI,
        Constants::SE_SIDM_SS_CITRA,
        Constants::SE_SIDM_TRUE_CITRA,
        Constants::SE_SIDM_TRUE_REVATI,
        Constants::SE_SIDM_TRUE_PUSHYA,
        Constants::SE_SIDM_GALCENT_RGILBRAND,
        Constants::SE_SIDM_GALEQU_IAU1958,
        Constants::SE_SIDM_GALEQU_TRUE,
        Constants::SE_SIDM_GALEQU_MULA,
        Constants::SE_SIDM_GALALIGN_MARDYKS,
        Constants::SE_SIDM_TRUE_MULA,
        Constants::SE_SIDM_GALCENT_MULA_WILHELM,
        Constants::SE_SIDM_ARYABHATA_522,
        Constants::SE_SIDM_BABYL_BRITTON,
        Constants::SE_SIDM_TRUE_SHEORAN,
        Constants::SE_SIDM_GALCENT_COCHRANE,
        Constants::SE_SIDM_GALEQU_FIORENZA,
        Constants::SE_SIDM_VALENS_MOON,
        Constants::SE_SIDM_LAHIRI_1940,
        Constants::SE_SIDM_LAHIRI_VP285,
        Constants::SE_SIDM_KRISHNAMURTI_VP291,
        Constants::SE_SIDM_LAHIRI_ICRC,
        Constants::SE_SIDM_USER
    );

    // Calendar type
    expectedArguments(
        \Swisseph\Swe\Functions\DateFunctions::julday(),
        4,
        Constants::SE_JUL_CAL,
        Constants::SE_GREG_CAL
    );

    expectedArguments(
        \Swisseph\Swe\Functions\DateFunctions::revjul(),
        2,
        Constants::SE_JUL_CAL,
        Constants::SE_GREG_CAL
    );

    // Eclipse types
    expectedArguments(
        \Swisseph\Swe\Functions\SolarEclipseWhenGlobFunctions::solEclipseWhenGlob(),
        2,
        Constants::SE_ECL_CENTRAL,
        Constants::SE_ECL_NONCENTRAL,
        Constants::SE_ECL_TOTAL,
        Constants::SE_ECL_ANNULAR,
        Constants::SE_ECL_PARTIAL,
        Constants::SE_ECL_ANNULAR_TOTAL,
        Constants::SE_ECL_PENUMBRAL,
        Constants::SE_ECL_ALLTYPES_SOLAR,
        Constants::SE_ECL_ALLTYPES_LUNAR
    );

    // Return type hints for arrays
    override(
        \Swisseph\Swe\Functions\PlanetsFunctions::calc(3),
        type(0) // array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
    );

    override(
        \Swisseph\Swe\Functions\PlanetsFunctions::calcUt(3),
        type(0)
    );

    override(
        \Swisseph\Swe\Functions\HousesFunctions::houses(4),
        type(0)
    );

    // Exit arguments - out parameters are arrays
    exitPoint(
        \Swisseph\Swe\Functions\PlanetsFunctions::calc(),
        [
            '@&$xx' => 'array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}',
            '@&$serr' => 'string|null'
        ]
    );

    exitPoint(
        \Swisseph\Swe\Functions\PlanetsFunctions::calcUt(),
        [
            '@&$xx' => 'array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}',
            '@&$serr' => 'string|null'
        ]
    );

    exitPoint(
        \Swisseph\Swe\Functions\HousesFunctions::houses(),
        [
            '@&$cusps' => 'array<int, float>',
            '@&$ascmc' => 'array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float, 7: float, 8?: float, 9?: float}'
        ]
    );

}
