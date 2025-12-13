// Step-by-step debug version for comparing C and PHP implementations
// Compile and run:
// cd C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph
// gcc -o test_steps.exe C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\php-swisseph\test_heliacal_step_by_step.c swehel.c swehouse.c swejpl.c swemmoon.c swemplan.c swedate.c sweph.c swephlib.c swecl.c -lm -I. && .\test_steps.exe

#include <stdio.h>
#include <string.h>
#include <math.h>
#include "swephexp.h"
#include "swephlib.h"

// From swehel.c - synodic period calculation
static double get_synodic_period(int ipl) {
    double period[] = {
        87.969,    // Mercury
        224.701,   // Venus
        0,         // Earth
        779.936,   // Mars
        398.884,   // Jupiter
        378.092,   // Saturn
        369.656,   // Uranus
        367.486,   // Neptune
        0,         // Pluto
        0          // mean_node
    };
    return period[ipl];
}

// From swehel.c - reference epochs for conjunctions
static double tcon_table[] = {
    // Mercury: [0]=morning first, [1]=evening last
    2451550.0, 2451550.0,
    // Venus: [2]=morning first, [3]=evening last
    2451996.0, 2451996.0,
    // Mars: [4]=morning first, [5]=evening last
    2451310.0, 2452179.0,
    // Jupiter: [6]=morning first, [7]=evening last
    2451163.0, 2451754.0,
    // Saturn: [8]=morning first, [9]=evening last
    2451294.0, 2451485.0,
    // Uranus: [10]=morning first, [11]=evening last
    2451704.0, 2451338.0,
    // Neptune: [12]=morning first, [13]=evening last
    2451753.0, 2451569.0,
    // Pluto: [14]=morning first, [15]=evening last
    2451629.0, 2451819.0
};

void find_conjunct_sun_debug(double tjd_start, int ipl, int TypeEvent) {
    int32 epheflag = SEFLG_SWIEPH;
    double daspect = 0.0;
    double x[6], xs[6];
    double ds, tjdcon, tjd0, dsynperiod;
    char serr[256];
    int i, iteration = 0;

    printf("\n=== FIND_CONJUNCT_SUN Debug ===\n");
    printf("Input: tjd_start=%.5f, ipl=%d (Venus), TypeEvent=%d\n", tjd_start, ipl, TypeEvent);

    // Determine aspect (0=conjunction, 180=opposition)
    if (ipl >= SE_MARS && TypeEvent >= 3) {
        daspect = 180.0;
    }
    printf("Aspect: %.1f degrees (0=conjunction)\n", daspect);

    // Get reference epoch
    i = (int)(((TypeEvent - 1) / 2) + ipl * 2);
    tjd0 = tcon_table[i];
    printf("Reference epoch (tcon_table[%d]): %.5f\n", i, tjd0);

    // Calculate synodic period
    dsynperiod = get_synodic_period(ipl);
    printf("Synodic period: %.2f days\n", dsynperiod);

    // Initial conjunction estimate
    tjdcon = tjd0 + floor((tjd_start - tjd0) / dsynperiod + 1) * dsynperiod;
    printf("Initial conjunction estimate: tjd0 + floor((%.5f - %.5f) / %.2f + 1) * %.2f\n",
           tjd_start, tjd0, dsynperiod, dsynperiod);
    printf("  = %.5f + floor(%.5f + 1) * %.2f\n", tjd0, (tjd_start - tjd0) / dsynperiod, dsynperiod);
    printf("  = %.5f + %.0f * %.2f\n", tjd0, floor((tjd_start - tjd0) / dsynperiod + 1), dsynperiod);
    printf("  = %.5f\n", tjdcon);

    printf("\n--- Newton's Method Iterations ---\n");
    ds = 100.0;
    while (ds > 0.5) {
        iteration++;

        // Calculate planet position
        if (swe_calc(tjdcon, ipl, epheflag | SEFLG_SPEED, x, serr) == ERR) {
            printf("ERROR: swe_calc planet failed: %s\n", serr);
            return;
        }

        // Calculate Sun position
        if (swe_calc(tjdcon, SE_SUN, epheflag | SEFLG_SPEED, xs, serr) == ERR) {
            printf("ERROR: swe_calc Sun failed: %s\n", serr);
            return;
        }

        // Calculate angular distance
        double raw_ds = x[0] - xs[0] - daspect;
        ds = swe_degnorm(raw_ds);
        if (ds > 180.0) {
            ds -= 360.0;
        }

        double speed_diff = x[3] - xs[3];
        double correction = ds / speed_diff;

        printf("Iter %d: tjd=%.8f\n", iteration, tjdcon);
        printf("  Planet: lon=%.6f, lat=%.6f, dist=%.6f AU, speed=%.6f deg/day\n",
               x[0], x[1], x[2], x[3]);
        printf("  Sun:    lon=%.6f, lat=%.6f, dist=%.6f AU, speed=%.6f deg/day\n",
               xs[0], xs[1], xs[2], xs[3]);
        printf("  Angular diff (raw): %.6f deg\n", raw_ds);
        printf("  Angular diff (norm): %.6f deg (after swe_degnorm + wrap)\n", ds);
        printf("  Speed diff: %.6f deg/day\n", speed_diff);
        printf("  Correction: %.6f / %.6f = %.8f days\n", ds, speed_diff, correction);

        tjdcon -= correction;
        printf("  New tjdcon: %.8f\n", tjdcon);

        if (fabs(ds) <= 0.5) {
            printf("  CONVERGED (ds <= 0.5)\n");
            break;
        }
    }

    printf("\n--- Superior/Inferior Conjunction Check ---\n");
    // Recalculate position at final conjunction
    if (swe_calc(tjdcon, ipl, epheflag, x, serr) == ERR) {
        printf("ERROR: Final swe_calc failed: %s\n", serr);
        return;
    }

    double planet_dist = x[2];
    printf("Planet distance at conjunction: %.6f AU\n", planet_dist);

    if (ipl <= SE_VENUS && TypeEvent <= 2 && daspect == 0.0) {
        printf("Checking conjunction type for inner planet (TypeEvent=%d)...\n", TypeEvent);

        if (planet_dist > 0.8) {
            printf("  >>> SUPERIOR conjunction detected (dist > 0.8 AU)\n");
            printf("  >>> Adjusting to INFERIOR: tjdcon -= %.2f / 2\n", dsynperiod);
            tjdcon -= dsynperiod / 2.0;
            printf("  >>> New tjdcon: %.5f\n", tjdcon);

            // Verify the new position
            if (swe_calc(tjdcon, ipl, epheflag, x, serr) == ERR) {
                printf("ERROR: Verification swe_calc failed: %s\n", serr);
                return;
            }
            printf("  >>> Verification: planet distance now %.6f AU\n", x[2]);
        } else {
            printf("  >>> INFERIOR conjunction confirmed (dist < 0.8 AU)\n");
        }
    }

    printf("\n=== FINAL RESULT: tjdcon = %.8f ===\n", tjdcon);
}

int main() {
    double tjd_start = 2451697.5;
    double dgeo[3] = {13.4, 52.5, 100.0};
    double datm[4] = {1013.25, 15.0, 40.0, 0.0};
    double dobs[6] = {36.0, 1.0, 0, 1.0, 0.0, 0.0};
    double dret[50];
    char serr[256];

    swe_set_ephe_path("C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe");

    printf("========================================\n");
    printf("  C Step-by-Step Heliacal Debug\n");
    printf("========================================\n");
    printf("\nVenus Heliacal Rising (TypeEvent=1)\n");
    printf("Start date: JD %.5f (2000-06-01)\n", tjd_start);
    printf("Location: %.1fE, %.1fN, %.0fm\n", dgeo[0], dgeo[1], dgeo[2]);
    printf("Expected result: JD 2452004.66233\n");

    printf("\n\n=== FIND_CONJUNCT_SUN Debug ===\n");
    printf("Input: tjd_start=%.5f, ipl=%d (should be SE_VENUS=%d), TypeEvent=%d\n",
           tjd_start, SE_VENUS, SE_VENUS, TypeEvent);

    // Step 1: Show find_conjunct_sun in detail
    find_conjunct_sun_debug(tjd_start, SE_VENUS, 1);

    // Step 2: Run full heliacal_ut and see result
    printf("\n\n=== Running full swe_heliacal_ut ===\n");
    int retval = swe_heliacal_ut(tjd_start, dgeo, datm, dobs, "Venus", 1, SEFLG_SWIEPH, dret, serr);

    if (retval < 0) {
        printf("Result: FAILED (retval=%d)\n", retval);
        printf("Error: %s\n", serr);
        printf("Last dret[0]: %.8f\n", dret[0]);
    } else {
        printf("Result: SUCCESS\n");
        printf("Event JD: %.8f\n", dret[0]);
        printf("Expected: 2452004.66233000\n");
        printf("Difference: %.8f days (%.2f seconds)\n",
               dret[0] - 2452004.66233,
               (dret[0] - 2452004.66233) * 86400);
    }

    swe_close();
    return 0;
}
