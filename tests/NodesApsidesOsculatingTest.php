<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;

/**
 * Tests for osculating nodes and apsides
 * Validates calcOsculatingNodesApsides() implementation
 */
class NodesApsidesOsculatingTest extends TestCase
{
    /**
     * Test Mars osculating vs mean nodes
     * Osculating should differ from mean due to perturbations
     */
    public function testMarsOsculatingVsMeanNodes(): void
    {
        $tjd = 2451545.0; // J2000.0
        $ipl = Constants::SE_MARS;
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

        // Mean nodes - initialize as empty arrays
        $xnascMean = [];
        $xndscMean = [];
        $xperiMean = [];
        $xapheMean = [];
        $serrMean = null;

        $retMean = swe_nod_aps(
            $tjd,
            $ipl,
            $iflag,
            Constants::SE_NODBIT_MEAN,
            $xnascMean,
            $xndscMean,
            $xperiMean,
            $xapheMean,
            $serrMean
        );

        $this->assertGreaterThanOrEqual(0, $retMean, "Mean nodes should succeed: " . ($serrMean ?? 'no error'));
        $this->assertIsArray($xnascMean, "Mean xnasc should be array, error: " . ($serrMean ?? 'none'));
        $this->assertCount(6, $xnascMean);

        // Osculating nodes - initialize as empty arrays
        $xnascOscu = [];
        $xndscOscu = [];
        $xperiOscu = [];
        $xapheOscu = [];
        $serrOscu = null;

        $retOscu = swe_nod_aps(
            $tjd,
            $ipl,
            $iflag,
            Constants::SE_NODBIT_OSCU,
            $xnascOscu,
            $xndscOscu,
            $xperiOscu,
            $xapheOscu,
            $serrOscu
        );

        $this->assertGreaterThanOrEqual(0, $retOscu, "Osculating nodes should succeed: " . ($serrOscu ?? ''));
        $this->assertIsArray($xnascOscu);
        $this->assertCount(6, $xnascOscu);

        // Osculating should differ from mean
        $diffLon = abs($xnascOscu[0] - $xnascMean[0]);
        $this->assertGreaterThan(0.001, $diffLon, "Osculating node longitude should differ from mean");
        $this->assertLessThan(10.0, $diffLon, "Difference should be reasonable (< 10°)");

        // Distance should be positive
        $this->assertGreaterThan(0, $xnascOscu[2], "Ascending node distance should be positive");
        $this->assertGreaterThan(0, $xperiOscu[2], "Perihelion distance should be positive");

        // If speed requested, check it's non-zero
        $this->assertNotEquals(0.0, $xnascOscu[3], "Node should have speed (dlongitude/dt)");
    }

    /**
     * Test Mercury osculating nodes (high eccentricity)
     * Mercury has highest eccentricity of planets, good test case
     */
    public function testMercuryOsculatingNodes(): void
    {
        $tjd = 2451545.0; // J2000.0
        $ipl = Constants::SE_MERCURY;
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

        $xnasc = [];
        $xndsc = [];
        $xperi = [];
        $xaphe = [];
        $serr = null;

        $ret = swe_nod_aps(
            $tjd,
            $ipl,
            $iflag,
            Constants::SE_NODBIT_OSCU,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $ret, "Mercury osculating should succeed: " . ($serr ?? ''));
        $this->assertIsArray($xnasc);
        $this->assertIsArray($xperi);
        $this->assertIsArray($xaphe);

        // Mercury perihelion distance should be < 1 AU
        $this->assertLessThan(1.0, $xperi[2], "Mercury perihelion < 1 AU");

        // Aphelion > perihelion
        $this->assertGreaterThan($xperi[2], $xaphe[2], "Aphelion distance > perihelion");

        // Eccentricity can be derived from a=(peri+aph)/2, e=(aph-peri)/(aph+peri)
        $semiMajor = ($xperi[2] + $xaphe[2]) / 2.0;
        $eccentricity = ($xaphe[2] - $xperi[2]) / ($xaphe[2] + $xperi[2]);

        // Mercury eccentricity ~ 0.2 (highest of planets)
        $this->assertGreaterThan(0.15, $eccentricity, "Mercury eccentricity should be > 0.15");
        $this->assertLessThan(0.25, $eccentricity, "Mercury eccentricity should be < 0.25");

        // Semi-major axis ~ 0.387 AU
        $this->assertGreaterThan(0.35, $semiMajor, "Mercury semi-major axis > 0.35 AU");
        $this->assertLessThan(0.42, $semiMajor, "Mercury semi-major axis < 0.42 AU");
    }

    /**
     * Test Jupiter osculating with barycentric option
     * For outer planets, barycentric ellipse is more meaningful
     */
    public function testJupiterOsculatingBarycentric(): void
    {
        $tjd = 2451545.0; // J2000.0
        $ipl = Constants::SE_JUPITER;
        $iflag = Constants::SEFLG_SWIEPH;

        // Heliocentric osculating
        $xnascHelio = [];
        $xndscHelio = [];
        $xperiHelio = [];
        $xapheHelio = [];
        $serrHelio = null;

        $retHelio = swe_nod_aps(
            $tjd,
            $ipl,
            $iflag,
            Constants::SE_NODBIT_OSCU,
            $xnascHelio,
            $xndscHelio,
            $xperiHelio,
            $xapheHelio,
            $serrHelio
        );

        $this->assertGreaterThanOrEqual(0, $retHelio, "Jupiter heliocentric osculating should succeed");

        // Barycentric osculating
        $xnascBary = [];
        $xndscBary = [];
        $xperiBary = [];
        $xapheBary = [];
        $serrBary = null;

        $retBary = swe_nod_aps(
            $tjd,
            $ipl,
            $iflag,
            Constants::SE_NODBIT_OSCU_BAR,
            $xnascBary,
            $xndscBary,
            $xperiBary,
            $xapheBary,
            $serrBary
        );

        $this->assertGreaterThanOrEqual(0, $retBary, "Jupiter barycentric osculating should succeed");

        // Both should give reasonable distances for Jupiter (~5 AU)
        $this->assertGreaterThan(4.5, $xperiHelio[2], "Jupiter perihelion > 4.5 AU (helio)");
        $this->assertLessThan(6.0, $xperiHelio[2], "Jupiter perihelion < 6.0 AU (helio)");

        $this->assertGreaterThan(4.5, $xperiBary[2], "Jupiter perihelion > 4.5 AU (bary)");
        $this->assertLessThan(6.0, $xperiBary[2], "Jupiter perihelion < 6.0 AU (bary)");

        // Barycentric and heliocentric should differ slightly
        $diffPeri = abs($xperiBary[2] - $xperiHelio[2]);
        $this->assertLessThan(0.5, $diffPeri, "Barycentric/heliocentric difference should be small");
    }

    /**
     * Test focal point option
     * Focal point is alternative to aphelion (second focus of ellipse)
     */
    public function testFocalPointOption(): void
    {
        $tjd = 2451545.0;
        $ipl = Constants::SE_MARS;
        $iflag = Constants::SEFLG_SWIEPH;

        // Normal aphelion
        $xnascA = [];
        $xndscA = [];
        $xperiA = [];
        $xapheA = []; // This will be aphelion
        $serrA = null;

        $retA = swe_nod_aps(
            $tjd,
            $ipl,
            $iflag,
            Constants::SE_NODBIT_OSCU,
            $xnascA,
            $xndscA,
            $xperiA,
            $xapheA,
            $serrA
        );

        $this->assertGreaterThanOrEqual(0, $retA);

        // Focal point
        $xnascF = [];
        $xndscF = [];
        $xperiF = [];
        $xfocal = []; // This will be focal point
        $serrF = null;

        $retF = swe_nod_aps(
            $tjd,
            $ipl,
            $iflag,
            Constants::SE_NODBIT_OSCU | Constants::SE_NODBIT_FOPOINT,
            $xnascF,
            $xndscF,
            $xperiF,
            $xfocal,
            $serrF
        );

        $this->assertGreaterThanOrEqual(0, $retF);

        // Focal point and aphelion longitudes should differ by 180°
        $diffLon = abs($xfocal[0] - $xapheA[0]);
        $diffLon = abs(swe_degnorm($diffLon)); // Normalize
        // Should be either ~0° (same) or ~180° (opposite)
        // For focal point, should be ~180° from perihelion, same as aphelion
        $this->assertTrue(
            $diffLon < 5.0 || abs($diffLon - 180.0) < 5.0,
            "Focal point should be ~180° from perihelion"
        );

        // Perihelion should be same in both cases
        $this->assertEqualsWithDelta($xperiA[0], $xperiF[0], 0.001, "Perihelion longitude should be same");
    }

    /**
     * Test speed calculation
     * When SEFLG_SPEED is set, derivatives should be non-zero
     */
    public function testOsculatingWithSpeed(): void
    {
        $tjd = 2451545.0;
        $ipl = Constants::SE_VENUS;
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_SPEED;

        $xnasc = [];
        $xndsc = [];
        $xperi = [];
        $xaphe = [];
        $serr = null;

        $ret = swe_nod_aps(
            $tjd,
            $ipl,
            $iflag,
            Constants::SE_NODBIT_OSCU,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $serr
        );

        $this->assertGreaterThanOrEqual(0, $ret);

        // Speed components (indices 3-5) should be non-zero
        // Node precesses slowly
        $this->assertNotEquals(0.0, $xnasc[3], "Ascending node should have dlongitude/dt");

        // Perihelion also precesses
        $this->assertNotEquals(0.0, $xperi[3], "Perihelion should have dlongitude/dt");

        // Speeds should be small but measurable (degrees per day)
        $this->assertLessThan(1.0, abs($xnasc[3]), "Node speed should be < 1°/day");
        $this->assertLessThan(1.0, abs($xperi[3]), "Perihelion speed should be < 1°/day");
    }
}
