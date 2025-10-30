<?php

require __DIR__ . '/../vendor/autoload.php';

use Swisseph\Nutation\FundamentalArguments;

// Test at J2000.0
$jd = 2451545.0;

echo "Fundamental Arguments at J2000.0:\n\n";

// Simon 1994 (luni-solar)
$args = FundamentalArguments::calcSimon1994($jd);
echo "Simon et al. 1994 (luni-solar):\n";
echo sprintf("  M  (Moon anomaly):      %12.9f rad  (%9.5f°)\n", $args['M'], rad2deg($args['M']));
echo sprintf("  SM (Sun anomaly):       %12.9f rad  (%9.5f°)\n", $args['SM'], rad2deg($args['SM']));
echo sprintf("  F  (Moon latitude arg): %12.9f rad  (%9.5f°)\n", $args['F'], rad2deg($args['F']));
echo sprintf("  D  (Moon elongation):   %12.9f rad  (%9.5f°)\n", $args['D'], rad2deg($args['D']));
echo sprintf("  OM (Moon node):         %12.9f rad  (%9.5f°)\n", $args['OM'], rad2deg($args['OM']));

// Delaunay MHB2000 (planetary)
$delaunay = FundamentalArguments::calcDelaunayMHB2000($jd);
echo "\nDelaunay MHB2000 (planetary nutation):\n";
echo sprintf("  AL   (Moon anomaly):    %12.9f rad  (%9.5f°)\n", $delaunay['AL'], rad2deg($delaunay['AL']));
echo sprintf("  ALSU (Sun anomaly):     %12.9f rad  (%9.5f°)\n", $delaunay['ALSU'], rad2deg($delaunay['ALSU']));
echo sprintf("  AF   (Moon latitude):   %12.9f rad  (%9.5f°)\n", $delaunay['AF'], rad2deg($delaunay['AF']));
echo sprintf("  AD   (Moon elongation): %12.9f rad  (%9.5f°)\n", $delaunay['AD'], rad2deg($delaunay['AD']));
echo sprintf("  AOM  (Moon node):       %12.9f rad  (%9.5f°)\n", $delaunay['AOM'], rad2deg($delaunay['AOM']));

// Planetary longitudes
$planets = FundamentalArguments::calcSouchay1999($jd);
echo "\nSouchay et al. 1999 (planetary longitudes):\n";
echo sprintf("  ALME (Mercury): %12.9f rad  (%9.5f°)\n", $planets['ALME'], rad2deg($planets['ALME']));
echo sprintf("  ALVE (Venus):   %12.9f rad  (%9.5f°)\n", $planets['ALVE'], rad2deg($planets['ALVE']));
echo sprintf("  ALEA (Earth):   %12.9f rad  (%9.5f°)\n", $planets['ALEA'], rad2deg($planets['ALEA']));
echo sprintf("  ALMA (Mars):    %12.9f rad  (%9.5f°)\n", $planets['ALMA'], rad2deg($planets['ALMA']));
echo sprintf("  ALJU (Jupiter): %12.9f rad  (%9.5f°)\n", $planets['ALJU'], rad2deg($planets['ALJU']));
echo sprintf("  ALSA (Saturn):  %12.9f rad  (%9.5f°)\n", $planets['ALSA'], rad2deg($planets['ALSA']));
echo sprintf("  ALUR (Uranus):  %12.9f rad  (%9.5f°)\n", $planets['ALUR'], rad2deg($planets['ALUR']));
echo sprintf("  ALNE (Neptune): %12.9f rad  (%9.5f°)\n", $planets['ALNE'], rad2deg($planets['ALNE']));

// General precession
$apa = FundamentalArguments::calcGeneralPrecession($jd);
echo sprintf("\nGeneral precession: %12.9f rad  (%9.5f°)\n", $apa, rad2deg($apa));

// Test at a different epoch: 2024-01-01
$jd2024 = 2460310.5;
echo "\n\nFundamental Arguments at 2024-01-01:\n\n";

$args2024 = FundamentalArguments::calcSimon1994($jd2024);
echo "Simon et al. 1994:\n";
echo sprintf("  OM (Moon node): %12.9f rad  (%9.5f°)\n", $args2024['OM'], rad2deg($args2024['OM']));
