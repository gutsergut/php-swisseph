// Debug version of heliacal test
// Compile and run:
// cd C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph
// gcc -o test_debug.exe C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\php-swisseph\test_heliacal_debug.c swehel.c swehouse.c swejpl.c swemmoon.c swemplan.c swedate.c sweph.c swephlib.c swecl.c -lm -I. && .\test_debug.exe

#include <stdio.h>
#include <string.h>
#include "swephexp.h"

int main() {
    double tjd_start = 2451697.5;
    double dgeo[3] = {13.4, 52.5, 100.0};
    double datm[4] = {1013.25, 15.0, 40.0, 0.0};
    double dobs[6] = {36.0, 1.0, 0, 1.0, 0.0, 0.0};
    double dret[50];
    double darr[10];
    char serr[256];
    int32 retval;
    int i;

    swe_set_ephe_path("C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe");

    printf("=== C Debug: Venus Heliacal Rising ===\n\n");
    printf("Start JD: %.5f (2000-06-01)\n", tjd_start);
    printf("Location: Berlin (%.1f°E, %.1f°N, %.0fm)\n", dgeo[0], dgeo[1], dgeo[2]);
    printf("Atmosphere: P=%.2f hPa, T=%.1f°C, RH=%.0f%%\n\n", datm[0], datm[1], datm[2]);

    printf("--- Calling swe_heliacal_ut ---\n");
    fflush(stdout);

    retval = swe_heliacal_ut(tjd_start, dgeo, datm, dobs, "Venus", 1, SEFLG_SWIEPH, dret, serr);

    printf("Result: ");
    if (retval < 0) {
        printf("FAILED\n");
        printf("Error: %s\n", serr);
        printf("Last dret[0]: %.8f\n", dret[0]);
    } else {
        printf("SUCCESS\n");
        printf("Event JD: %.8f\n", dret[0]);
        printf("Expected: 2452004.66233000\n");
        printf("Diff: %.8f days (%.2f seconds)\n",
               dret[0] - 2452004.66233,
               (dret[0] - 2452004.66233) * 86400);

        // Test VLM at the found event date
        printf("\n--- Testing VLM at found event date ---\n");
        for (i = 0; i < 10; i++) darr[i] = 0.0;
        serr[0] = '\0';

        printf("Calling swe_vis_limit_mag(%.8f, ...)...\n", dret[0]);
        fflush(stdout);

        int vlm_ret = swe_vis_limit_mag(dret[0], dgeo, datm, dobs, "Venus", SEFLG_SWIEPH, darr, serr);

        printf("VLM retval: %d", vlm_ret);
        if (serr[0] != '\0') printf(" (%s)", serr);
        printf("\n");

        if (vlm_ret >= 0 || vlm_ret == -2) {
            printf("  VLM (darr[0]):   %9.6f\n", darr[0]);
            printf("  AltO (darr[1]):  %9.6f deg\n", darr[1]);
            printf("  Magn (darr[7]):  %9.6f\n", darr[7]);
            printf("  vdelta:          %9.6f %s\n",
                   darr[0] - darr[7],
                   (darr[0] - darr[7] > 0) ? "(VISIBLE)" : "(NOT VISIBLE)");
        }
    }

    swe_close();
    return 0;
}
