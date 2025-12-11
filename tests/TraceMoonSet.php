<?php

/**
 * Trace RiseSetFunctions step-by-step to find the bug
 * Compare with C code line-by-line
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Swisseph\Constants;

$ephePath = realpath(__DIR__ . '/../../eph/ephe');
swe_set_ephe_path($ephePath);

$jd_ut = 2460677.0;  // 2025-01-01 12:00 UT
$geopos = [13.4, 52.5, 0.0];  // Berlin
$atpress = 1013.25;
$attemp = 15.0;
$horhgt = 0.0;

echo "=== Step-by-step trace of Moon SET calculation ===\n\n";
echo "Input: tjd_ut = $jd_ut (2025-01-01 12:00 UT)\n";
echo "       geopos = [{$geopos[0]}°, {$geopos[1]}°, {$geopos[2]}m]\n";
echo "       atpress = $atpress mbar, attemp = $attemp °C\n";
echo "       horhgt = $horhgt\n\n";

// Step 1: Clean flags (swecl.c:4423)
$epheflag = Constants::SEFLG_SWIEPH;
$iflag = $epheflag & (Constants::SEFLG_EPHMASK | Constants::SEFLG_NONUT | Constants::SEFLG_TRUEPOS);
printf("Step 1: Clean flags\n");
printf("  epheflag = 0x%X\n", $epheflag);
printf("  iflag = epheflag & (EPHMASK|NONUT|TRUEPOS) = 0x%X\n\n", $iflag);

// Step 2: Set coordinate system (swecl.c:4425-4432)
$rsmi = Constants::SE_CALC_SET;
$tohor_flag = Constants::SE_EQU2HOR;
$iflag |= Constants::SEFLG_EQUATORIAL;
$iflag |= Constants::SEFLG_TOPOCTR;
swe_set_topo($geopos[0], $geopos[1], $geopos[2]);
printf("Step 2: Set coordinate system\n");
printf("  rsmi = 0x%X (SE_CALC_SET)\n", $rsmi);
printf("  tohor_flag = 0x%X (SE_EQU2HOR)\n", $tohor_flag);
printf("  iflag |= EQUATORIAL|TOPOCTR = 0x%X\n", $iflag);
printf("  swe_set_topo(%.1f, %.1f, %.1f)\n\n", $geopos[0], $geopos[1], $geopos[2]);

// Step 3: Calculate samples (swecl.c:4460-4552)
$twohrs = 1.0 / 12.0;
$jmax = 14;
$tc = [];
$h = [];
$xh = [];

printf("Step 3: Sample Moon positions every 2 hours\n");
printf("  twohrs = %.8f days\n", $twohrs);
printf("  jmax = %d\n", $jmax);
printf("  Window: JD %.7f to %.7f (28 hours)\n\n", $jd_ut - $twohrs, $jd_ut + ($jmax - 1) * $twohrs);

$ipl = Constants::SE_MOON;
$dd = 0.0;
$rdi = 0.0;

for ($ii = 0, $t = $jd_ut - $twohrs; $ii <= $jmax; $ii++, $t += $twohrs) {
    $tc[$ii] = $t;

    // Get Moon position (swecl.c:4463-4467)
    $te = $t + swe_deltat_ex($t, $epheflag, $serr);
    $xc = [];
    $rc = swe_calc($te, $ipl, $iflag, $xc, $serr);
    if ($rc < 0) {
        echo "ERROR: swe_calc failed: $serr\n";
        exit(1);
    }

    // Get diameter on first iteration (swecl.c:4470-4487)
    if ($ii == 0) {
        $dd = 3476300.0; // Moon diameter in meters (swecl.c:4485)
        $curdist = $xc[2];
        printf("\n  DEBUG: First sample distance calculation:\n");
        printf("    xc[0] (RA) = %.6f°\n", $xc[0]);
        printf("    xc[1] (Dec) = %.6f°\n", $xc[1]);
        printf("    xc[2] (Distance) = %.9f AU\n", $xc[2]);
        printf("    dd (Moon diameter) = %.0f meters\n", $dd);
        printf("    AUNIT = %.0f meters (1 AU)\n", 149597870700.0);
        printf("    rdi = asin(dd/2/AUNIT/curdist) * RADTOdeg\n");
        printf("        = asin(%.0f / 2 / %.0f / %.9f)\n", $dd, 149597870700.0, $curdist);
        $rdi = rad2deg(asin($dd / 2.0 / 149597870700.0 / $curdist));
        printf("        = %.6f°\n", $rdi);
        printf("        = %.2f arc-minutes\n", $rdi * 60);
        printf("        = %.1f arc-seconds\n\n", $rdi * 3600);
    }

    // Convert to horizon coordinates (swecl.c:4489-4514)
    $xhii = [];
    swe_azalt($t, $tohor_flag, $xc, $geopos, $atpress, $attemp, $xhii, $serr);

    // Apply disc edge (upper edge for SET)
    $xhii[1] += $rdi;

    // Apply refraction (swecl.c:4502-4514)
    swe_azalt_rev($t, Constants::SE_HOR2EQU, $xhii, $geopos, $xc, $serr);
    swe_azalt($t, Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $xhii, $serr);
    $xhii[1] -= $horhgt;
    $xhii[2] -= $horhgt;
    $h[$ii] = $xhii[2];  // refracted altitude

    $xh[$ii] = $xhii;

    $date = swe_revjul($t, Constants::SE_GREG_CAL);
    printf("  Sample %2d: JD %.7f (%02d:%02d UT) h = %+8.4f° (rdi=%.4f°)\n",
        $ii, $t, (int)$date['ut'], (int)((($date['ut'] - (int)$date['ut']) * 60)),
        $h[$ii], $rdi);
}

echo "\nStep 4: Look for horizon crossings\n\n";

for ($ii = 1; $ii <= $jmax; $ii++) {
    // Check for sign change (swecl.c:4620-4621)
    if ($h[$ii - 1] * $h[$ii] >= 0) {
        continue;
    }

    // Check if it's SET (height decreasing)
    if ($h[$ii - 1] < $h[$ii]) {
        printf("  Skip sample %d: RISE (h[%d]=%.4f < h[%d]=%.4f)\n", $ii, $ii-1, $h[$ii-1], $ii, $h[$ii]);
        continue;
    }

    printf("  Found SET crossing at sample %d: h[%d]=%.4f > h[%d]=%.4f\n", $ii, $ii-1, $h[$ii-1], $ii, $h[$ii]);
    printf("    Time window: JD %.7f to %.7f\n", $tc[$ii-1], $tc[$ii]);

    // Binary search for exact time (swecl.c:4626-4688)
    $t1 = $tc[$ii - 1];
    $t2 = $tc[$ii];
    $h1 = $h[$ii - 1];
    $h2 = $h[$ii];

    printf("    Binary search (20 iterations):\n");

    for ($i = 0; $i < 20; $i++) {
        $t_mid = ($t1 + $t2) / 2.0;

        $te = $t_mid + swe_deltat_ex($t_mid, $epheflag, $serr);
        $xc = [];
        swe_calc($te, $ipl, $iflag, $xc, $serr);

        $curdist = $xc[2];
        $rdi_mid = rad2deg(asin($dd / 2.0 / 149597870700.0 / $curdist));

        $ah = [];
        swe_azalt($t_mid, $tohor_flag, $xc, $geopos, $atpress, $attemp, $ah, $serr);
        $ah[1] += $rdi_mid;

        swe_azalt_rev($t_mid, Constants::SE_HOR2EQU, $ah, $geopos, $xc, $serr);
        swe_azalt($t_mid, Constants::SE_EQU2HOR, $xc, $geopos, $atpress, $attemp, $ah, $serr);
        $ah[1] -= $horhgt;
        $ah[2] -= $horhgt;
        $h_mid = $ah[2];

        if ($i < 3 || $i == 19) {
            $date_mid = swe_revjul($t_mid, Constants::SE_GREG_CAL);
            printf("      Iter %2d: JD %.9f (%02d:%02d:%06.3f) h = %+.6f°\n",
                $i, $t_mid, (int)$date_mid['ut'],
                (int)((($date_mid['ut'] - (int)$date_mid['ut']) * 60)),
                ((($date_mid['ut'] - (int)$date_mid['ut']) * 60) - (int)((($date_mid['ut'] - (int)$date_mid['ut']) * 60))) * 60,
                $h_mid);
        } else if ($i == 3) {
            printf("      ... (iterations 3-18 omitted) ...\n");
        }

        // Bisection (swecl.c:4681-4686)
        if ($h_mid * $h1 <= 0) {
            $h2 = $h_mid;
            $t2 = $t_mid;
        } else {
            $h1 = $h_mid;
            $t1 = $t_mid;
        }
    }

    $t_final = ($t1 + $t2) / 2.0;
    $date_final = swe_revjul($t_final, Constants::SE_GREG_CAL);

    printf("\n    Final time: JD %.9f = %02d:%02d:%06.3f UT\n",
        $t_final,
        (int)$date_final['ut'],
        (int)((($date_final['ut'] - (int)$date_final['ut']) * 60)),
        ((($date_final['ut'] - (int)$date_final['ut']) * 60) - (int)((($date_final['ut'] - (int)$date_final['ut']) * 60))) * 60);

    // Check if after tjd_ut (swecl.c:4689-4693)
    if ($t_final > $jd_ut) {
        printf("    ✅ Event is AFTER tjd_ut (%.7f > %.7f) - RETURN\n", $t_final, $jd_ut);
        break;
    } else {
        printf("    ⏩ Event is BEFORE tjd_ut (%.7f <= %.7f) - CONTINUE\n\n", $t_final, $jd_ut);
    }
}

echo "\n=== Expected from swetest64.exe: 16:26:27.6 UT ===\n";
