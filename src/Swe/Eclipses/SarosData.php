<?php

declare(strict_types=1);

namespace Swisseph\Swe\Eclipses;

/**
 * Saros cycle data for eclipse predictions
 * Ported from swecl.c:108-298 (Swiss Ephemeris C library)
 *
 * Saros cycle: ~6585.3213 days (~18 years, 11 days, 8 hours)
 * After one Saros period, eclipses repeat with similar geometry.
 *
 * Data derived from NASA Eclipse Web Site:
 * https://eclipse.gsfc.nasa.gov/SEsaros/SEsaros0-180.html
 *
 * Note: For eclipse dates <= 15 Feb -1604 and >= 2 Sep 2666,
 * Saros cycle numbers cannot always be determined.
 */
class SarosData
{
    /**
     * Saros cycle length in days
     * From swecl.c:108
     */
    public const SAROS_CYCLE = 6585.3213;

    /**
     * Number of solar Saros series (0-180)
     * From swecl.c:109
     */
    public const NSAROS_SOLAR = 181;

    /**
     * Solar Saros cycle data
     * Ported from swecl.c:110-298 (saros_data_solar[NSAROS_SOLAR])
     *
     * Each entry: [series_no, tstart]
     * - series_no: Saros series number (0-180)
     * - tstart: Julian day of first eclipse in series
     *
     * @var array<int, array{series_no: int, tstart: float}>
     */
    public const SAROS_DATA_SOLAR = [
        ['series_no' => 0, 'tstart' => 641886.5],    // 23 May -2955
        ['series_no' => 1, 'tstart' => 672214.5],    // 04 Jun -2872
        ['series_no' => 2, 'tstart' => 676200.5],    // 04 May -2861
        ['series_no' => 3, 'tstart' => 693357.5],    // 24 Apr -2814
        ['series_no' => 4, 'tstart' => 723685.5],    // 06 May -2731
        ['series_no' => 5, 'tstart' => 727671.5],    // 04 Apr -2720
        ['series_no' => 6, 'tstart' => 744829.5],    // 27 Mar -2673
        ['series_no' => 7, 'tstart' => 775157.5],    // 08 Apr -2590
        ['series_no' => 8, 'tstart' => 779143.5],    // 07 Mar -2579
        ['series_no' => 9, 'tstart' => 783131.5],    // 06 Feb -2568
        ['series_no' => 10, 'tstart' => 820044.5],   // 28 Feb -2467
        ['series_no' => 11, 'tstart' => 810859.5],   // 06 Jan -2492
        ['series_no' => 12, 'tstart' => 748993.5],   // 20 Aug -2662
        ['series_no' => 13, 'tstart' => 792492.5],   // 23 Sep -2543
        ['series_no' => 14, 'tstart' => 789892.5],   // 11 Aug -2550
        ['series_no' => 15, 'tstart' => 787294.5],   // 01 Jul -2557
        ['series_no' => 16, 'tstart' => 824207.5],   // 23 Jul -2456
        ['series_no' => 17, 'tstart' => 834779.5],   // 03 Jul -2427
        ['series_no' => 18, 'tstart' => 838766.5],   // 02 Jun -2416
        ['series_no' => 19, 'tstart' => 869094.5],   // 15 Jun -2333
        ['series_no' => 20, 'tstart' => 886251.5],   // 05 Jun -2286
        ['series_no' => 21, 'tstart' => 890238.5],   // 05 May -2275
        ['series_no' => 22, 'tstart' => 927151.5],   // 28 May -2174
        ['series_no' => 23, 'tstart' => 937722.5],   // 07 May -2145
        ['series_no' => 24, 'tstart' => 941709.5],   // 06 Apr -2134
        ['series_no' => 25, 'tstart' => 978623.5],   // 30 Apr -2033
        ['series_no' => 26, 'tstart' => 989194.5],   // 08 Apr -2004
        ['series_no' => 27, 'tstart' => 993181.5],   // 09 Mar -1993
        ['series_no' => 28, 'tstart' => 1023510.5],  // 22 Mar -1910
        ['series_no' => 29, 'tstart' => 1034081.5],  // 01 Mar -1881
        ['series_no' => 30, 'tstart' => 972214.5],   // 12 Oct -2051
        ['series_no' => 31, 'tstart' => 1061811.5],  // 31 Jan -1805
        ['series_no' => 32, 'tstart' => 1006529.5],  // 24 Sep -1957
        ['series_no' => 33, 'tstart' => 997345.5],   // 02 Aug -1982
        ['series_no' => 34, 'tstart' => 1021088.5],  // 04 Aug -1917
        ['series_no' => 35, 'tstart' => 1038245.5],  // 25 Jul -1870
        ['series_no' => 36, 'tstart' => 1042231.5],  // 23 Jun -1859
        ['series_no' => 37, 'tstart' => 1065974.5],  // 25 Jun -1794
        ['series_no' => 38, 'tstart' => 1089716.5],  // 26 Jun -1729
        ['series_no' => 39, 'tstart' => 1093703.5],  // 26 May -1718
        ['series_no' => 40, 'tstart' => 1117446.5],  // 28 May -1653
        ['series_no' => 41, 'tstart' => 1141188.5],  // 28 May -1588
        ['series_no' => 42, 'tstart' => 1145175.5],  // 28 Apr -1577
        ['series_no' => 43, 'tstart' => 1168918.5],  // 29 Apr -1512
        ['series_no' => 44, 'tstart' => 1192660.5],  // 30 Apr -1447
        ['series_no' => 45, 'tstart' => 1196647.5],  // 30 Mar -1436
        ['series_no' => 46, 'tstart' => 1220390.5],  // 01 Apr -1371
        ['series_no' => 47, 'tstart' => 1244132.5],  // 02 Apr -1306
        ['series_no' => 48, 'tstart' => 1234948.5],  // 08 Feb -1331
        ['series_no' => 49, 'tstart' => 1265277.5],  // 22 Feb -1248
        ['series_no' => 50, 'tstart' => 1282433.5],  // 11 Feb -1201
        ['series_no' => 51, 'tstart' => 1207395.5],  // 02 Sep -1407
        ['series_no' => 52, 'tstart' => 1217968.5],  // 14 Aug -1378
        ['series_no' => 53, 'tstart' => 1254881.5],  // 06 Sep -1277
        ['series_no' => 54, 'tstart' => 1252282.5],  // 25 Jul -1284
        ['series_no' => 55, 'tstart' => 1262855.5],  // 06 Jul -1255
        ['series_no' => 56, 'tstart' => 1293182.5],  // 17 Jul -1172
        ['series_no' => 57, 'tstart' => 1297169.5],  // 17 Jun -1161
        ['series_no' => 58, 'tstart' => 1314326.5],  // 07 Jun -1114
        ['series_no' => 59, 'tstart' => 1344654.5],  // 19 Jun -1031
        ['series_no' => 60, 'tstart' => 1348640.5],  // 18 May -1020
        ['series_no' => 61, 'tstart' => 1365798.5],  // 10 May -0973
        ['series_no' => 62, 'tstart' => 1396126.5],  // 22 May -0890
        ['series_no' => 63, 'tstart' => 1400112.5],  // 20 Apr -0879
        ['series_no' => 64, 'tstart' => 1417270.5],  // 11 Apr -0832
        ['series_no' => 65, 'tstart' => 1447598.5],  // 24 Apr -0749
        ['series_no' => 66, 'tstart' => 1444999.5],  // 12 Mar -0756
        ['series_no' => 67, 'tstart' => 1462157.5],  // 04 Mar -0709
        ['series_no' => 68, 'tstart' => 1492485.5],  // 16 Mar -0626
        ['series_no' => 69, 'tstart' => 1456959.5],  // 09 Dec -0724
        ['series_no' => 70, 'tstart' => 1421434.5],  // 05 Sep -0821
        ['series_no' => 71, 'tstart' => 1471518.5],  // 19 Oct -0684
        ['series_no' => 72, 'tstart' => 1455748.5],  // 16 Aug -0727
        ['series_no' => 73, 'tstart' => 1466320.5],  // 27 Jul -0698
        ['series_no' => 74, 'tstart' => 1496648.5],  // 08 Aug -0615
        ['series_no' => 75, 'tstart' => 1500634.5],  // 07 Jul -0604
        ['series_no' => 76, 'tstart' => 1511207.5],  // 18 Jun -0575
        ['series_no' => 77, 'tstart' => 1548120.5],  // 11 Jul -0474
        ['series_no' => 78, 'tstart' => 1552106.5],  // 09 Jun -0463
        ['series_no' => 79, 'tstart' => 1562679.5],  // 21 May -0434
        ['series_no' => 80, 'tstart' => 1599592.5],  // 13 Jun -0333
        ['series_no' => 81, 'tstart' => 1603578.5],  // 12 May -0322
        ['series_no' => 82, 'tstart' => 1614150.5],  // 22 Apr -0293
        ['series_no' => 83, 'tstart' => 1644479.5],  // 05 May -0210
        ['series_no' => 84, 'tstart' => 1655050.5],  // 14 Apr -0181
        ['series_no' => 85, 'tstart' => 1659037.5],  // 14 Mar -0170
        ['series_no' => 86, 'tstart' => 1695950.5],  // 06 Apr -0069
        ['series_no' => 87, 'tstart' => 1693351.5],  // 23 Feb -0076
        ['series_no' => 88, 'tstart' => 1631484.5],  // 06 Oct -0246
        ['series_no' => 89, 'tstart' => 1727666.5],  // 04 Feb 0018
        ['series_no' => 90, 'tstart' => 1672384.5],  // 28 Sep -0134
        ['series_no' => 91, 'tstart' => 1663200.5],  // 06 Aug -0159
        ['series_no' => 92, 'tstart' => 1693529.5],  // 19 Aug -0076
        ['series_no' => 93, 'tstart' => 1710685.5],  // 09 Aug -0029
        ['series_no' => 94, 'tstart' => 1714672.5],  // 09 Jul -0018
        ['series_no' => 95, 'tstart' => 1738415.5],  // 11 Jul 0047
        ['series_no' => 96, 'tstart' => 1755572.5],  // 01 Jul 0094
        ['series_no' => 97, 'tstart' => 1766144.5],  // 11 Jun 0123
        ['series_no' => 98, 'tstart' => 1789887.5],  // 12 Jun 0188
        ['series_no' => 99, 'tstart' => 1807044.5],  // 03 Jun 0235
        ['series_no' => 100, 'tstart' => 1817616.5], // 13 May 0264
        ['series_no' => 101, 'tstart' => 1841359.5], // 15 May 0329
        ['series_no' => 102, 'tstart' => 1858516.5], // 05 May 0376
        ['series_no' => 103, 'tstart' => 1862502.5], // 04 Apr 0387
        ['series_no' => 104, 'tstart' => 1892831.5], // 17 Apr 0470
        ['series_no' => 105, 'tstart' => 1903402.5], // 27 Mar 0499
        ['series_no' => 106, 'tstart' => 1887633.5], // 23 Jan 0456
        ['series_no' => 107, 'tstart' => 1924547.5], // 15 Feb 0557
        ['series_no' => 108, 'tstart' => 1921948.5], // 04 Jan 0550
        ['series_no' => 109, 'tstart' => 1873251.5], // 07 Sep 0416
        ['series_no' => 110, 'tstart' => 1890409.5], // 30 Aug 0463
        ['series_no' => 111, 'tstart' => 1914151.5], // 30 Aug 0528
        ['series_no' => 112, 'tstart' => 1918138.5], // 31 Jul 0539
        ['series_no' => 113, 'tstart' => 1935296.5], // 22 Jul 0586
        ['series_no' => 114, 'tstart' => 1959038.5], // 23 Jul 0651
        ['series_no' => 115, 'tstart' => 1963024.5], // 21 Jun 0662
        ['series_no' => 116, 'tstart' => 1986767.5], // 23 Jun 0727
        ['series_no' => 117, 'tstart' => 2010510.5], // 24 Jun 0792
        ['series_no' => 118, 'tstart' => 2014496.5], // 24 May 0803
        ['series_no' => 119, 'tstart' => 2031654.5], // 15 May 0850
        ['series_no' => 120, 'tstart' => 2061982.5], // 27 May 0933
        ['series_no' => 121, 'tstart' => 2065968.5], // 25 Apr 0944
        ['series_no' => 122, 'tstart' => 2083126.5], // 17 Apr 0991
        ['series_no' => 123, 'tstart' => 2113454.5], // 29 Apr 1074
        ['series_no' => 124, 'tstart' => 2104269.5], // 06 Mar 1049
        ['series_no' => 125, 'tstart' => 2108256.5], // 04 Feb 1060
        ['series_no' => 126, 'tstart' => 2151755.5], // 10 Mar 1179
        ['series_no' => 127, 'tstart' => 2083302.5], // 10 Oct 0991
        ['series_no' => 128, 'tstart' => 2080704.5], // 29 Aug 0984
        ['series_no' => 129, 'tstart' => 2124203.5], // 03 Oct 1103
        ['series_no' => 130, 'tstart' => 2121603.5], // 20 Aug 1096
        ['series_no' => 131, 'tstart' => 2132176.5], // 01 Aug 1125
        ['series_no' => 132, 'tstart' => 2162504.5], // 13 Aug 1208
        ['series_no' => 133, 'tstart' => 2166490.5], // 13 Jul 1219
        ['series_no' => 134, 'tstart' => 2177062.5], // 22 Jun 1248
        ['series_no' => 135, 'tstart' => 2207390.5], // 05 Jul 1331
        ['series_no' => 136, 'tstart' => 2217962.5], // 14 Jun 1360
        ['series_no' => 137, 'tstart' => 2228534.5], // 25 May 1389
        ['series_no' => 138, 'tstart' => 2258862.5], // 06 Jun 1472
        ['series_no' => 139, 'tstart' => 2269434.5], // 17 May 1501
        ['series_no' => 140, 'tstart' => 2273421.5], // 16 Apr 1512
        ['series_no' => 141, 'tstart' => 2310334.5], // 19 May 1613
        ['series_no' => 142, 'tstart' => 2314320.5], // 17 Apr 1624
        ['series_no' => 143, 'tstart' => 2311722.5], // 07 Mar 1617
        ['series_no' => 144, 'tstart' => 2355221.5], // 11 Apr 1736
        ['series_no' => 145, 'tstart' => 2319695.5], // 04 Jan 1639
        ['series_no' => 146, 'tstart' => 2284169.5], // 19 Sep 1541
        ['series_no' => 147, 'tstart' => 2314498.5], // 12 Oct 1624
        ['series_no' => 148, 'tstart' => 2325069.5], // 21 Sep 1653
        ['series_no' => 149, 'tstart' => 2329056.5], // 21 Aug 1664
        ['series_no' => 150, 'tstart' => 2352799.5], // 24 Aug 1729
        ['series_no' => 151, 'tstart' => 2369956.5], // 14 Aug 1776
        ['series_no' => 152, 'tstart' => 2380528.5], // 26 Jul 1805
        ['series_no' => 153, 'tstart' => 2404271.5], // 28 Jul 1870
        ['series_no' => 154, 'tstart' => 2421428.5], // 19 Jul 1917
        ['series_no' => 155, 'tstart' => 2425414.5], // 17 Jun 1928
        ['series_no' => 156, 'tstart' => 2455743.5], // 01 Jul 2011
        ['series_no' => 157, 'tstart' => 2472900.5], // 21 Jun 2058
        ['series_no' => 158, 'tstart' => 2476886.5], // 20 May 2069
        ['series_no' => 159, 'tstart' => 2500629.5], // 23 May 2134
        ['series_no' => 160, 'tstart' => 2517786.5], // 13 May 2181
        ['series_no' => 161, 'tstart' => 2515187.5], // 01 Apr 2174
        ['series_no' => 162, 'tstart' => 2545516.5], // 15 Apr 2257
        ['series_no' => 163, 'tstart' => 2556087.5], // 25 Mar 2286
        ['series_no' => 164, 'tstart' => 2487635.5], // 24 Oct 2098
        ['series_no' => 165, 'tstart' => 2504793.5], // 16 Oct 2145
        ['series_no' => 166, 'tstart' => 2535121.5], // 29 Oct 2228
        ['series_no' => 167, 'tstart' => 2525936.5], // 06 Sep 2203
        ['series_no' => 168, 'tstart' => 2543094.5], // 28 Aug 2250
        ['series_no' => 169, 'tstart' => 2573422.5], // 10 Sep 2333
        ['series_no' => 170, 'tstart' => 2577408.5], // 09 Aug 2344
        ['series_no' => 171, 'tstart' => 2594566.5], // 01 Aug 2391
        ['series_no' => 172, 'tstart' => 2624894.5], // 13 Aug 2474
        ['series_no' => 173, 'tstart' => 2628880.5], // 12 Jul 2485
        ['series_no' => 174, 'tstart' => 2646038.5], // 04 Jul 2532
        ['series_no' => 175, 'tstart' => 2669780.5], // 05 Jul 2597
        ['series_no' => 176, 'tstart' => 2673766.5], // 04 Jun 2608
        ['series_no' => 177, 'tstart' => 2690924.5], // 27 May 2655
        ['series_no' => 178, 'tstart' => 2721252.5], // 09 Jun 2738
        ['series_no' => 179, 'tstart' => 2718653.5], // 28 Apr 2731
        ['series_no' => 180, 'tstart' => 2729226.5], // 08 Apr 2760
    ];
}
