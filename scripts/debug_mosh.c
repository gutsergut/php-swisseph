/* Debug program to trace Moshier calculation */
#include <stdio.h>
#include <math.h>

#define J2000 2451545.0
#define TIMESCALE 3652500.0
#define STR 4.8481368110953599359e-6

static double freqs[] = {
    53810162868.8982,
    21066413643.3548,
    12959774228.3429,
    6890507749.3988,
    1092566037.7991,
    439960985.5372,
    154248119.3933,
    78655032.0744,
    52272245.1795
};

static double phases[] = {
    252.25090552 * 3600.,
    181.97980085 * 3600.,
    100.46645683 * 3600.,
    355.43299958 * 3600.,
    34.35151874 * 3600.,
    50.07744430 * 3600.,
    314.05500511 * 3600.,
    304.34866548 * 3600.,
    860492.1546,
};

static double mods3600(double x) {
    return x - 1.296e6 * floor(x / 1.296e6);
}

int main() {
    double J = 2451545.0;  /* J2000.0 */
    double T = (J - J2000) / TIMESCALE;

    printf("=== Moshier Debug C ===\n\n");
    printf("J = %.10f\n", J);
    printf("T = %.20e (should be 0 at J2000)\n\n", T);

    /* Mercury (i=0) */
    double sr = (mods3600(freqs[0] * T) + phases[0]) * STR;
    printf("Mercury (i=0):\n");
    printf("  freqs[0] = %.10f\n", freqs[0]);
    printf("  phases[0] = %.10f arcsec\n", phases[0]);
    printf("  freqs[0] * T = %.20e\n", freqs[0] * T);
    printf("  mods3600(freqs[0] * T) = %.20f\n", mods3600(freqs[0] * T));
    printf("  sr = %.20f rad\n", sr);
    printf("  sin(sr) = %.15f\n", sin(sr));
    printf("  cos(sr) = %.15f\n", cos(sr));

    return 0;
}
