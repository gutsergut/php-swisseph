<?php

namespace Swisseph;

final class Output
{
    /**
     * Return an empty xx[6] shaped according to flags.
     * - Default/EQUATORIAL/RADIANS: [a, b, r, da, db, dr]
     * - XYZ: [x, y, z, vx, vy, vz]
     * Values set to 0.0 so that units switching is consistent (0 in deg=0 in rad).
     */
    public static function emptyForFlags(int $iflag): array
    {
        if ($iflag & Constants::SEFLG_XYZ) {
            return [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
        }
        // Angular/Distance shape
        return [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
    }
}
