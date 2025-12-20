<?php

$r = 1.8497202617423; // AU
$v = 0.025624020818601; // AU/day

// Convert to SI
$r_m = $r * 1.495978707e11; // meters
$v_ms = $v * 1.495978707e11 / 86400; // m/s

$GM = 1.32712440017987e+20; // m^3/s^2

echo "=== Mars orbital energy check ===\n\n";
echo "Distance: $r AU = " . ($r_m / 1e9) . " million km\n";
echo "Speed: $v AU/day = " . ($v_ms / 1000) . " km/s\n\n";

$E_kin = 0.5 * $v_ms * $v_ms;
$E_pot = -$GM / $r_m;
$E_total = $E_kin + $E_pot;

echo "Kinetic energy: " . ($E_kin / 1e6) . " MJ/kg\n";
echo "Potential energy: " . ($E_pot / 1e6) . " MJ/kg\n";
echo "Total energy: " . ($E_total / 1e6) . " MJ/kg\n\n";

if ($E_total < 0) {
    echo "✅ Elliptical orbit (bound)\n";

    // Semi-major axis from energy: a = -GM / (2*E)
    $a_m = -$GM / (2 * $E_total);
    $a_au = $a_m / 1.495978707e11;
    echo "Semi-major axis (from energy): $a_au AU\n\n";

    // Expected for Mars: ~1.524 AU
    echo "Expected for Mars: ~1.524 AU\n";
} else {
    echo "❌ Hyperbolic orbit (unbound) - WRONG!\n";
}

// Using formula sema = 1 / (2/r - v²/GM) as in osculating code
$Gmsm = $GM / (1.495978707e11)**3 * 86400**2; // AU³/day²
$v2 = $v * $v; // AU²/day²
$sema_formula = 1 / (2/$r - $v2/$Gmsm);

echo "\nUsing osculating formula: sema = 1 / (2/r - v²/GM)\n";
echo "  2/r = " . (2/$r) . "\n";
echo "  v²/GM = " . ($v2/$Gmsm) . "\n";
echo "  2/r - v²/GM = " . (2/$r - $v2/$Gmsm) . "\n";
echo "  sema = $sema_formula AU\n";
