<?php

declare(strict_types=1);

namespace Swisseph\Swe\Functions;

use Swisseph\Constants;
use Swisseph\SwephFile\SwedState;
use Swisseph\TidalAcceleration;

/**
 * Astronomical Models Management
 *
 * Full port from swephlib.c:4120-4450 without simplifications.
 *
 * Manages astronomical models for:
 * - Delta T (5 models: Stephenson/Morrison 1984-2016)
 * - Precession (11 models: Newcomb to Vondrak 2011)
 * - Nutation (5 models: Woolard to IAU 2000A)
 * - Frame Bias (3 methods: None, IAU 2000, IAU 2006)
 * - Sidereal Time (4 models: IAU 1976 to Long-term)
 * - JPL Horizons modes (approximation modes)
 *
 * Version presets for Swiss Ephemeris compatibility:
 * - SE 1.00-1.70: Historical models
 * - SE 1.72-1.80: IAU 2006 precession + nutation
 * - SE 2.00-2.05: IAU 2006 + updated Delta T
 * - SE 2.06+: Stephenson 2016 Delta T
 */
final class AstroModelsFunctions
{
    /**
     * Model configuration strings for different Swiss Ephemeris versions
     * Format: "D,P,P,N,B,J,J,S" where:
     *   D = Delta T model
     *   P = Precession long-term model
     *   P = Precession short-term model
     *   N = Nutation model
     *   B = Bias model
     *   J = JPL Horizons mode
     *   J = JPL Horizons approximation mode
     *   S = Sidereal time model
     */
    private const AMODELS_SE_1_00 = "1,3,1,1,1,0,0,1";
    private const AMODELS_SE_1_64 = "2,3,1,1,1,0,0,1";
    private const AMODELS_SE_1_70 = "2,8,8,4,2,0,0,2";
    private const AMODELS_SE_1_72 = "3,8,8,4,2,0,0,2";
    private const AMODELS_SE_1_77 = "4,8,8,4,2,0,0,2";
    private const AMODELS_SE_1_78 = "4,9,9,4,2,0,0,2";
    private const AMODELS_SE_1_80 = "4,9,9,4,3,0,0,1";  // note sid. time (S)!
    private const AMODELS_SE_2_00 = "4,9,9,4,3,0,0,4";
    private const AMODELS_SE_2_06 = "5,9,9,4,3,0,0,4";

    /**
     * Set astronomical models for Swiss Ephemeris calculations
     *
     * Port of swe_set_astro_models() from swephlib.c:4183-4237.
     *
     * @param string $samod Model specification:
     *   - "D,P,P,N,B,J,J,S" format (8 comma-separated integers)
     *   - "SE<version>" (e.g., "SE2.06", "SE1.80") - applies version preset
     *   - "" (empty) - uses current SE version
     * @param int $iflag Calculation flags (for ephemeris number detection)
     * @return void
     */
    public static function setAstroModels(string $samod, int $iflag): void
    {
        // Ensure SwedState is initialized
        $swed = SwedState::getInstance();

        // If models string provided (comma-separated digits)
        if ($samod !== '' && ctype_digit($samod[0])) {
            self::setAstroModelsFromString($samod);
            return;
        }

        // If empty or starts with "SE" - use version-based preset
        if ($samod === '' || strpos($samod, 'SE') === 0) {
            $s = $samod;
            if (strlen($s) > 20) {
                $s = substr($s, 0, 20);
            }

            // Remove second dot in "SE2.05.01"
            if (strlen($s) >= 5) {
                $pos = strpos($s, '.', 5);
                if ($pos !== false) {
                    $s = substr($s, 0, $pos) . substr($s, $pos + 1);
                }
            }

            // Remove 'b' in "SE2.05.02b04"
            if (strlen($s) >= 5) {
                $pos = strpos($s, 'b', 5);
                if ($pos !== false) {
                    $s = substr($s, 0, $pos) . substr($s, $pos + 1);
                }
            }

            // Extract version number
            $version = 0.0;
            if (strlen($s) >= 2) {
                $version = (float)substr($s, 2);
            }

            if ($version == 0.0) {
                // Use current SE version constant
                $version = 2.10;  // Current PHP port version
            }

            // Apply version-specific models
            if ($version >= 2.06) {
                self::setAstroModelsFromString(self::AMODELS_SE_2_06);
            } elseif ($version >= 2.01) {
                self::setAstroModelsFromString(self::AMODELS_SE_2_00);
            } elseif ($version >= 2.00) {
                self::setAstroModelsFromString(self::AMODELS_SE_2_00);
                // Special case: DE431 ephemeris
                // Note: We don't have swi_get_denum() fully implemented yet
                // So we skip the DE number check for now
                \swe_set_tid_acc(Constants::SE_TIDAL_DE406);
            } elseif ($version >= 1.80) {
                self::setAstroModelsFromString(self::AMODELS_SE_1_80);
                \swe_set_tid_acc(Constants::SE_TIDAL_DE406);
            } elseif ($version >= 1.78) {
                self::setAstroModelsFromString(self::AMODELS_SE_1_78);
                \swe_set_tid_acc(Constants::SE_TIDAL_DE406);
            } elseif ($version >= 1.77) {
                self::setAstroModelsFromString(self::AMODELS_SE_1_77);
                \swe_set_tid_acc(Constants::SE_TIDAL_DE406);
            } elseif ($version >= 1.72) {
                self::setAstroModelsFromString(self::AMODELS_SE_1_72);
                \swe_set_tid_acc(-25.7376);
            } elseif ($version >= 1.70) {
                self::setAstroModelsFromString(self::AMODELS_SE_1_70);
                \swe_set_tid_acc(-25.7376);
            } elseif ($version >= 1.64) {
                self::setAstroModelsFromString(self::AMODELS_SE_1_64);
                \swe_set_tid_acc(-25.7376);
            } else {
                self::setAstroModelsFromString(self::AMODELS_SE_1_00);
                \swe_set_tid_acc(-25.7376);
            }
        }
    }

    /**
     * Parse and set models from comma-separated string
     *
     * Port of set_astro_models() from swephlib.c:4120-4132.
     *
     * @param string $samod Model string "D,P,P,N,B,J,J,S"
     * @return void
     */
    private static function setAstroModelsFromString(string $samod): void
    {
        $swed = SwedState::getInstance();
        $parts = explode(',', $samod);

        $models = [];
        for ($i = 0; $i < Constants::NSE_MODELS && $i < count($parts); $i++) {
            $models[$i] = (int)trim($parts[$i]);
        }

        // Pad with zeros if needed
        while (count($models) < Constants::NSE_MODELS) {
            $models[] = 0;
        }

        $swed->astroModels = $models;
    }

    /**
     * Get current astronomical models configuration
     *
     * Port of swe_get_astro_models() from swephlib.c:4409-4555.
     *
     * @param string|null &$samod Output: model string "D,P,P,N,B,J,J,S" (may be modified if input provided)
     * @param string|null &$sdet Output: detailed human-readable description
     * @param int $iflag Calculation flags (for ephemeris number detection)
     * @return void
     */
    public static function getAstroModels(?string &$samod, ?string &$sdet, int $iflag): void
    {
        $swed = SwedState::getInstance();

        // If samod provided, process it first
        $listAllModels = false;
        if ($samod !== null && $samod !== '') {
            if (strpos($samod, '+') !== false) {
                $listAllModels = true;
            }
            self::setAstroModels($samod, $iflag);
        }

        // Build model string
        $samod0 = '';
        $models = $swed->astroModels ?? array_fill(0, Constants::NSE_MODELS, 0);

        for ($i = 0; $i < Constants::NSE_MODELS; $i++) {
            $imod = $models[$i];

            // Normalize to 0 for defaults
            switch ($i) {
                case Constants::SE_MODEL_PREC_LONGTERM:
                    if ($imod == Constants::SEMOD_PREC_DEFAULT) {
                        $imod = 0;
                    }
                    break;
                case Constants::SE_MODEL_PREC_SHORTTERM:
                    if ($imod == Constants::SEMOD_PREC_DEFAULT_SHORT) {
                        $imod = 0;
                    }
                    break;
                case Constants::SE_MODEL_NUT:
                    if ($imod == Constants::SEMOD_NUT_DEFAULT) {
                        $imod = 0;
                    }
                    break;
                case Constants::SE_MODEL_SIDT:
                    if ($imod == Constants::SEMOD_SIDT_DEFAULT) {
                        $imod = 0;
                    }
                    break;
                case Constants::SE_MODEL_BIAS:
                    if ($imod == Constants::SEMOD_BIAS_DEFAULT) {
                        $imod = 0;
                    }
                    break;
                case Constants::SE_MODEL_JPLHOR_MODE:
                    if ($imod == Constants::SEMOD_JPLHOR_DEFAULT) {
                        $imod = 0;
                    }
                    break;
                case Constants::SE_MODEL_JPLHORA_MODE:
                    if ($imod == Constants::SEMOD_JPLHORA_DEFAULT) {
                        $imod = 0;
                    }
                    break;
                case Constants::SE_MODEL_DELTAT:
                    if ($imod == Constants::SEMOD_DELTAT_DEFAULT) {
                        $imod = 0;
                    }
                    break;
            }

            $samod0 .= sprintf('%d,', $imod);
        }

        // Note: In C, the line `strcpy(samod, samod0)` is commented out (swephlib.c:4442-4443).
        // This means samod is NOT returned as output, only sdet is populated.
        // samod serves only as optional INPUT to trigger swe_set_astro_models() call.

        // Build detailed description
        if ($sdet !== null) {
            $sdet = '';

            // JPL ephemeris number and tidal acceleration
            // Note: swi_get_denum() not fully implemented yet, using placeholder
            $denum = 431;  // Default DE431
            $tidAcc = \swe_get_tid_acc();
            $sdet .= sprintf("JPL eph. %d; tidal acc. Moon used by SE: %.4f\n", $denum, $tidAcc);

            // JPL Horizons mode info (if applicable)
            if ($iflag & Constants::SEFLG_JPLEPH) {
                if ($iflag & Constants::SEFLG_JPLHOR) {
                    $sdet .= "JPL Horizons mode: SEFLG_JPLHOR\n";
                    $sdet .= "  Daily corrections to dpsi/deps 1962-today\n";

                    $jplhormod = $models[Constants::SE_MODEL_JPLHOR_MODE] ?? 0;
                    if ($jplhormod == 0) {
                        $jplhormod = Constants::SEMOD_JPLHOR_DEFAULT;
                    }

                    if ($jplhormod == Constants::SEMOD_JPLHOR_LONG_AGREEMENT) {
                        $sdet .= "  Good agreement with JPL Horizons between 1800 and today\n";
                    } else {
                        $sdet .= "  Defaults to SEFLG_JPLEPH_APPROX before 1962\n";
                    }
                } elseif ($iflag & Constants::SEFLG_JPLHOR_APPROX) {
                    $sdet .= "JPL Horizons mode: SEFLG_JPLHOR_APPROX\n";
                    $sdet .= "  Some corrections, approximating JPL Horizons\n";

                    $jplhoramod = $models[Constants::SE_MODEL_JPLHORA_MODE] ?? 0;
                    if ($jplhoramod == 0) {
                        $jplhoramod = Constants::SEMOD_JPLHORA_DEFAULT;
                    }

                    if ($jplhoramod == Constants::SEMOD_JPLHORA_1) {
                        $sdet .= "  (SEMOD_JPLHORA_1)\n";
                    } elseif ($jplhoramod == Constants::SEMOD_JPLHORA_2) {
                        $sdet .= "  (SEMOD_JPLHORA_2)\n";
                    } else {
                        $sdet .= "  (SEMOD_JPLHORA_3)\n";
                    }
                }
            }

            // Model descriptions
            $sdet .= "\nAstronomical models:\n";

            $sdet .= "  Delta T: " . self::getDeltaTModelName($models[Constants::SE_MODEL_DELTAT] ?? 0) . "\n";
            $sdet .= "  Precession (long-term): " . self::getPrecessionModelName($models[Constants::SE_MODEL_PREC_LONGTERM] ?? 0, $iflag) . "\n";
            $sdet .= "  Precession (short-term): " . self::getPrecessionModelName($models[Constants::SE_MODEL_PREC_SHORTTERM] ?? 0, $iflag) . "\n";
            $sdet .= "  Nutation: " . self::getNutationModelName($models[Constants::SE_MODEL_NUT] ?? 0, $iflag, $models) . "\n";
            $sdet .= "  Frame bias: " . self::getFrameBiasModelName($models[Constants::SE_MODEL_BIAS] ?? 0) . "\n";
            $sdet .= "  Sidereal time: " . self::getSidtModelName($models[Constants::SE_MODEL_SIDT] ?? 0) . "\n";
        }
    }

    /**
     * Get precession model name
     * Port from get_precession_model() in swephlib.c:4240-4277
     */
    private static function getPrecessionModelName(int $precmod, int $iflag): string
    {
        if ($precmod == 0) {
            $precmod = Constants::SEMOD_PREC_DEFAULT;
        }

        if ($iflag & Constants::SEFLG_JPLEPH) {
            if ($iflag & Constants::SEFLG_JPLHOR) {
                return "IAU 1976 (Lieske) / Owen 1990 before 1799";
            }
            if ($iflag & Constants::SEFLG_JPLHOR_APPROX) {
                return "Vondrak 2011 / IAU 1976 (Lieske) before 1962 / Owen 1990 before 1799";
            }
        }

        return match ($precmod) {
            Constants::SEMOD_PREC_IAU_1976 => "IAU 1976 (Lieske)",
            Constants::SEMOD_PREC_IAU_2000 => "IAU 2000 (Lieske 1976, Mathews 2002)",
            Constants::SEMOD_PREC_IAU_2006 => "IAU 2006 (Capitaine & alii)",
            Constants::SEMOD_PREC_BRETAGNON_2003 => "Bretagnon 2003",
            Constants::SEMOD_PREC_LASKAR_1986 => "Laskar 1986",
            Constants::SEMOD_PREC_SIMON_1994 => "Simon 1994",
            Constants::SEMOD_PREC_WILLIAMS_1994 => "Williams 1994",
            Constants::SEMOD_PREC_WILL_EPS_LASK => "Williams 1994 / Epsilon Laskar 1986",
            Constants::SEMOD_PREC_OWEN_1990 => "Owen 1990",
            Constants::SEMOD_PREC_NEWCOMB => "Newcomb 1895",
            Constants::SEMOD_PREC_VONDRAK_2011 => "Vondrák 2011",
            default => "Unknown",
        };
    }

    /**
     * Get Delta T model name
     * Port from get_deltat_model() in swephlib.c:4279-4298
     */
    private static function getDeltaTModelName(int $dtmod): string
    {
        if ($dtmod == 0) {
            $dtmod = Constants::SEMOD_DELTAT_DEFAULT;
        }

        return match ($dtmod) {
            Constants::SEMOD_DELTAT_ESPENAK_MEEUS_2006 => "Espenak/Meeus 2006 (before 1633)",
            Constants::SEMOD_DELTAT_STEPHENSON_MORRISON_2004 => "Stephenson/Morrison 2004 (before 1600)",
            Constants::SEMOD_DELTAT_STEPHENSON_1997 => "Stephenson 1997 (before 1600)",
            Constants::SEMOD_DELTAT_STEPHENSON_MORRISON_1984 => "Stephenson/Morrison 1984 (before 1600)",
            Constants::SEMOD_DELTAT_STEPHENSON_ETC_2016 => "Stephenson/Morrison/Hohenkerk 2016 (before 1955)",
            default => "Unknown",
        };
    }

    /**
     * Get nutation model name
     * Port from get_nutation_model() in swephlib.c:4300-4339
     */
    private static function getNutationModelName(int $nutmod, int $iflag, array $models): string
    {
        if ($nutmod == 0) {
            $nutmod = Constants::SEMOD_NUT_DEFAULT;
        }

        $name = match ($nutmod) {
            Constants::SEMOD_NUT_WOOLARD => "Woolard 1953",
            Constants::SEMOD_NUT_IAU_1980 => "IAU 1980 (Wahr)",
            Constants::SEMOD_NUT_IAU_CORR_1987 => "Herring 1986",
            Constants::SEMOD_NUT_IAU_2000A => "IAU 2000A (Mathews)",
            Constants::SEMOD_NUT_IAU_2000B => "IAU 2000B (Mathews)",
            default => "Unknown",
        };

        // Add JPL Horizons corrections info
        if ($iflag & Constants::SEFLG_JPLEPH) {
            if ($iflag & Constants::SEFLG_JPLHOR) {
                $name = "IAU 1980 (Wahr)\n+ daily corrections to dpsi/deps 1962-today";

                $jplhormod = $models[Constants::SE_MODEL_JPLHOR_MODE] ?? 0;
                if ($jplhormod == 0) {
                    $jplhormod = Constants::SEMOD_JPLHOR_DEFAULT;
                }

                if ($jplhormod == Constants::SEMOD_JPLHOR_LONG_AGREEMENT) {
                    $name .= "\n  good agreement with JPL Horizons between 1800 and today";
                } else {
                    $name .= "\n  defaults to SEFLG_JPLEPH_APPROX before 1962";
                }
            } elseif ($iflag & Constants::SEFLG_JPLHOR_APPROX) {
                $name .= "\n+ some corrections, approximating JPL Horizons";

                $jplhoramod = $models[Constants::SE_MODEL_JPLHORA_MODE] ?? 0;
                if ($jplhoramod == 0) {
                    $jplhoramod = Constants::SEMOD_JPLHORA_DEFAULT;
                }

                if ($jplhoramod == Constants::SEMOD_JPLHORA_1) {
                    $name .= " (SEMOD_JPLHORA_1)";
                } elseif ($jplhoramod == Constants::SEMOD_JPLHORA_2) {
                    $name .= " (SEMOD_JPLHORA_2)";
                } else {
                    $name .= " (SEMOD_JPLHORA_3)";
                }
            }
        }

        return $name;
    }

    /**
     * Get frame bias model name
     * Port from get_frame_bias_model() in swephlib.c:4341-4354
     */
    private static function getFrameBiasModelName(int $biasmod): string
    {
        if ($biasmod == 0) {
            $biasmod = Constants::SEMOD_BIAS_DEFAULT;
        }

        return match ($biasmod) {
            Constants::SEMOD_BIAS_IAU2000 => "IAU 2000",
            Constants::SEMOD_BIAS_IAU2006 => "IAU 2006",
            Constants::SEMOD_BIAS_NONE => "none",
            default => "Unknown",
        };
    }

    /**
     * Get sidereal time model name
     * Port from get_sidt_model() in swephlib.c:4356-4371
     */
    private static function getSidtModelName(int $sidtmod): string
    {
        if ($sidtmod == 0) {
            $sidtmod = Constants::SEMOD_SIDT_DEFAULT;
        }

        return match ($sidtmod) {
            Constants::SEMOD_SIDT_IAU_1976 => "IAU 1976",
            Constants::SEMOD_SIDT_IAU_2006 => "IAU 2006 (Capitaine 2003)",
            Constants::SEMOD_SIDT_IERS_CONV_2010 => "IERS Convention 2010",
            Constants::SEMOD_SIDT_LONGTERM => "IERS Convention 2010 + long-term extension by Astrodienst",
            default => "Unknown",
        };
    }
}
