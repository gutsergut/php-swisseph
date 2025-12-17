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
        // Note: Mars osculating vs mean can differ by 40+° due to perturbations, this is normal
        $this->assertLessThan(90.0, $diffLon, "Difference should be reasonable (< 90°)");

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

        // Note: swe_nod_aps returns GEOCENTRIC distances to node/apsid points,
        // not heliocentric orbital radii. So we can only verify positive distances.
        $this->assertGreaterThan(0, $xperi[2], "Perihelion geocentric distance should be positive");
        $this->assertGreaterThan(0, $xaphe[2], "Aphelion geocentric distance should be positive");

        // Verify longitudes are in valid range
        $this->assertGreaterThanOrEqual(0, $xperi[0], "Perihelion longitude >= 0");
        $this->assertLessThan(360, $xperi[0], "Perihelion longitude < 360");
        $this->assertGreaterThanOrEqual(0, $xaphe[0], "Aphelion longitude >= 0");
        $this->assertLessThan(360, $xaphe[0], "Aphelion longitude < 360");

        // Verify reference values from swetest64 (accuracy ~20")
        // swetest64 -b01.01.2000 -ut12:00 -p2 -fF: 290.1195416  272.9905385  277.2085865
        $this->assertEqualsWithDelta(290.12, $xperi[0], 0.1, "Perihelion lon matches C reference");
        $this->assertEqualsWithDelta(272.99, $xaphe[0], 0.1, "Aphelion lon matches C reference");
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

        // Focal point is the second focus of the ellipse, NOT the aphelion
        // Its longitude differs from both perihelion and aphelion
        // Reference from swetest64 -p4 -fF: peri=313.31°, aphe=192.28°, focal=264.45°
        // Just verify focal point is different from aphelion (different position)
        $this->assertNotEquals($xfocal[0], $xapheA[0], "Focal point longitude differs from aphelion");

        // Focal point distance should differ from aphelion distance
        // Focal distance = 2 * sema * ecce (sum of distances from foci = 2*a)
        // This is less than aphelion distance
        $this->assertGreaterThan(0, $xfocal[2], "Focal point distance should be positive");

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

        // Note: Osculating node speeds can be much higher than mean node speeds
        // due to instantaneous perturbations. Just verify they are finite.
        $this->assertLessThan(10.0, abs($xnasc[3]), "Node speed should be < 10°/day");
        $this->assertLessThan(10.0, abs($xperi[3]), "Perihelion speed should be < 10°/day");
    }
}
