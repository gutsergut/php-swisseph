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



    retval = swe_heliacal_ut(tjd_start, dgeo, datm, dobs, "Venus", 1, SEFLG_SWIEPH, dret, serr);

    printf("C: Return value: %d\n", retval);
    if (serr[0] != '\0') {
        printf("C: Message: %s\n", serr);
    }
    if (retval >= 0) {
        printf("C: Event JD: %.8f\n", dret[0]);
        printf("C: Expected:  2452004.66233000\n");
        printf("C: Diff: %.6f days\n", dret[0] - 2452004.66233);
    } else {
        printf("C: Last dret[0]: %.8f\n", dret[0]);
    }

    swe_close();
    return 0;
}

