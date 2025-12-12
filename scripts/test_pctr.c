/*
 * Test planetocentric calculations using original Swiss Ephemeris DLL
 * Compile: gcc -o test_pctr.exe test_pctr.c -L. -lswedll64
 */

#include <stdio.h>
#include <string.h>
#include <math.h>

/* Swiss Ephemeris function declarations */
__declspec(dllimport) void swe_set_ephe_path(char *path);
__declspec(dllimport) double swe_julday(int year, int month, int day, double hour, int gregflag);
__declspec(dllimport) double swe_deltat_ex(double tjd_ut, int ephe_flag, char *serr);
__declspec(dllimport) int swe_calc(double tjd_et, int ipl, int iflag, double *xx, char *serr);
__declspec(dllimport) char *swe_version(char *s);

/* Constants from swephexp.h */
#define SE_GREG_CAL 1
#define SEFLG_SWIEPH 2
#define SEFLG_SPEED 256
#define SEFLG_BARYCTR (1 << 5)
#define SEFLG_J2000 (1 << 1)
#define SEFLG_ICRS (1 << 4)
#define SEFLG_TRUEPOS (1 << 9)
#define SEFLG_EQUATORIAL (1 << 10)
#define SEFLG_XYZ (1 << 11)
#define SEFLG_NOABERR (1 << 12)
#define SEFLG_NOGDEFL (1 << 14)

#define RAD2DEG (180.0 / M_PI)
#define DEG2RAD (M_PI / 180.0)

int main() {
    char version[256];
    char serr[256];
    double jd_ut, jd_et;
    double xx[6], xx_venus[6], xx_mars[6];
    int ret;
    int iflag, iflag_bary;

    printf("=================================================================\n");
    printf("=== Swiss Ephemeris Planetocentric Test (Original C DLL)    ===\n");
    printf("=================================================================\n\n");

    swe_version(version);
    printf("Swiss Ephemeris version: %s\n\n", version);

    /* Set ephemeris path */
    swe_set_ephe_path("..\\..\\eph\\ephe");

    /* Test date: 1.1.2000 12:00 UT */
    jd_ut = swe_julday(2000, 1, 1, 12.0, SE_GREG_CAL);
    jd_et = jd_ut + swe_deltat_ex(jd_ut, SEFLG_SWIEPH, serr);

    printf("Date: 1.1.2000 12:00 UT\n");
    printf("JD_UT: %.10f\n", jd_ut);
    printf("JD_ET: %.10f\n\n", jd_et);

    /* ===================================================================== */
    /* PART 1: Barycentric coordinates (for comparison with PHP)            */
    /* ===================================================================== */
    printf("--- PART 1: Barycentric Coordinates ---\n\n");

    iflag_bary = SEFLG_SWIEPH | SEFLG_BARYCTR | SEFLG_J2000 | SEFLG_ICRS |
                 SEFLG_TRUEPOS | SEFLG_EQUATORIAL | SEFLG_XYZ | SEFLG_SPEED |
                 SEFLG_NOABERR | SEFLG_NOGDEFL;

    /* Venus barycentric */
    ret = swe_calc(jd_et, 3, iflag_bary, xx_venus, serr);
    if (ret < 0) {
        printf("ERROR Venus: %s\n", serr);
        return 1;
    }

    printf("Venus (barycentric J2000 ICRS equatorial XYZ):\n");
    printf("  XYZ: [%.12f, %.12f, %.12f]\n", xx_venus[0], xx_venus[1], xx_venus[2]);
    printf("  VEL: [%.12f, %.12f, %.12f]\n", xx_venus[3], xx_venus[4], xx_venus[5]);

    double r_v = sqrt(xx_venus[0]*xx_venus[0] + xx_venus[1]*xx_venus[1] + xx_venus[2]*xx_venus[2]);
    double ra_v = atan2(xx_venus[1], xx_venus[0]);
    if (ra_v < 0) ra_v += 2 * M_PI;
    double dec_v = asin(xx_venus[2] / r_v);
    printf("  RA:  %.6f° (%.2fh)\n", ra_v * RAD2DEG, ra_v * RAD2DEG / 15.0);
    printf("  Dec: %.6f°\n", dec_v * RAD2DEG);
    printf("  Dist: %.9f AU\n\n", r_v);

    /* Mars barycentric */
    ret = swe_calc(jd_et, 4, iflag_bary, xx_mars, serr);
    if (ret < 0) {
        printf("ERROR Mars: %s\n", serr);
        return 1;
    }

    printf("Mars (barycentric J2000 ICRS equatorial XYZ):\n");
    printf("  XYZ: [%.12f, %.12f, %.12f]\n", xx_mars[0], xx_mars[1], xx_mars[2]);
    printf("  VEL: [%.12f, %.12f, %.12f]\n", xx_mars[3], xx_mars[4], xx_mars[5]);

    double r_m = sqrt(xx_mars[0]*xx_mars[0] + xx_mars[1]*xx_mars[1] + xx_mars[2]*xx_mars[2]);
    double ra_m = atan2(xx_mars[1], xx_mars[0]);
    if (ra_m < 0) ra_m += 2 * M_PI;
    double dec_m = asin(xx_mars[2] / r_m);
    printf("  RA:  %.6f° (%.2fh)\n", ra_m * RAD2DEG, ra_m * RAD2DEG / 15.0);
    printf("  Dec: %.6f°\n", dec_m * RAD2DEG);
    printf("  Dist: %.9f AU\n\n", r_m);

    /* Simple subtraction */
    double xx_diff[3];
    xx_diff[0] = xx_mars[0] - xx_venus[0];
    xx_diff[1] = xx_mars[1] - xx_venus[1];
    xx_diff[2] = xx_mars[2] - xx_venus[2];

    printf("Mars - Venus (simple subtraction):\n");
    printf("  XYZ: [%.12f, %.12f, %.12f]\n", xx_diff[0], xx_diff[1], xx_diff[2]);

    double r_diff = sqrt(xx_diff[0]*xx_diff[0] + xx_diff[1]*xx_diff[1] + xx_diff[2]*xx_diff[2]);
    double ra_diff = atan2(xx_diff[1], xx_diff[0]);
    if (ra_diff < 0) ra_diff += 2 * M_PI;
    double dec_diff = asin(xx_diff[2] / r_diff);
    printf("  RA:  %.6f°\n", ra_diff * RAD2DEG);
    printf("  Dec: %.6f°\n", dec_diff * RAD2DEG);
    printf("  Dist: %.9f AU\n\n", r_diff);

    /* Convert to ecliptic */
    double eps_j2000 = 23.4392794444444 * DEG2RAD;
    double sin_eps = sin(eps_j2000);
    double cos_eps = cos(eps_j2000);

    double xx_ecl[3];
    xx_ecl[0] = xx_diff[0];
    xx_ecl[1] = xx_diff[1] * cos_eps + xx_diff[2] * sin_eps;
    xx_ecl[2] = -xx_diff[1] * sin_eps + xx_diff[2] * cos_eps;

    double lon_diff = atan2(xx_ecl[1], xx_ecl[0]);
    if (lon_diff < 0) lon_diff += 2 * M_PI;
    double lat_diff = asin(xx_ecl[2] / r_diff);

    printf("  Ecliptic Lon: %.7f°\n", lon_diff * RAD2DEG);
    printf("  Ecliptic Lat: %.7f°\n\n", lat_diff * RAD2DEG);

    /* ===================================================================== */
    /* PART 2: Analysis - What should Y-coordinate be?                      */
    /* ===================================================================== */
    printf("--- PART 2: Expected Y-coordinate Analysis ---\n\n");

    /* If reference lon=359.4388477° and r=2.11 AU, what should Y be? */
    double ref_lon = 359.4388477;
    double ref_lat = -1.4197691;

    double ref_lon_rad = ref_lon * DEG2RAD;
    double ref_lat_rad = ref_lat * DEG2RAD;

    /* For ecliptic coordinates: X = r*cos(lat)*cos(lon), Y = r*cos(lat)*sin(lon) */
    double expected_X = r_diff * cos(ref_lat_rad) * cos(ref_lon_rad);
    double expected_Y = r_diff * cos(ref_lat_rad) * sin(ref_lon_rad);
    double expected_Z = r_diff * sin(ref_lat_rad);

    printf("If reference values are correct (lon=%.7f°, lat=%.7f°, r=%.9f AU):\n",
           ref_lon, ref_lat, r_diff);
    printf("  Expected ecliptic XYZ: [%.9f, %.9f, %.9f]\n",
           expected_X, expected_Y, expected_Z);
    printf("  Actual from subtraction: [%.9f, %.9f, %.9f]\n\n",
           xx_ecl[0], xx_ecl[1], xx_ecl[2]);

    printf("Difference:\n");
    printf("  ΔX: %.9f AU\n", fabs(xx_ecl[0] - expected_X));
    printf("  ΔY: %.9f AU (%.1fx too large)\n",
           fabs(xx_ecl[1] - expected_Y),
           fabs(xx_ecl[1] / expected_Y));
    printf("  ΔZ: %.9f AU\n\n", fabs(xx_ecl[2] - expected_Z));

    /* ===================================================================== */
    /* PART 3: Verify with swetest output                                   */
    /* ===================================================================== */
    printf("--- PART 3: Reference Values ---\n\n");

    printf("Simple barycentric subtraction gives: Lon=%.7f°  Lat=%.7f°\n",
           lon_diff * RAD2DEG, lat_diff * RAD2DEG);
    printf("swetest64 reference (unknown flags):  Lon=%.7f°  Lat=%.7f°\n\n",
           ref_lon, ref_lat);

    double diff_lon = lon_diff * RAD2DEG - ref_lon;
    if (diff_lon > 180.0) diff_lon -= 360.0;
    if (diff_lon < -180.0) diff_lon += 360.0;

    printf("Difference from reference:\n");
    printf("  ΔLon: %.7f° (%.1f arcmin)\n", fabs(diff_lon), fabs(diff_lon * 60));
    printf("  ΔLat: %.7f° (%.1f arcmin)\n\n",
           fabs(lat_diff * RAD2DEG - ref_lat),
           fabs((lat_diff * RAD2DEG - ref_lat) * 60));

    printf("\n=================================================================\n");
    printf("CONCLUSION: Simple barycentric subtraction does NOT match reference.\n");
    printf("This suggests:\n");
    printf("1. Reference values include light-time or other corrections\n");
    printf("2. OR reference was calculated with different method\n");
    printf("3. OR reference flag was parsed incorrectly\n");

    return 0;
}
