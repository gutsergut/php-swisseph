<?php

declare(strict_types=1);

namespace Swisseph\Domain\Heliacal;

use Swisseph\Constants;
use Swisseph\Swe;
use Swisseph\ErrorCodes;

/**
 * Heliacal event computation via Arcus Visionis method
 * Full port from swehel.c lines 2114-2451 (moon_event_arc_vis, heliacal_ut_arc_vis)
 *
 * WITHOUT SIMPLIFICATIONS - complete C algorithm:
 * - Binary search for heliacal events using TopoArcVisionis
 * - Moon crescent visibility with phase checking
 * - Variable step size optimization (powers of 2)
 * - Both VR (minimum TAV walk) and PTO (Ptolemaic horizon cross) methods
 * - Full integration with swe_calc, swe_azalt, swe_pheno_ut
 */
final class HeliacalArcusMethod
{
    /**
     * Moon event calculation using Arcus Visionis method
     * Port from swehel.c:2114-2209 (moon_event_arc_vis)
     *
     * Finds lunar crescent visibility (evening first or morning last)
     * by searching for minimum TAV around new moon.
     *
     * @param float $JDNDaysUTStart Starting JD in UT
     * @param array $dgeo Geographic position [lon, lat, height_m]
     * @param array $datm Atmospheric conditions [pressure_mbar, temp_C, RH, VR_km]
     * @param array $dobs Observer parameters [age, SN, etc.]
     * @param int $TypeEvent Event type: 3=morning last, 4=evening first
     * @param int $helflag Calculation flags
     * @param array &$dret Output array [0]=JD of event
     * @param string|null &$serr Error message
     * @return int OK or ERR
     */
    public static function moon_event_arc_vis(
        float $JDNDaysUTStart,
        array $dgeo,
        array $datm,
        array $dobs,
        int $TypeEvent,
        int $helflag,
        array &$dret,
        ?string &$serr = null
    ): int {
        $x = array_fill(0, 20, 0.0);
        $MinTAV = 0.0;
        $MinTAVoud = 0.0;
        $OldestMinTAV = 0.0;
        $phase1 = 0.0;
        $phase2 = 0.0;
        $JDNDaysUT = 0.0;
        $JDNDaysUTi = 0.0;
        $tjd_moonevent = 0.0;
        $tjd_moonevent_start = 0.0;
        $DeltaAltoud = 0.0;
        $TimeCheck = 0.0;
        $LocalminCheck = 0.0;
        $AltS = 0.0;
        $AltO = 0.0;
        $DeltaAlt = 90.0;
        $ObjectName = 'moon';
        $Daystep = 0;
        $goingup = 0;
        $Planet = Constants::SE_MOON;

        $dret[0] = $JDNDaysUTStart; // will be returned in error case

        $avkind = $helflag & Constants::SE_HELFLAG_AVKIND;
        $epheflag = $helflag & (Constants::SEFLG_JPLEPH | Constants::SEFLG_SWIEPH | Constants::SEFLG_MOSEPH);

        if ($avkind === 0) {
            $avkind = Constants::SE_HELFLAG_AVKIND_VR;
        }
        if ($avkind !== Constants::SE_HELFLAG_AVKIND_VR) {
            $serr = 'error: invalid AV kind for the moon';
            return Constants::ERR;
        }
        if ($TypeEvent === 1 || $TypeEvent === 2) {
            $serr = 'error: the moon has no morning first or evening last';
            return Constants::ERR;
        }

        $iflag = Constants::SEFLG_TOPOCTR | Constants::SEFLG_EQUATORIAL | $epheflag;
        if (!($helflag & Constants::SE_HELFLAG_HIGH_PRECISION)) {
            $iflag |= Constants::SEFLG_NONUT | Constants::SEFLG_TRUEPOS;
        }

        $Daystep = 1;
        if ($TypeEvent === 3) {
            // morning last
            $TypeEvent = 2;
        } else {
            // evening first
            $TypeEvent = 1;
            $Daystep = -$Daystep;
        }

        // check Synodic/phase Period
        $JDNDaysUT = $JDNDaysUTStart;
        // start 30 days later if TypeEvent=4 (1)
        if ($TypeEvent === 1) {
            $JDNDaysUT = $JDNDaysUT + 30;
        }

        // determination of new moon date
        $serr_tmp = '';
        Swe::swe_pheno_ut($JDNDaysUT, $Planet, $iflag, $x, $serr_tmp);
        $phase2 = $x[0];
        $goingup = 0;

        do {
            $JDNDaysUT = $JDNDaysUT + $Daystep;
            $phase1 = $phase2;
            Swe::swe_pheno_ut($JDNDaysUT, $Planet, $iflag, $x, $serr_tmp);
            $phase2 = $x[0];
            if ($phase2 > $phase1) {
                $goingup = 1;
            }
        } while ($goingup === 0 || ($goingup === 1 && ($phase2 > $phase1)));

        // fix the date to get the day with the smallest phase (newest moon)
        $JDNDaysUT = $JDNDaysUT - $Daystep;

        // initialize the date to look for set
        $JDNDaysUTi = $JDNDaysUT;
        $JDNDaysUT = $JDNDaysUT - $Daystep;
        $MinTAVoud = 199.0;

        do {
            $JDNDaysUT = $JDNDaysUT + $Daystep;
            $retval = HeliacalGeometry::RiseSet(
                $JDNDaysUT,
                $dgeo,
                $datm,
                $ObjectName,
                $TypeEvent,
                $helflag,
                0,
                $tjd_moonevent,
                $serr
            );
            if ($retval !== Constants::OK) {
                return $retval;
            }
            $tjd_moonevent_start = $tjd_moonevent;
            $MinTAV = 199.0;
            $OldestMinTAV = $MinTAV;

            do {
                $OldestMinTAV = $MinTAVoud;
                $MinTAVoud = $MinTAV;
                $DeltaAltoud = $DeltaAlt;
                $tjd_moonevent = $tjd_moonevent - 1.0 / 60.0 / 24.0 * HeliacalUtils::sgn($Daystep);

                if (HeliacalGeometry::ObjectLoc($tjd_moonevent, $dgeo, $datm, 'sun', 0, $helflag, $AltS, $serr) === Constants::ERR) {
                    return Constants::ERR;
                }
                if (HeliacalGeometry::ObjectLoc($tjd_moonevent, $dgeo, $datm, $ObjectName, 0, $helflag, $AltO, $serr) === Constants::ERR) {
                    return Constants::ERR;
                }
                $DeltaAlt = $AltO - $AltS;

                if (HeliacalArcusVisionis::DeterTAV($dobs, $tjd_moonevent, $dgeo, $datm, $ObjectName, $helflag, $MinTAV, $serr) === Constants::ERR) {
                    return Constants::ERR;
                }

                $TimeCheck = $tjd_moonevent - HeliacalConstants::LocalMinStep / 60.0 / 24.0 * HeliacalUtils::sgn($Daystep);
                if (HeliacalArcusVisionis::DeterTAV($dobs, $TimeCheck, $dgeo, $datm, $ObjectName, $helflag, $LocalminCheck, $serr) === Constants::ERR) {
                    return Constants::ERR;
                }
            } while (
                ($MinTAV <= $MinTAVoud || $LocalminCheck < $MinTAV) &&
                abs($tjd_moonevent - $tjd_moonevent_start) < 120.0 / 60.0 / 24.0
            );
        } while ($DeltaAltoud < $MinTAVoud && abs($JDNDaysUT - $JDNDaysUTi) < 15);

        if (abs($JDNDaysUT - $JDNDaysUTi) < 15) {
            $tjd_moonevent += (1 - HeliacalUtils::x2min($MinTAV, $MinTAVoud, $OldestMinTAV)) *
                HeliacalUtils::sgn($Daystep) / 60.0 / 24.0;
        } else {
            $serr = 'no date found for lunar event';
            return Constants::ERR;
        }

        $dret[0] = $tjd_moonevent;
        return Constants::OK;
    }

    /**
     * Heliacal event calculation using Arcus Visionis method
     * Port from swehel.c:2211-2451 (heliacal_ut_arc_vis)
     *
     * Main algorithm for finding heliacal rising/setting using AV method.
     * Uses variable step size (powers of 2) for coarse search, then
     * fine-tunes with VR (minimum TAV walk) or PTO (horizon crossing).
     *
     * @param float $JDNDaysUTStart Starting JD in UT
     * @param array $dgeo Geographic position [lon, lat, height_m]
     * @param array $datm Atmospheric conditions [pressure_mbar, temp_C, RH, VR_km]
     * @param array $dobs Observer parameters [age, SN, etc.]
     * @param string $ObjectName Object name ('venus', 'aldebaran', etc.)
     * @param int $TypeEventIn Event type: 1=heliacal rising, 2=heliacal setting, 3=acronychal rising, 4=acronychal setting
     * @param int $helflag Calculation flags
     * @param array &$dret Output array [0]=JD of event
     * @param string|null &$serr_ret Error message
     * @return int OK, -2 (not found), or ERR
     */
    public static function heliacal_ut_arc_vis(
        float $JDNDaysUTStart,
        array $dgeo,
        array $datm,
        array $dobs,
        string $ObjectName,
        int $TypeEventIn,
        int $helflag,
        array &$dret,
        ?string &$serr_ret = null
    ): int {
        $x = array_fill(0, 6, 0.0);
        $xin = array_fill(0, 2, 0.0);
        $xaz = array_fill(0, 3, 0.0);
        $dang = array_fill(0, 3, 0.0);
        $objectmagn = 0.0;
        $maxlength = 0.0;
        $DayStep = 0.0;
        $JDNDaysUT = 0.0;
        $JDNDaysUTfinal = 0.0;
        $JDNDaysUTstep = 0.0;
        $JDNDaysUTstepoud = 0.0;
        $JDNarcvisUT = 0.0;
        $tjd_tt = 0.0;
        $tret = 0.0;
        $OudeDatum = 0.0;
        $JDNDaysUTinp = $JDNDaysUTStart;
        $JDNDaysUTtijd = 0.0;
        $ArcusVis = 0.0;
        $ArcusVisDelta = 0.0;
        $ArcusVisPto = 0.0;
        $ArcusVisDeltaoud = 0.0;
        $Trise = 0.0;
        $sunsangle = 0.0;
        $Theliacal = 0.0;
        $Tdelta = 0.0;
        $Angle = 0.0;
        $TimeStep = 0.0;
        $TimePointer = 0.0;
        $OldestMinTAV = 0.0;
        $MinTAVoud = 0.0;
        $MinTAVact = 0.0;
        $extrax = 0.0;
        $TbVR = 0.0;
        $AziS = 0.0;
        $AltS = 0.0;
        $AziO = 0.0;
        $AltO = 0.0;
        $DeltaAlt = 0.0;
        $direct = 0.0;
        $Pressure = $datm[0];
        $Temperature = $datm[1];
        $d = 0.0;
        $retval = Constants::OK;
        $TypeEvent = $TypeEventIn;
        $doneoneday = 0;
        $serr = '';

        $dret[0] = $JDNDaysUTStart;

        $Planet = HeliacalMagnitude::DeterObject($ObjectName);

        // determine Magnitude of star
        $retval = HeliacalMagnitude::Magnitude($JDNDaysUTStart, $dgeo, $ObjectName, $helflag, $objectmagn, $serr);
        if ($retval === Constants::ERR) {
            goto swe_heliacal_err;
        }

        $epheflag = $helflag & (Constants::SEFLG_JPLEPH | Constants::SEFLG_SWIEPH | Constants::SEFLG_MOSEPH);
        $iflag = Constants::SEFLG_TOPOCTR | Constants::SEFLG_EQUATORIAL | $epheflag;
        if (!($helflag & Constants::SE_HELFLAG_HIGH_PRECISION)) {
            $iflag |= Constants::SEFLG_NONUT | Constants::SEFLG_TRUEPOS;
        }

        // start values for search of heliacal rise
        // maxlength = phase period in days, smaller than minimal synodic period
        // days per step (for heliacal rise) in power of two
        switch ($Planet) {
            case Constants::SE_MERCURY:
                $DayStep = 1;
                $maxlength = 100;
                break;
            case Constants::SE_VENUS:
                $DayStep = 64;
                $maxlength = 384;
                break;
            case Constants::SE_MARS:
                $DayStep = 128;
                $maxlength = 640;
                break;
            case Constants::SE_JUPITER:
                $DayStep = 64;
                $maxlength = 384;
                break;
            case Constants::SE_SATURN:
                $DayStep = 64;
                $maxlength = 256;
                break;
            default:
                $DayStep = 64;
                $maxlength = 256;
                break;
        }

        // heliacal setting
        $eventtype = $TypeEvent;
        if ($eventtype === 2) {
            $DayStep = -$DayStep;
        }
        // acronychal setting
        if ($eventtype === 4) {
            $eventtype = 1;
            $DayStep = -$DayStep;
        }
        // acronychal rising
        if ($eventtype === 3) {
            $eventtype = 2;
        }
        $eventtype |= Constants::SE_BIT_DISC_CENTER;

        // normalize the maxlength to the step size
        {
            // check each Synodic/phase Period
            $JDNDaysUT = $JDNDaysUTStart;
            // make sure one can find an event on the just after the JDNDaysUTStart
            $JDNDaysUTfinal = $JDNDaysUT + $maxlength;
            $JDNDaysUT = $JDNDaysUT - 1;
            if ($DayStep < 0) {
                $JDNDaysUTtijd = $JDNDaysUT;
                $JDNDaysUT = $JDNDaysUTfinal;
                $JDNDaysUTfinal = $JDNDaysUTtijd;
            }
            // prepare the search
            $JDNDaysUTstep = $JDNDaysUT - $DayStep;
            $doneoneday = 0;
            $ArcusVisDelta = 199.0;
            $ArcusVisPto = -5.55;

            do { // outer loop: step size reduction
                if (abs($DayStep) === 1.0) {
                    $doneoneday = 1;
                }

                do { // inner loop: daily stepping
                    // init search for heliacal rise
                    $JDNDaysUTstepoud = $JDNDaysUTstep;
                    $ArcusVisDeltaoud = $ArcusVisDelta;
                    $JDNDaysUTstep = $JDNDaysUTstep + $DayStep;

                    // determine rise/set time
                    $retval = HeliacalGeometry::my_rise_trans(
                        $JDNDaysUTstep,
                        Constants::SE_SUN,
                        '',
                        $eventtype,
                        $helflag,
                        $dgeo,
                        $datm,
                        $tret,
                        $serr
                    );
                    if ($retval === Constants::ERR) {
                        goto swe_heliacal_err;
                    }

                    // determine time compensation to get Sun's altitude at heliacal rise
                    $tjd_tt = $tret + Swe::swe_deltat_ex($tret, $epheflag, $serr);
                    $retval = Swe::swe_calc($tjd_tt, Constants::SE_SUN, $iflag, $x, $serr);
                    if ($retval === Constants::ERR) {
                        goto swe_heliacal_err;
                    }

                    $xin[0] = $x[0];
                    $xin[1] = $x[1];
                    Swe::swe_azalt($tret, Constants::SE_EQU2HOR, $dgeo, $Pressure, $Temperature, $xin, $xaz);
                    $Trise = HeliacalGeometry::HourAngle($xaz[1], $x[1], $dgeo[1]);

                    $sunsangle = $ArcusVisPto;
                    if ($helflag & Constants::SE_HELFLAG_AVKIND_MIN7) {
                        $sunsangle = -7;
                    }
                    if ($helflag & Constants::SE_HELFLAG_AVKIND_MIN9) {
                        $sunsangle = -9;
                    }

                    $Theliacal = HeliacalGeometry::HourAngle($sunsangle, $x[1], $dgeo[1]);
                    $Tdelta = $Theliacal - $Trise;
                    if ($TypeEvent === 2 || $TypeEvent === 3) {
                        $Tdelta = -$Tdelta;
                    }

                    // determine approx. time when sun is at the wanted Sun's altitude
                    $JDNarcvisUT = $tret - $Tdelta / 24.0;
                    $tjd_tt = $JDNarcvisUT + Swe::swe_deltat_ex($JDNarcvisUT, $epheflag, $serr);

                    // determine Sun's position
                    $retval = Swe::swe_calc($tjd_tt, Constants::SE_SUN, $iflag, $x, $serr);
                    if ($retval === Constants::ERR) {
                        goto swe_heliacal_err;
                    }

                    $xin[0] = $x[0];
                    $xin[1] = $x[1];
                    Swe::swe_azalt($JDNarcvisUT, Constants::SE_EQU2HOR, $dgeo, $Pressure, $Temperature, $xin, $xaz);
                    $AziS = $xaz[0] + 180.0;
                    if ($AziS >= 360.0) {
                        $AziS = $AziS - 360.0;
                    }
                    $AltS = $xaz[1];

                    // determine object's position
                    if ($Planet !== -1) {
                        $retval = Swe::swe_calc($tjd_tt, $Planet, $iflag, $x, $serr);
                        if ($retval === Constants::ERR) {
                            goto swe_heliacal_err;
                        }
                        // determine magnitude of Planet
                        $retval = HeliacalMagnitude::Magnitude($JDNarcvisUT, $dgeo, $ObjectName, $helflag, $objectmagn, $serr);
                        if ($retval === Constants::ERR) {
                            goto swe_heliacal_err;
                        }
                    } else {
                        $retval = Swe::swe_fixstar($ObjectName, $tjd_tt, $iflag, $x, $serr);
                        if ($retval === Constants::ERR) {
                            goto swe_heliacal_err;
                        }
                    }

                    $xin[0] = $x[0];
                    $xin[1] = $x[1];
                    Swe::swe_azalt($JDNarcvisUT, Constants::SE_EQU2HOR, $dgeo, $Pressure, $Temperature, $xin, $xaz);
                    $AziO = $xaz[0] + 180.0;
                    if ($AziO >= 360.0) {
                        $AziO = $AziO - 360.0;
                    }
                    $AltO = $xaz[1];

                    // determine arcus visionis
                    $DeltaAlt = $AltO - $AltS;
                    $retval = HeliacalArcusVisionis::HeliacalAngle(
                        $objectmagn,
                        $dobs,
                        $AziO,
                        -1,
                        0,
                        $JDNarcvisUT,
                        $AziS,
                        $dgeo,
                        $datm,
                        $helflag,
                        $dang,
                        $serr
                    );
                    if ($retval === Constants::ERR) {
                        goto swe_heliacal_err;
                    }
                    $ArcusVis = $dang[1];
                    $ArcusVisPto = $dang[2];
                    $ArcusVisDelta = $DeltaAlt - $ArcusVis;
                } while (
                    ($ArcusVisDeltaoud > 0 || $ArcusVisDelta < 0) &&
                    ($JDNDaysUTfinal - $JDNDaysUTstep) * HeliacalUtils::sgn($DayStep) > 0
                );

                if ($doneoneday === 0 && ($JDNDaysUTfinal - $JDNDaysUTstep) * HeliacalUtils::sgn($DayStep) > 0) {
                    // go back to date before heliacal altitude
                    $ArcusVisDelta = $ArcusVisDeltaoud;
                    $DayStep = ((int)(abs($DayStep) / 2.0)) * HeliacalUtils::sgn($DayStep);
                    $JDNDaysUTstep = $JDNDaysUTstepoud;
                }
            } while ($doneoneday === 0 && ($JDNDaysUTfinal - $JDNDaysUTstep) * HeliacalUtils::sgn($DayStep) > 0);
        }

        $d = ($JDNDaysUTfinal - $JDNDaysUTstep) * HeliacalUtils::sgn($DayStep);
        if ($d <= 0 || $d >= $maxlength) {
            $dret[0] = $JDNDaysUTinp; // no date found, just return input
            $retval = -2; // marks "not found" within synodic period
            $serr = sprintf('heliacal event not found within maxlength %f', $maxlength);
            goto swe_heliacal_err;
        }

        $direct = HeliacalConstants::TimeStepDefault / 24.0 / 60.0;
        if ($DayStep < 0) {
            $direct = -$direct;
        }

        if ($helflag & Constants::SE_HELFLAG_AVKIND_VR) {
            // determine via walkthrough
            $TimeStep = $direct;
            $TbVR = 0.0;
            $TimePointer = $JDNarcvisUT;

            if (HeliacalArcusVisionis::DeterTAV($dobs, $TimePointer, $dgeo, $datm, $ObjectName, $helflag, $OldestMinTAV, $serr) === Constants::ERR) {
                return Constants::ERR;
            }

            $TimePointer = $TimePointer + $TimeStep;
            if (HeliacalArcusVisionis::DeterTAV($dobs, $TimePointer, $dgeo, $datm, $ObjectName, $helflag, $MinTAVoud, $serr) === Constants::ERR) {
                return Constants::ERR;
            }

            if ($MinTAVoud > $OldestMinTAV) {
                $TimePointer = $JDNarcvisUT;
                $TimeStep = -$TimeStep;
                $MinTAVact = $OldestMinTAV;
            } else {
                $MinTAVact = $MinTAVoud;
                $MinTAVoud = $OldestMinTAV;
            }

            do {
                $TimePointer = $TimePointer + $TimeStep;
                $OldestMinTAV = $MinTAVoud;
                $MinTAVoud = $MinTAVact;

                if (HeliacalArcusVisionis::DeterTAV($dobs, $TimePointer, $dgeo, $datm, $ObjectName, $helflag, $MinTAVact, $serr) === Constants::ERR) {
                    return Constants::ERR;
                }

                if ($MinTAVoud < $MinTAVact) {
                    $extrax = HeliacalUtils::x2min($MinTAVact, $MinTAVoud, $OldestMinTAV);
                    $TbVR = $TimePointer - (1 - $extrax) * $TimeStep;
                }
            } while ($TbVR === 0.0);

            $JDNarcvisUT = $TbVR;
        }

        if ($helflag & Constants::SE_HELFLAG_AVKIND_PTO) {
            do {
                $OudeDatum = $JDNarcvisUT;
                $JDNarcvisUT = $JDNarcvisUT - $direct;
                $tjd_tt = $JDNarcvisUT + Swe::swe_deltat_ex($JDNarcvisUT, $epheflag, $serr);

                if ($Planet !== -1) {
                    $retval = Swe::swe_calc($tjd_tt, $Planet, $iflag, $x, $serr);
                    if ($retval === Constants::ERR) {
                        goto swe_heliacal_err;
                    }
                } else {
                    $retval = Swe::swe_fixstar($ObjectName, $tjd_tt, $iflag, $x, $serr);
                    if ($retval === Constants::ERR) {
                        goto swe_heliacal_err;
                    }
                }

                $xin[0] = $x[0];
                $xin[1] = $x[1];
                Swe::swe_azalt($JDNarcvisUT, Constants::SE_EQU2HOR, $dgeo, $Pressure, $Temperature, $xin, $xaz);
                $Angle = $xaz[1];
            } while ($Angle > 0);

            $JDNarcvisUT = ($JDNarcvisUT + $OudeDatum) / 2.0;
        }

        if ($JDNarcvisUT < -9999999 || $JDNarcvisUT > 9999999) {
            $dret[0] = $JDNDaysUT; // no date found, just return input
            $serr = 'no heliacal date found';
            $retval = Constants::ERR;
            goto swe_heliacal_err;
        }

        $dret[0] = $JDNarcvisUT;

        swe_heliacal_err:
        if ($serr_ret !== null && $serr !== '') {
            $serr_ret = $serr;
        }
        return $retval;
    }
}
