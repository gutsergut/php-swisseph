<?php

namespace Swisseph;

/**
 * FK4 ↔ FK5 coordinate system conversions.
 *
 * Port of swi_FK4_FK5() and swi_FK5_FK4() from swephlib.c:4090-4113
 *
 * FK4 (Fourth Fundamental Catalogue) and FK5 (Fifth Fundamental Catalogue)
 * are different stellar reference frames. FK4 was based on B1950.0 epoch,
 * while FK5 is based on J2000.0 epoch.
 */
class FK4FK5
{
    /**
     * Convert FK4 (B1950.0) coordinates to FK5 (J2000.0).
     *
     * Port of swi_FK4_FK5() from swephlib.c:4090-4103
     *
     * According to Explanatory Supplement to the Astronomical Almanac, p. 167f.
     * Applies correction for equinox shift between FK4 and FK5 systems.
     *
     * @param array $xp Position vector [x, y, z, dx, dy, dz] in AU and AU/day (modified in place)
     * @param float $tjd Julian day (for time-dependent correction)
     * @return void
     */
    public static function fk4ToFk5(array &$xp, float $tjd): void
    {
        // Zero vector check
        if ($xp[0] == 0 && $xp[1] == 0 && $xp[2] == 0) {
            return;
        }

        // With zero speed, we assume it should really be zero
        $correctSpeed = true;
        if ($xp[3] == 0) {
            $correctSpeed = false;
        }

        // Convert to polar coordinates
        Coordinates::cartpolSp($xp, $xp);

        // Apply equinox correction according to Expl.Suppl., p. 167f.
        // 0.035 arcsec base correction + 0.085 arcsec/century time-dependent term
        $correction = (0.035 + 0.085 * ($tjd - Constants::B1950) / 36524.2198782) / 3600.0 * 15.0 * Constants::DEGTORAD;
        $xp[0] += $correction;

        // Apply speed correction if needed
        if ($correctSpeed) {
            $speedCorrection = (0.085 / 36524.2198782) / 3600.0 * 15.0 * Constants::DEGTORAD;
            $xp[3] += $speedCorrection;
        }

        // Convert back to Cartesian coordinates
        Coordinates::polcartSp($xp, $xp);
    }

    /**
     * Convert FK5 (J2000.0) coordinates to FK4 (B1950.0).
     *
     * Port of swi_FK5_FK4() from swephlib.c:4105-4113
     *
     * Inverse of fk4ToFk5(). According to Explanatory Supplement, p. 167f.
     *
     * @param array $xp Position vector [x, y, z, dx, dy, dz] in AU and AU/day (modified in place)
     * @param float $tjd Julian day (for time-dependent correction)
     * @return void
     */
    public static function fk5ToFk4(array &$xp, float $tjd): void
    {
        // Zero vector check
        if ($xp[0] == 0 && $xp[1] == 0 && $xp[2] == 0) {
            return;
        }

        // Convert to polar coordinates
        Coordinates::cartpolSp($xp, $xp);

        // Apply inverse equinox correction
        $correction = (0.035 + 0.085 * ($tjd - Constants::B1950) / 36524.2198782) / 3600.0 * 15.0 * Constants::DEGTORAD;
        $xp[0] -= $correction;

        // Apply inverse speed correction
        $speedCorrection = (0.085 / 36524.2198782) / 3600.0 * 15.0 * Constants::DEGTORAD;
        $xp[3] -= $speedCorrection;

        // Convert back to Cartesian coordinates
        Coordinates::polcartSp($xp, $xp);
    }
}
