#include <stdio.h>
/*
    planet_harness.c — минимальный C-харнесс для Swiss Ephemeris
    Сборка (PowerShell из папки php-swisseph/tests):
        gcc -O2 -I ../../с-swisseph/swisseph -o planet_harness.exe planet_harness.c \
                ../../с-swisseph/swisseph/swedate.c \
                ../../с-swisseph/swisseph/swehouse.c \
                ../../с-swisseph/swisseph/swejpl.c \
                ../../с-swisseph/swisseph/swemmoon.c \
                ../../с-swisseph/swisseph/swemplan.c \
                ../../с-swisseph/swisseph/sweph.c \
                ../../с-swisseph/swisseph/swephlib.c \
                ../../с-swisseph/swisseph/swecl.c \
                ../../с-swisseph/swisseph/swehel.c -lm

    Запуск:
        .\planet_harness.exe "..\\..\\eph\\ephe"

    Выводит JSON с массивами позиций Юпитера и Сатурна на наборе JD.
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>

/* Include Swiss Ephemeris C API */
#include "../../с-swisseph/swisseph/swephexp.h"
#define ARRAY_LEN(x) (sizeof(x)/sizeof((x)[0]))

static void json_escape(const char *src, FILE *out) {
    for (const char *p = src; *p; ++p) {
        unsigned char c = (unsigned char)*p;
        switch (c) {
        case '\\': fputs("\\\\", out); break;
        case '"': fputs("\\\"", out); break;
        case '\n': fputs("\\n", out); break;
        case '\r': fputs("\\r", out); break;
        case '\t': fputs("\\t", out); break;
        default:
            if (c < 0x20) fprintf(out, "\\u%04x", c);
            else fputc(c, out);
        }
    }
}

int main(int argc, char **argv) {
    const char *ephe_path = NULL;
    if (argc >= 2) {
        ephe_path = argv[1];
    } else {
        ephe_path = "..\\eph\\ephe"; /* default relative to tests directory */
    }

    swe_set_ephe_path((char*)ephe_path);

    /* JD list (TT) */
    const double jds[] = {2451545.0, 2453000.5, 2448000.5, 2460000.5};

    const int planets[] = { SE_JUPITER, SE_SATURN };
    const char *planet_names[] = { "jupiter", "saturn" }; /* parallel arrays */

    printf("{\n");
    printf("  \"ephe\": \"");
    json_escape(ephe_path, stdout);
    printf("\",\n");

    /* JDs */
    printf("  \"jds\": [");
    for (size_t i = 0; i < ARRAY_LEN(jds); ++i) {
        if (i) printf(", ");
        printf("%.1f", jds[i]);
    }
    printf("],\n");

    printf("  \"planets\": {\n");

    for (size_t pi = 0; pi < ARRAY_LEN(planets); ++pi) {
        int ipl = planets[pi];
        const char *pname = planet_names[pi];
        printf("    \"%s\": [\n", pname);
        for (size_t ji = 0; ji < ARRAY_LEN(jds); ++ji) {
            double jd = jds[ji];
            double xx[6];
            char serr[256] = {0};
            int ret;
            /* Ecliptic (geocentric apparent) with speed */
            ret = swe_calc(jd, ipl, SEFLG_SPEED, xx, serr);
            if (ret < 0) {
                fprintf(stderr, "swe_calc ecl error planet %d jd %.1f: %s\n", ipl, jd, serr);
                return 1;
            }
            double lon = xx[0];
            double lat = xx[1];
            double dist = xx[2];
            double xxEq[6];
            serr[0] = '\0';
            ret = swe_calc(jd, ipl, SEFLG_EQUATORIAL | SEFLG_SPEED, xxEq, serr);
            if (ret < 0) {
                fprintf(stderr, "swe_calc equ error planet %d jd %.1f: %s\n", ipl, jd, serr);
                return 1;
            }
            double ra = xxEq[0];
            double dec = xxEq[1];
            double distEq = xxEq[2];

            printf("      { \"jd\": %.1f, \"ecl\": { \"lon\": %.10f, \"lat\": %.10f, \"r\": %.10f }, \"equ\": { \"ra\": %.10f, \"dec\": %.10f, \"r\": %.10f } }", jd, lon, lat, dist, ra, dec, distEq);
            if (ji + 1 < ARRAY_LEN(jds)) printf(",");
            printf("\n");
        }
        printf("    ]");
        if (pi + 1 < ARRAY_LEN(planets)) printf(",");
        printf("\n");
    }

    printf("  }\n");
    printf("}\n");
    return 0;
}
