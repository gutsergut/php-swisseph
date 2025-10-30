<?php

declare(strict_types=1);

namespace Swisseph\Domain\NodesApsides;

use Swisseph\Coordinates;
use Swisseph\Math;

/**
 * Calculator for planetary mean nodes and apsides (VSOP87 elements)
 * Does NOT include Moon — lunar mean is handled by LunarMeanCalculator
 */
class MeanCalculator
{
    private const J2000 = 2451545.0;
    private const DEGTORAD = 0.0174532925199433;

    /**
     * Calculate mean nodes and apsides for planets Mercury-Neptune
     *
     * @param float $tjdEt Julian day ET/TT
     * @param int $iplx Element index from PlanetaryElements::IPL_TO_ELEM
     * @param array &$xnasc Output: ascending node [lon, lat, dist, dlon, dlat, ddist]
     * @param array &$xndsc Output: descending node
     * @param array &$xperi Output: perihelion
     * @param array &$xaphe Output: aphelion or focal point
     * @param bool $doFocalPoint Return focal point instead of aphelion
     * @param bool $withSpeed Calculate speeds
     * @param int $iflag Calculation flags (for coordinate transformations)
     */
    public static function calculate(
        float $tjdEt,
        int $iplx,
        array &$xnasc,
        array &$xndsc,
        array &$xperi,
        array &$xaphe,
        bool $doFocalPoint,
        bool $withSpeed,
        int $iflag = 0
    ): void {
        $t = ($tjdEt - self::J2000) / 36525.0;  // centuries since J2000

        // Initialize arrays
        $xnasc = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xndsc = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xperi = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        $xaphe = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        // Evaluate orbital elements at epoch
        $incl = PlanetaryElements::evalPoly(PlanetaryElements::EL_INCL[$iplx], $t);
        $sema = PlanetaryElements::evalPoly(PlanetaryElements::EL_SEMA[$iplx], $t);
        $ecce = PlanetaryElements::evalPoly(PlanetaryElements::EL_ECCE[$iplx], $t);

        // Speed contributions (derivatives per day)
        $vincl = PlanetaryElements::EL_INCL[$iplx][1] / 36525.0;
        $vsema = PlanetaryElements::EL_SEMA[$iplx][1] / 36525.0;
        $vecce = PlanetaryElements::EL_ECCE[$iplx][1] / 36525.0;

        // Ascending node
        $xnasc[0] = PlanetaryElements::evalPoly(PlanetaryElements::EL_NODE[$iplx], $t);
        $xnasc[3] = PlanetaryElements::EL_NODE[$iplx][1] / 36525.0;

        // Perihelion
        $xperi[0] = PlanetaryElements::evalPoly(PlanetaryElements::EL_PERI[$iplx], $t);
        $xperi[3] = PlanetaryElements::EL_PERI[$iplx][1] / 36525.0;

        // Descending node = ascending node + 180°
        $xndsc[0] = Math::normAngleDeg($xnasc[0] + 180.0);
        $xndsc[3] = $xnasc[3];

        // Angular distance of perihelion from node (in orbital plane)
        $parg = Math::normAngleDeg($xperi[0] - $xnasc[0]);
        $pargx = Math::normAngleDeg($xperi[0] + $xperi[3] - $xnasc[0] - $xnasc[3]);

        // Transform perihelion from orbital plane to ecliptic via spherical rotation
        $xpe_pol_rad = [Math::degToRad($parg), 0.0, 1.0];
        $xpe_cart = [];
        Coordinates::polCart($xpe_pol_rad, $xpe_cart);

        $seps_i = sin(Math::degToRad($incl));
        $ceps_i = cos(Math::degToRad($incl));
        $xpe_cart_rot = [];
        Coordinates::coortrf2($xpe_cart, $xpe_cart_rot, -$seps_i, $ceps_i);

        $xpe_rot_pol = [];
        Coordinates::cartPol($xpe_cart_rot, $xpe_rot_pol);

        // Auxiliary for speed: use -(incl + vincl)
        $xpe3_pol_rad = [Math::degToRad($pargx), 0.0, 1.0];
        $xpe3_cart = [];
        Coordinates::polCart($xpe3_pol_rad, $xpe3_cart);

        $seps_iv = sin(Math::degToRad($incl + $vincl));
        $ceps_iv = cos(Math::degToRad($incl + $vincl));
        $xpe3_cart_rot = [];
        Coordinates::coortrf2($xpe3_cart, $xpe3_cart_rot, -$seps_iv, $ceps_iv);

        $xpe3_rot_pol = [];
        Coordinates::cartPol($xpe3_cart_rot, $xpe3_rot_pol);

        // Add node longitude back; preserve latitude
        $xperi[0] = Math::normAngleDeg(Math::radToDeg($xpe_rot_pol[0]) + $xnasc[0]);
        $xperi[1] = Math::radToDeg($xpe_rot_pol[1]);
        $xperi[3] = Math::normAngleDeg(
            (Math::radToDeg($xpe3_rot_pol[0]) + $xnasc[0] + $xnasc[3]) - $xperi[0]
        );
        $xperi[4] = 0.0; // dlat placeholder

        // Heliocentric distances
        $xperi[2] = $sema * (1.0 - $ecce);
        $xperi[5] = ($sema + $vsema) * (1.0 - $ecce - $vecce) - $xperi[2];

        // Aphelion = perihelion + 180°
        $xaphe[0] = Math::normAngleDeg($xperi[0] + 180.0);
        $xaphe[1] = -$xperi[1];
        $xaphe[3] = $xperi[3];
        $xaphe[4] = -$xperi[4];

        if ($doFocalPoint) {
            $xaphe[2] = $sema * $ecce * 2.0;
            $xaphe[5] = ($sema + $vsema) * ($ecce + $vecce) * 2.0 - $xaphe[2];
        } else {
            $xaphe[2] = $sema * (1.0 + $ecce);
            $xaphe[5] = ($sema + $vsema) * (1.0 + $ecce + $vecce) - $xaphe[2];
        }

        // Heliocentric distance of nodes (from eccentric anomaly)
        $ea = atan(tan(-$parg * self::DEGTORAD / 2.0) * sqrt((1.0 - $ecce) / (1.0 + $ecce))) * 2.0;
        $eax = atan(
            tan(-$pargx * self::DEGTORAD / 2.0) * sqrt((1.0 - $ecce - $vecce) / (1.0 + $ecce + $vecce))
        ) * 2.0;

        $xnasc[2] = $sema * (cos($ea) - $ecce) / cos($parg * self::DEGTORAD);
        $xnasc[5] = ($sema + $vsema) *
            (cos($eax) - $ecce - $vecce) / cos($pargx * self::DEGTORAD) - $xnasc[2];

        $ea = atan(
            tan((180.0 - $parg) * self::DEGTORAD / 2.0) * sqrt((1.0 - $ecce) / (1.0 + $ecce))
        ) * 2.0;
        $eax = atan(
            tan((180.0 - $pargx) * self::DEGTORAD / 2.0)
            * sqrt((1.0 - $ecce - $vecce) / (1.0 + $ecce + $vecce))
        ) * 2.0;

        $xndsc[2] = $sema * (cos($ea) - $ecce) / cos((180.0 - $parg) * self::DEGTORAD);
        $xndsc[5] = ($sema + $vsema)
            * (cos($eax) - $ecce - $vecce)
            / cos((180.0 - $pargx) * self::DEGTORAD)
            - $xndsc[2];

        // Zero out speeds if not requested
        if (!$withSpeed) {
            $xnasc[3] = $xnasc[4] = $xnasc[5] = 0.0;
            $xndsc[3] = $xndsc[4] = $xndsc[5] = 0.0;
            $xperi[3] = $xperi[4] = $xperi[5] = 0.0;
            $xaphe[3] = $xaphe[4] = $xaphe[5] = 0.0;
        }

        // Transform from mean ecliptic to true ecliptic of date
        CoordinateTransformer::transformMeanToTrue(
            $tjdEt,
            $xnasc,
            $xndsc,
            $xperi,
            $xaphe,
            $iflag,
            false  // is_true_nodaps = FALSE for mean nodes
        );
    }
}
