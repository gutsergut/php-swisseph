<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Constants;
use Swisseph\Swe\SweDateTime;
use Swisseph\Swe\Functions\HousesFunctions;
use Swisseph\SwephFile\SwedState;

final class MoonTopoParallaxAccuracyTest extends TestCase
{
    private const SWETEST_PATH = 'C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\с-swisseph\\swisseph\\windows\\programs\\swetest64.exe';

    // Moscow as an example topocentric site
    private const GEO = [55.75, 37.62, 0.18]; // lat, lon (east+), elevation km

    public function test_topocentric_parallax_ratio_within_threshold(): void
    {
        $jd_ut = 2460408.5; // Same date as other Moon tests in repo
        $iflag = Constants::SEFLG_SWIEPH | Constants::SEFLG_EQUATORIAL | Constants::SEFLG_SPEED;

        // Ensure ephe path set
        $swed = SwedState::getInstance();
        if ($swed->ephepath === '') {
            $swed->setEphePath(__DIR__ . DIRECTORY_SEPARATOR . 'ephe');
        }

        // Compute PHP geocentric and topocentric RA/Dec for Moon
        [$ra_geo, $dec_geo] = $this->calcMoonEquatorial($jd_ut, $iflag, null);
        [$ra_topo, $dec_topo] = $this->calcMoonEquatorial($jd_ut, $iflag | Constants::SEFLG_TOPOCTR, self::GEO);

        $dra_php = ($ra_topo - $ra_geo) * 3600.0; // arcsec
        $ddec_php = ($dec_topo - $dec_geo) * 3600.0; // arcsec

        // Get swetest reference
        [$ra_geo_ref, $dec_geo_ref] = $this->swetestMoonEquatorial($jd_ut, null);
        [$ra_topo_ref, $dec_topo_ref] = $this->swetestMoonEquatorial($jd_ut, self::GEO);
        $dra_ref = ($ra_topo_ref - $ra_geo_ref) * 3600.0;
        $ddec_ref = ($dec_topo_ref - $dec_geo_ref) * 3600.0;

        // Ratios should be close to 1 within 0.2%
        $ratio_ra = $dra_php / $dra_ref;
        $ratio_dec = $ddec_php / $ddec_ref;

        $this->assertThat($ratio_ra, $this->logicalAnd(
            $this->greaterThan(1.0 - 0.002),
            $this->lessThan(1.0 + 0.002)
        ), 'RA parallax ratio out of bounds: ' . $ratio_ra);

        $this->assertThat($ratio_dec, $this->logicalAnd(
            $this->greaterThan(1.0 - 0.002),
            $this->lessThan(1.0 + 0.002)
        ), 'Dec parallax ratio out of bounds: ' . $ratio_dec);
    }

    private function calcMoonEquatorial(float $jd_ut, int $iflag, ?array $geo): array
    {
        $swed = SwedState::getInstance();
        if ($geo) {
            // lon east+, Swiss Ephem expects east positive
            $swed->topd->geolon = $geo[1];
            $swed->topd->geolat = $geo[0];
            $swed->topd->geoalt = $geo[2];
            $swed->geoposIsSet = true;
        } else {
            $swed->geoposIsSet = false;
        }

        $jd_tt = $jd_ut + \Swisseph\DeltaT::deltaTSecondsFromJd($jd_ut) / 86400.0;
        $xx = array_fill(0, 6, 0.0);
        $serr = null;
        \Swisseph\SwephFile\SwephCalculator::calculateBody($jd_tt, Constants::SE_MOON, $iflag, $xx, $serr);
        return [$xx[0], $xx[1]]; // RA, Dec in degrees
    }

    private function swetestMoonEquatorial(float $jd_ut, ?array $geo): array
    {
        $args = [
            '-b1.1.2000', // placeholder, will use -bj below
            '-bj' . $jd_ut,
            '-p1',
            '-eswe',
            '-equ',
            '-s1',
            '-fPZ' // RA,Dec in degrees
        ];
        if ($geo) {
            $args[] = '-topo' . $geo[1] . ',' . $geo[0] . ',' . ($geo[2] * 1000.0);
        }
        $cmd = '"' . self::SWETEST_PATH . '" ' . implode(' ', $args);
        $out = [];
        @exec($cmd, $out);
        if (empty($out)) {
            $this->markTestSkipped('swetest64.exe not available or returned no output');
        }
        // Parse last line like: " 2000.01.01 ... RA=xx Dec=yy"
        $line = end($out);
        // Expect two numbers separated by spaces
        $parts = preg_split('/\s+/', trim($line));
        $n = count($parts);
        $ra = (float)$parts[$n-2];
        $dec = (float)$parts[$n-1];
        return [$ra, $dec];
    }
}
