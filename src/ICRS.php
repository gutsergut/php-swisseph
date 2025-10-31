<?php

namespace Swisseph;

/**
 * ICRS ↔ FK5 coordinate frame conversions.
 *
 * Port of swi_icrs2fk5() from swephlib.c:2292-2337
 *
 * ICRS (International Celestial Reference System) is the current standard reference frame
 * adopted by IAU in 1997. It's essentially aligned with FK5/J2000, but with improved accuracy.
 *
 * The transformation uses a small rotation matrix to convert between the two frames.
 */
class ICRS
{
    /**
     * Convert between ICRS and FK5 frames.
     *
     * Port of swi_icrs2fk5() from swephlib.c:2292-2337
     *
     * Applies a small rotation matrix for frame conversion.
     * The rotation matrix values are based on IAU standards.
     *
     * @param array $x Position vector [x, y, z, dx, dy, dz] (modified in place)
     * @param int $iflag Calculation flags (for SEFLG_SPEED check)
     * @param bool $backward If true: FK5→ICRS, if false: ICRS→FK5
     * @return void
     */
    public static function icrsToFk5(array &$x, int $iflag, bool $backward): void
    {
        // Rotation matrix for ICRS ↔ FK5 conversion
        // These are the precise IAU values
        $rb = [
            [+0.9999999999999928, +0.0000001110223287, +0.0000000441180557],
            [-0.0000001110223330, +0.9999999999999891, +0.0000000964779176],
            [-0.0000000441180450, -0.0000000964779225, +0.9999999999999943]
        ];

        $xx = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

        if ($backward) {
            // FK5 → ICRS (backward transformation)
            // Multiply by rows of matrix
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] = $x[0] * $rb[$i][0] +
                         $x[1] * $rb[$i][1] +
                         $x[2] * $rb[$i][2];

                if ($iflag & Constants::SEFLG_SPEED) {
                    $xx[$i + 3] = $x[3] * $rb[$i][0] +
                                 $x[4] * $rb[$i][1] +
                                 $x[5] * $rb[$i][2];
                }
            }
        } else {
            // ICRS → FK5 (forward transformation)
            // Multiply by columns of matrix (transpose)
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] = $x[0] * $rb[0][$i] +
                         $x[1] * $rb[1][$i] +
                         $x[2] * $rb[2][$i];

                if ($iflag & Constants::SEFLG_SPEED) {
                    $xx[$i + 3] = $x[3] * $rb[0][$i] +
                                 $x[4] * $rb[1][$i] +
                                 $x[5] * $rb[2][$i];
                }
            }
        }

        // Copy result back
        for ($i = 0; $i <= 5; $i++) {
            $x[$i] = $xx[$i];
        }
    }
}
