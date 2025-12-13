// Compile:
// cd C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\с-swisseph\swisseph
// gcc -o test_heliacal_venus.exe C:\Users\serge\OneDrive\Documents\Fractal\Projects\Component\Swisseph\php-swisseph\test_heliacal_venus.c swehel.c swehouse.c swejpl.c swemmoon.c swemplan.c swedate.c sweph.c swephlib.c swecl.c -lm -I.

#include <stdio.h>
#include <string.h>
#include "swephexp.h"

int main() {
    double tjd_start = 2451697.5; // 2000-06-01 00:00 UT
    double dgeo[3] = {13.4, 52.5, 100.0}; // Berlin: lon, lat, alt(m)
    double datm[4] = {1013.25, 15.0, 40.0, 0.0}; // pressure, temp, RH, VR
    double dobs[6] = {36.0, 1.0, 0, 1.0, 0.0, 0.0}; // age, SN, binocular, mag, aperture, transmission
    double dret[50];
    double darr[10];
    char serr[256];
    int32 retval;
    int i;

    // Set ephemeris path
    swe_set_ephe_path("C:\\Users\\serge\\OneDrive\\Documents\\Fractal\\Projects\\Component\\Swisseph\\eph\\ephe");

    printf("=== C Test: Venus Heliacal Rising (Full Debug) ===\n\n");
    printf("Start JD: %.5f (2000-06-01)\n", tjd_start);
    printf("Location: Berlin (%.1f°E, %.1f°N, %.0fm)\n", dgeo[0], dgeo[1], dgeo[2]);
    printf("Atmosphere: P=%.2f hPa, T=%.1f°C, RH=%.0f%%\n\n", datm[0], datm[1], datm[2]);

    // First, test swe_vis_limit_mag at a specific time
    printf("--- Test swe_vis_limit_mag at JD 2452253.79569 ---\n");
    double test_jd = 2452253.79569;

    // Initialize darr
    for (i = 0; i < 10; i++) darr[i] = 0.0;
    serr[0] = '\0';

    retval = swe_vis_limit_mag(test_jd, dgeo, datm, dobs, "Venus", SEFLG_SWIEPH, darr, serr);
    printf("Return value: %d\n", retval);
    if (serr[0] != '\0') {
        printf("Error/Warning: %s\n", serr);
    }
    if (retval == -2) {
        printf("Object below horizon\n");
    }
    if (retval >= 0 || retval == -2) {
        printf("darr[0] (VLM):      %.6f\n", darr[0]);
        printf("darr[1] (AltO):     %.6f\n", darr[1]);
        printf("darr[2] (AziO):     %.6f\n", darr[2]);
        printf("darr[3] (AltS):     %.6f\n", darr[3]);
        printf("darr[4] (AziS):     %.6f\n", darr[4]);
        printf("darr[5] (AltM):     %.6f\n", darr[5]);
        printf("darr[6] (AziM):     %.6f\n", darr[6]);
        printf("darr[7] (Magn):     %.6f\n", darr[7]);
        printf("vdelta = VLM - Magn: %.6f\n", darr[0] - darr[7]);
        printf("Object visible: %s\n\n", (darr[0] - darr[7] > 0) ? "YES" : "NO");
    }

    // Call heliacal rising function
    printf("--- Full swe_heliacal_ut call ---\n");
    retval = swe_heliacal_ut(
        tjd_start,
        dgeo,
        datm,
        dobs,
        "Venus",
        1, // SE_HELIACAL_RISING
        SEFLG_SWIEPH,
        dret,
        serr
    );

    if (retval < 0) {
        printf("ERROR: %s\n", serr);
        printf("Last dret[0]: %.8f\n\n", dret[0]);
    } else {
        printf("Result: SUCCESS\n");
        printf("Event JD: %.8f\n", dret[0]);
        printf("Expected: 2452004.66233\n");
        printf("Diff: %.5f days\n\n", dret[0] - 2452004.66233);

        // Show additional return values
        printf("Return values:\n");
        printf("  dret[0] (event JD):     %.8f\n", dret[0]);
        printf("  dret[1] (opt time):     %.8f\n", dret[1]);
        printf("  dret[2] (end time):     %.8f\n", dret[2]);
        printf("  dret[3] (duration min): %.2f\n", dret[3]);
    }

    swe_close();
    return 0;
}
