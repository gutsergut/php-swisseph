# BUG: swe_calc returns incorrect topocentric Moon coordinates

## Problem Description

`swe_calc()` with flags `SEFLG_EQUATORIAL | SEFLG_TOPOCTR | SEFLG_XYZ` returns incorrect cartesian coordinates for the Moon when called from PHP implementation.

## Evidence

### Test Parameters
- Time: JD 2460409.2630702 (2024-04-08)
- Location: Dallas, TX (lon=-96.8°, lat=32.8°, alt=0m)
- Flags: `SEFLG_EQUATORIAL | SEFLG_TOPOCTR | SEFLG_XYZ`
- Body: SE_MOON (Moon)

### Results Comparison

**C (correct):**
```
xm (cartesian) = [0.002234363, 0.000716638, 0.000307623, ...]
lm (equatorial) = [17.782866401°, 7.468893526°, 0.002366554 AU, ...]
x2 (normalized) = [0.944141723, 0.302819337, 0.129987907]
```

**PHP (incorrect):**
```
xm (cartesian) = [0.002268970, 0.000726062, 0.000330582, ...]
lm (equatorial) = [17.744493038°, 7.900213704°, 0.002405135 AU, ...]
x2 (normalized) = [0.943385584, 0.301880145, 0.137448240]
```

### Coordinate Differences
- Δx = 0.000035 AU (~5200 km)
- Δy = 0.000009 AU (~1400 km)  
- Δz = 0.000023 AU (~3400 km)
- **Total error: ~6600 km in 3D space**

### Angular Differences
- Right Ascension: 17.783° (C) vs 17.744° (PHP) - Δ = 0.039° = 2.3 arcmin
- Declination: 7.469° (C) vs 7.900° (PHP) - Δ = 0.431° = 25.9 arcmin
- Distance: 0.002367 AU (C) vs 0.002405 AU (PHP) - Δ = 0.000038 AU (~5700 km)

## Impact

This bug causes `swe_sol_eclipse_when_loc()` to fail:
1. Iterative refinement starts with wrong angular distance (0.348° instead of 0.171°)
2. Refinement cannot converge to correct minimum
3. Function finds PARTIAL eclipse 25 minutes too early instead of correct TOTAL eclipse
4. Test case: 2024-04-08 Dallas eclipse
   - Expected: JD 2460409.279639, TOTAL, magnitude 1.0147
   - PHP finds: JD 2460409.262373, PARTIAL, magnitude 0.3658

## Test Files
- `tests/test_moon_coordinates.php` - PHP test
- `с-swisseph/swisseph/test_moon_coordinates.c` - C reference test
- Both tests use identical parameters for direct comparison

## Suspected Cause

The error is systematic and affects all three cartesian components. Possible causes:
1. Topocentric correction not applied correctly
2. Wrong Earth rotation angle or observer position calculation
3. Issue in coordinate transformation (equatorial → cartesian)
4. Problem in the underlying Moon position calculation (swemmoon.c port)

The difference is too large to be numerical precision - this is a logic error in the topocentric transformation or Moon ephemeris calculation.

## Priority

**CRITICAL** - This bug blocks all topocentric lunar calculations including:
- Local solar eclipses (`swe_sol_eclipse_when_loc`)
- Local lunar eclipses
- Occultations
- Any topocentric Moon phenomena

## Next Steps

1. Review PHP port of topocentric correction in `swe_calc`
2. Check Moon ephemeris calculation (`swemmoon.c` → PHP)
3. Verify coordinate transformations (equatorial → cartesian with topocentric)
4. Compare intermediate values step-by-step between C and PHP

## Files to Investigate
- `src/Swe/Functions/EphemerisFunctions.php` - Main `swe_calc` implementation
- `src/Swe/Ephemeris/MoonCalculator.php` - Moon position calculation
- `src/Swe/Coordinates/TopocentricCorrection.php` - Topocentric transformation
- `src/Swe/Coordinates/EquatorialToCartesian.php` - Coordinate conversion
