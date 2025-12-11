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
     * Number of lunar Saros series (1-180)
     * From swecl.c:305
     */
    public const NSAROS_LUNAR = 180;

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

    /**
     * Lunar Saros cycle data
     * Ported from swecl.c:306-485 (saros_data_lunar[NSAROS_LUNAR])
     *
     * Each entry: [series_no, tstart]
     * - series_no: Saros series number (1-180)
     * - tstart: Julian day of first eclipse in series
     *
     * Data derived from NASA Eclipse Web Site:
     * https://eclipse.gsfc.nasa.gov/LEsaros/LEsaroscat.html
     *
     * Note: For eclipse dates <= 29 April -1337 and >= 10 Aug 2892,
     * Saros cycle numbers cannot always be determined.
     *
     * @var array<int, array{series_no: int, tstart: float}>
     */
    public const SAROS_DATA_LUNAR = [
        ['series_no' => 1, 'tstart' => 782437.5],    // 14 Mar -2570
        ['series_no' => 2, 'tstart' => 799593.5],    // 03 Mar -2523
        ['series_no' => 3, 'tstart' => 783824.5],    // 30 Dec -2567
        ['series_no' => 4, 'tstart' => 754884.5],    // 06 Oct -2646
        ['series_no' => 5, 'tstart' => 824724.5],    // 22 Dec -2455
        ['series_no' => 6, 'tstart' => 762857.5],    // 04 Aug -2624
        ['series_no' => 7, 'tstart' => 773430.5],    // 16 Jul -2595
        ['series_no' => 8, 'tstart' => 810343.5],    // 08 Aug -2494
        ['series_no' => 9, 'tstart' => 807743.5],    // 26 Jun -2501
        ['series_no' => 10, 'tstart' => 824901.5],   // 17 Jun -2454
        ['series_no' => 11, 'tstart' => 855229.5],   // 29 Jun -2371
        ['series_no' => 12, 'tstart' => 859215.5],   // 28 May -2360
        ['series_no' => 13, 'tstart' => 876373.5],   // 20 May -2313
        ['series_no' => 14, 'tstart' => 906701.5],   // 01 Jun -2230
        ['series_no' => 15, 'tstart' => 910687.5],   // 30 Apr -2219
        ['series_no' => 16, 'tstart' => 927845.5],   // 21 Apr -2172
        ['series_no' => 17, 'tstart' => 958173.5],   // 04 May -2089
        ['series_no' => 18, 'tstart' => 962159.5],   // 02 Apr -2078
        ['series_no' => 19, 'tstart' => 979317.5],   // 24 Mar -2031
        ['series_no' => 20, 'tstart' => 1009645.5],  // 05 Apr -1948
        ['series_no' => 21, 'tstart' => 1007046.5],  // 22 Feb -1955
        ['series_no' => 22, 'tstart' => 1017618.5],  // 02 Feb -1926
        ['series_no' => 23, 'tstart' => 1054531.5],  // 25 Feb -1825
        ['series_no' => 24, 'tstart' => 979493.5],   // 16 Sep -2031
        ['series_no' => 25, 'tstart' => 976895.5],   // 06 Aug -2038
        ['series_no' => 26, 'tstart' => 1020394.5],  // 09 Sep -1919
        ['series_no' => 27, 'tstart' => 1017794.5],  // 28 Jul -1926
        ['series_no' => 28, 'tstart' => 1028367.5],  // 09 Jul -1897
        ['series_no' => 29, 'tstart' => 1058695.5],  // 21 Jul -1814
        ['series_no' => 30, 'tstart' => 1062681.5],  // 19 Jun -1803
        ['series_no' => 31, 'tstart' => 1073253.5],  // 30 May -1774
        ['series_no' => 32, 'tstart' => 1110167.5],  // 23 Jun -1673
        ['series_no' => 33, 'tstart' => 1114153.5],  // 22 May -1662
        ['series_no' => 34, 'tstart' => 1131311.5],  // 13 May -1615
        ['series_no' => 35, 'tstart' => 1161639.5],  // 25 May -1532
        ['series_no' => 36, 'tstart' => 1165625.5],  // 24 Apr -1521
        ['series_no' => 37, 'tstart' => 1176197.5],  // 03 Apr -1492
        ['series_no' => 38, 'tstart' => 1213111.5],  // 27 Apr -1391
        ['series_no' => 39, 'tstart' => 1217097.5],  // 26 Mar -1380
        ['series_no' => 40, 'tstart' => 1221084.5],  // 24 Feb -1369
        ['series_no' => 41, 'tstart' => 1257997.5],  // 18 Mar -1268
        ['series_no' => 42, 'tstart' => 1255398.5],  // 04 Feb -1275
        ['series_no' => 43, 'tstart' => 1186946.5],  // 07 Sep -1463
        ['series_no' => 44, 'tstart' => 1283128.5],  // 06 Jan -1199
        ['series_no' => 45, 'tstart' => 1227845.5],  // 29 Aug -1351
        ['series_no' => 46, 'tstart' => 1225247.5],  // 19 Jul -1358
        ['series_no' => 47, 'tstart' => 1255575.5],  // 31 Jul -1275
        ['series_no' => 48, 'tstart' => 1272732.5],  // 21 Jul -1228
        ['series_no' => 49, 'tstart' => 1276719.5],  // 21 Jun -1217
        ['series_no' => 50, 'tstart' => 1307047.5],  // 03 Jul -1134
        ['series_no' => 51, 'tstart' => 1317619.5],  // 13 Jun -1105
        ['series_no' => 52, 'tstart' => 1328191.5],  // 23 May -1076
        ['series_no' => 53, 'tstart' => 1358519.5],  // 05 Jun -0993
        ['series_no' => 54, 'tstart' => 1375676.5],  // 26 May -0946
        ['series_no' => 55, 'tstart' => 1379663.5],  // 25 Apr -0935
        ['series_no' => 56, 'tstart' => 1409991.5],  // 07 May -0852
        ['series_no' => 57, 'tstart' => 1420562.5],  // 16 Apr -0823
        ['series_no' => 58, 'tstart' => 1424549.5],  // 16 Mar -0812
        ['series_no' => 59, 'tstart' => 1461463.5],  // 09 Apr -0711
        ['series_no' => 60, 'tstart' => 1465449.5],  // 08 Mar -0700
        ['series_no' => 61, 'tstart' => 1436509.5],  // 13 Dec -0780
        ['series_no' => 62, 'tstart' => 1493179.5],  // 08 Feb -0624
        ['series_no' => 63, 'tstart' => 1457653.5],  // 03 Nov -0722
        ['series_no' => 64, 'tstart' => 1435298.5],  // 20 Aug -0783
        ['series_no' => 65, 'tstart' => 1452456.5],  // 11 Aug -0736
        ['series_no' => 66, 'tstart' => 1476198.5],  // 12 Aug -0671
        ['series_no' => 67, 'tstart' => 1480184.5],  // 11 Jul -0660
        ['series_no' => 68, 'tstart' => 1503928.5],  // 14 Jul -0595
        ['series_no' => 69, 'tstart' => 1527670.5],  // 15 Jul -0530
        ['series_no' => 70, 'tstart' => 1531656.5],  // 13 Jun -0519
        ['series_no' => 71, 'tstart' => 1548814.5],  // 04 Jun -0472
        ['series_no' => 72, 'tstart' => 1579142.5],  // 17 Jun -0389
        ['series_no' => 73, 'tstart' => 1583128.5],  // 16 May -0378
        ['series_no' => 74, 'tstart' => 1600286.5],  // 07 May -0331
        ['series_no' => 75, 'tstart' => 1624028.5],  // 08 May -0266
        ['series_no' => 76, 'tstart' => 1628015.5],  // 07 Apr -0255
        ['series_no' => 77, 'tstart' => 1651758.5],  // 09 Apr -0190
        ['series_no' => 78, 'tstart' => 1675500.5],  // 10 Apr -0125
        ['series_no' => 79, 'tstart' => 1672901.5],  // 27 Feb -0132
        ['series_no' => 80, 'tstart' => 1683474.5],  // 07 Feb -0103
        ['series_no' => 81, 'tstart' => 1713801.5],  // 19 Feb -0020
        ['series_no' => 82, 'tstart' => 1645349.5],  // 21 Sep -0208
        ['series_no' => 83, 'tstart' => 1649336.5],  // 22 Aug -0197
        ['series_no' => 84, 'tstart' => 1686249.5],  // 13 Sep -0096
        ['series_no' => 85, 'tstart' => 1683650.5],  // 02 Aug -0103
        ['series_no' => 86, 'tstart' => 1694222.5],  // 13 Jul -0074
        ['series_no' => 87, 'tstart' => 1731136.5],  // 06 Aug 0027
        ['series_no' => 88, 'tstart' => 1735122.5],  // 05 Jul 0038
        ['series_no' => 89, 'tstart' => 1745694.5],  // 15 Jun 0067
        ['series_no' => 90, 'tstart' => 1776022.5],  // 27 Jun 0150
        ['series_no' => 91, 'tstart' => 1786594.5],  // 07 Jun 0179
        ['series_no' => 92, 'tstart' => 1797166.5],  // 17 May 0208
        ['series_no' => 93, 'tstart' => 1827494.5],  // 30 May 0291
        ['series_no' => 94, 'tstart' => 1838066.5],  // 09 May 0320
        ['series_no' => 95, 'tstart' => 1848638.5],  // 19 Apr 0349
        ['series_no' => 96, 'tstart' => 1878966.5],  // 01 May 0432
        ['series_no' => 97, 'tstart' => 1882952.5],  // 31 Mar 0443
        ['series_no' => 98, 'tstart' => 1880354.5],  // 18 Feb 0436
        ['series_no' => 99, 'tstart' => 1923853.5],  // 24 Mar 0555
        ['series_no' => 100, 'tstart' => 1881741.5], // 06 Dec 0439
        ['series_no' => 101, 'tstart' => 1852801.5], // 11 Sep 0360
        ['series_no' => 102, 'tstart' => 1889715.5], // 05 Oct 0461
        ['series_no' => 103, 'tstart' => 1893701.5], // 03 Sep 0472
        ['series_no' => 104, 'tstart' => 1897688.5], // 04 Aug 0483
        ['series_no' => 105, 'tstart' => 1928016.5], // 16 Aug 0566
        ['series_no' => 106, 'tstart' => 1938588.5], // 27 Jul 0595
        ['series_no' => 107, 'tstart' => 1942575.5], // 26 Jun 0606
        ['series_no' => 108, 'tstart' => 1972903.5], // 08 Jul 0689
        ['series_no' => 109, 'tstart' => 1990059.5], // 27 Jun 0736
        ['series_no' => 110, 'tstart' => 1994046.5], // 28 May 0747
        ['series_no' => 111, 'tstart' => 2024375.5], // 10 Jun 0830
        ['series_no' => 112, 'tstart' => 2034946.5], // 20 May 0859
        ['series_no' => 113, 'tstart' => 2045518.5], // 29 Apr 0888
        ['series_no' => 114, 'tstart' => 2075847.5], // 13 May 0971
        ['series_no' => 115, 'tstart' => 2086418.5], // 21 Apr 1000
        ['series_no' => 116, 'tstart' => 2083820.5], // 11 Mar 0993
        ['series_no' => 117, 'tstart' => 2120733.5], // 03 Apr 1094
        ['series_no' => 118, 'tstart' => 2124719.5], // 02 Mar 1105
        ['series_no' => 119, 'tstart' => 2062852.5], // 14 Oct 0935
        ['series_no' => 120, 'tstart' => 2086596.5], // 16 Oct 1000
        ['series_no' => 121, 'tstart' => 2103752.5], // 06 Oct 1047
        ['series_no' => 122, 'tstart' => 2094568.5], // 14 Aug 1022
        ['series_no' => 123, 'tstart' => 2118311.5], // 16 Aug 1087
        ['series_no' => 124, 'tstart' => 2142054.5], // 17 Aug 1152
        ['series_no' => 125, 'tstart' => 2146040.5], // 17 Jul 1163
        ['series_no' => 126, 'tstart' => 2169783.5], // 18 Jul 1228
        ['series_no' => 127, 'tstart' => 2186940.5], // 09 Jul 1275
        ['series_no' => 128, 'tstart' => 2197512.5], // 18 Jun 1304
        ['series_no' => 129, 'tstart' => 2214670.5], // 10 Jun 1351
        ['series_no' => 130, 'tstart' => 2238412.5], // 10 Jun 1416
        ['series_no' => 131, 'tstart' => 2242398.5], // 10 May 1427
        ['series_no' => 132, 'tstart' => 2266142.5], // 12 May 1492
        ['series_no' => 133, 'tstart' => 2289884.5], // 13 May 1557
        ['series_no' => 134, 'tstart' => 2287285.5], // 01 Apr 1550
        ['series_no' => 135, 'tstart' => 2311028.5], // 13 Apr 1615
        ['series_no' => 136, 'tstart' => 2334770.5], // 13 Apr 1680
        ['series_no' => 137, 'tstart' => 2292659.5], // 17 Dec 1564
        ['series_no' => 138, 'tstart' => 2276890.5], // 15 Oct 1521
        ['series_no' => 139, 'tstart' => 2326974.5], // 09 Dec 1658
        ['series_no' => 140, 'tstart' => 2304619.5], // 25 Sep 1597
        ['series_no' => 141, 'tstart' => 2308606.5], // 25 Aug 1608
        ['series_no' => 142, 'tstart' => 2345520.5], // 19 Sep 1709
        ['series_no' => 143, 'tstart' => 2349506.5], // 18 Aug 1720
        ['series_no' => 144, 'tstart' => 2360078.5], // 29 Jul 1749
        ['series_no' => 145, 'tstart' => 2390406.5], // 11 Aug 1832
        ['series_no' => 146, 'tstart' => 2394392.5], // 11 Jul 1843
        ['series_no' => 147, 'tstart' => 2411550.5], // 02 Jul 1890
        ['series_no' => 148, 'tstart' => 2441878.5], // 15 Jul 1973
        ['series_no' => 149, 'tstart' => 2445864.5], // 13 Jun 1984
        ['series_no' => 150, 'tstart' => 2456437.5], // 25 May 2013
        ['series_no' => 151, 'tstart' => 2486765.5], // 06 Jun 2096
        ['series_no' => 152, 'tstart' => 2490751.5], // 07 May 2107
        ['series_no' => 153, 'tstart' => 2501323.5], // 16 Apr 2136
        ['series_no' => 154, 'tstart' => 2538236.5], // 10 May 2237
        ['series_no' => 155, 'tstart' => 2529052.5], // 18 Mar 2212
        ['series_no' => 156, 'tstart' => 2473771.5], // 08 Nov 2060
        ['series_no' => 157, 'tstart' => 2563367.5], // 01 Mar 2306
        ['series_no' => 158, 'tstart' => 2508085.5], // 21 Oct 2154
        ['series_no' => 159, 'tstart' => 2505486.5], // 09 Sep 2147
        ['series_no' => 160, 'tstart' => 2542400.5], // 03 Oct 2248
        ['series_no' => 161, 'tstart' => 2546386.5], // 02 Sep 2259
        ['series_no' => 162, 'tstart' => 2556958.5], // 12 Aug 2288
        ['series_no' => 163, 'tstart' => 2587287.5], // 27 Aug 2371
        ['series_no' => 164, 'tstart' => 2597858.5], // 05 Aug 2400
        ['series_no' => 165, 'tstart' => 2601845.5], // 06 Jul 2411
        ['series_no' => 166, 'tstart' => 2632173.5], // 18 Jul 2494
        ['series_no' => 167, 'tstart' => 2649330.5], // 09 Jul 2541
        ['series_no' => 168, 'tstart' => 2653317.5], // 08 Jun 2552
        ['series_no' => 169, 'tstart' => 2683645.5], // 22 Jun 2635
        ['series_no' => 170, 'tstart' => 2694217.5], // 01 Jun 2664
        ['series_no' => 171, 'tstart' => 2698203.5], // 01 May 2675
        ['series_no' => 172, 'tstart' => 2728532.5], // 15 May 2758
        ['series_no' => 173, 'tstart' => 2739103.5], // 24 Apr 2787
        ['series_no' => 174, 'tstart' => 2683822.5], // 16 Dec 2635
        ['series_no' => 175, 'tstart' => 2740492.5], // 11 Feb 2791
        ['series_no' => 176, 'tstart' => 2724722.5], // 09 Dec 2747
        ['series_no' => 177, 'tstart' => 2708952.5], // 05 Oct 2704
        ['series_no' => 178, 'tstart' => 2732695.5], // 07 Oct 2769
        ['series_no' => 179, 'tstart' => 2749852.5], // 27 Sep 2816
        ['series_no' => 180, 'tstart' => 2753839.5], // 28 Aug 2827
    ];

    /**
     * Get lunar Saros cycle data
     *
     * @return array<int, array{series_no: int, tstart: float}>
     */
    public static function getLunarSarosData(): array
    {
        return self::SAROS_DATA_LUNAR;
    }}
