<?php

declare(strict_types=1);

namespace Swisseph\Domain\NodesApsides;

use Swisseph\Coordinates;
use Swisseph\Nutation;
use Swisseph\NutationMatrix;
use Swisseph\Obliquity;
use Swisseph\Precession;
use Swisseph\Constants;

/**
 * Coordinate transformation for nodes and apsides
 *
 * Port of coordinate transformation section from swecl.c (lines 5210-5600)
 * Transforms from mean ecliptic to true ecliptic of date:
 * 1. polar → cartesian
 * 2. ecliptic → equator (rotation by obliquity)
 * 3. mean equator of date → J2000 (precession)
 * 4. J2000 → mean equator of date (precession)
 * 5. mean equator → true equator (nutation)
 * 6. equator → ecliptic (rotation by obliquity)
 * 7. mean ecliptic → true ecliptic (nutation)
 */
class CoordinateTransformer
{
    /**
     * Transform mean nodes/apsides from orbital plane to true ecliptic of date
     *
     * @param float $tjdEt Julian day ET/TT
     * @param array $xnasc Ascending node [lon, lat, dist, dlon, dlat, ddist] in degrees
     * @param array $xndsc Descending node [lon, lat, dist, dlon, dlat, ddist] in degrees
     * @param array $xperi Perihelion [lon, lat, dist, dlon, dlat, ddist] in degrees
     * @param array $xaphe Aphelion/focal point [lon, lat, dist, dlon, dlat, ddist] in degrees
     * @param int $iflag Calculation flags
     * @return void (modifies arrays by reference)
     */
    public static function transformMeanToTrue(
        float $tjdEt,
        array &$xnasc,
        array &$xndsc,
        array &$xperi,
        array &$xaphe,
        int $iflag,
        bool $isTrueNodaps = false  // For mean nodes this is FALSE
    ): void {
        // Port of swecl.c lines 5227-5618
        // Transform coordinates from mean ecliptic to true ecliptic

        $isJ2000 = (bool)($iflag & Constants::SEFLG_J2000);
        $withNutation = !($iflag & Constants::SEFLG_NONUT) && $isTrueNodaps;        // Step 1: Convert degrees to radians and polar to cartesian (lines 5227-5234)
        $xx = [$xnasc, $xndsc, $xperi, $xaphe];

        for ($i = 0; $i < 4; $i++) {
            // Convert degrees to radians
            $xx[$i][0] = deg2rad($xx[$i][0]);
            $xx[$i][1] = deg2rad($xx[$i][1]);
            $xx[$i][3] = deg2rad($xx[$i][3]);
            $xx[$i][4] = deg2rad($xx[$i][4]);

            // Polar to cartesian with speed
            Coordinates::polCartSp($xx[$i], $xx[$i]);
        }

        // Step 2: Get obliquity for the appropriate epoch (lines 5427-5430)
        if ($isJ2000) {
            $eps = Obliquity::meanObliquityRadFromJdTT(Constants::J2000);
        } else {
            $eps = Obliquity::meanObliquityRadFromJdTT($tjdEt);
        }
        $seps = sin($eps);
        $ceps = cos($eps);

        // Step 3: Transformations shared by mean and osculating (lines 5433-5600)
        for ($i = 0; $i < 4; $i++) {
            // For mean nodes: is_true_nodaps = FALSE, so skip nutation in this step (line 5444)
            // We only do nutation AFTER precession (see line 5550)

            // Transform position to equator (line 5449)
            $pos = [$xx[$i][0], $xx[$i][1], $xx[$i][2]];
            $posOut = [];
            Coordinates::coortrf2($pos, $posOut, -$seps, $ceps);

            // Transform speed to equator (line 5450)
            $speed = [$xx[$i][3], $xx[$i][4], $xx[$i][5]];
            $speedOut = [];
            Coordinates::coortrf2($speed, $speedOut, -$seps, $ceps);

            // Reassemble
            $xx[$i] = [
                $posOut[0], $posOut[1], $posOut[2],
                $speedOut[0], $speedOut[1], $speedOut[2]
            ];
        }        // Step 4: Precession (lines 5455-5548)
        if ($isJ2000) {
            // Precess from mean equinox of date to J2000 (line 5461)
            for ($i = 0; $i < 4; $i++) {
                Precession::precess($xx[$i], $tjdEt, 0, Constants::J_TO_J2000);
            }
        } else {
            // For date equinox: precess to J2000 and back (lines 5461, 5544)
            // This seems redundant but it's what C code does
            for ($i = 0; $i < 4; $i++) {
                Precession::precess($xx[$i], $tjdEt, 0, Constants::J_TO_J2000);
            }

            // Apply nutation if requested (line 5550)
            if ($withNutation) {
                [$dpsi, $deps] = Nutation::calc($tjdEt);

                // Calculate mean obliquity for nutation matrix
                $epsMean = Obliquity::meanObliquityRadFromJdTT($tjdEt);

                // Calculate true obliquity
                $epsTrue = $epsMean + $deps;

                // Use simplified rotation by nutation angle (as in C code line 5550)
                // This rotates around X-axis by nutation in obliquity
                $snut = sin($epsTrue) - sin($epsMean);  // Approximation for small angles
                $cnut = cos($epsTrue) - cos($epsMean);

                // Actually, looking at C code more carefully, swed.nut.snut/cnut are
                // sin(eps_true) and cos(eps_true), not the differences!
                $snut = sin($epsTrue);
                $cnut = cos($epsTrue);

                for ($i = 0; $i < 4; $i++) {
                    // Transform position
                    $pos = [$xx[$i][0], $xx[$i][1], $xx[$i][2]];
                    $posOut = [];
                    Coordinates::coortrf2($pos, $posOut, $snut, $cnut);

                    // Transform speed
                    $speed = [$xx[$i][3], $xx[$i][4], $xx[$i][5]];
                    $speedOut = [];
                    Coordinates::coortrf2($speed, $speedOut, $snut, $cnut);

                    // Reassemble
                    $xx[$i] = [
                        $posOut[0], $posOut[1], $posOut[2],
                        $speedOut[0], $speedOut[1], $speedOut[2]
                    ];
                }
            }            // Precess back from J2000 to date (line 5544)
            for ($i = 0; $i < 4; $i++) {
                Precession::precess($xx[$i], $tjdEt, 0, Constants::J2000_TO_J);
            }
        }

        // Step 5: Transform back to ecliptic (lines 5563-5568)
        for ($i = 0; $i < 4; $i++) {
            // Transform position
            $pos = [$xx[$i][0], $xx[$i][1], $xx[$i][2]];
            $posOut = [];
            Coordinates::coortrf2($pos, $posOut, $seps, $ceps);

            // Transform speed
            $speed = [$xx[$i][3], $xx[$i][4], $xx[$i][5]];
            $speedOut = [];
            Coordinates::coortrf2($speed, $speedOut, $seps, $ceps);

            // Reassemble
            $xx[$i] = [
                $posOut[0], $posOut[1], $posOut[2],
                $speedOut[0], $speedOut[1], $speedOut[2]
            ];
        }

        // Step 6: Convert cartesian to polar with speed (line 5607-5608)
        for ($i = 0; $i < 4; $i++) {
            Coordinates::cartPolSp($xx[$i], $xx[$i]);
        }

        // Step 7: Convert radians to degrees (unless SEFLG_RADIANS) (lines 5612-5618)
        if (!($iflag & Constants::SEFLG_RADIANS)) {
            for ($i = 0; $i < 4; $i++) {
                $xx[$i][0] = rad2deg($xx[$i][0]);
                $xx[$i][1] = rad2deg($xx[$i][1]);
                $xx[$i][3] = rad2deg($xx[$i][3]);
                $xx[$i][4] = rad2deg($xx[$i][4]);
            }
        }

        // Copy back to output arrays
        $xnasc = $xx[0];
        $xndsc = $xx[1];
        $xperi = $xx[2];
        $xaphe = $xx[3];
    }
}
