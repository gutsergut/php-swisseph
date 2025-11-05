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

## PARTIAL FIX (2025-11-05)

### Root Cause Found
`SwephPlanCalculator::calculate()` was **incorrectly converting geocentric Moon to barycentric** by adding Earth position:
```php
// WRONG CODE (removed):
$xpm_bary[$i] = $xpm[$i] + $xpe[$i];  // barycentric = geocentric + Earth
$xp_result = $xpm_bary;
```

This caused **DOUBLE Earth addition**:
1. SwephPlanCalculator adds Earth → barycentric Moon
2. MoonTransform::appPosEtc() adds Earth again → wrong coordinates

### The Fix
C code `sweplan()` returns **GEOCENTRIC Moon** (not barycentric):
- sweph.c:1960: `xpret[i] = xp[i]` where `xp = xpm` (geocentric)
- Barycentric conversion happens LATER in `app_pos_etc_moon()`
- sweph.c:4183: `xx[i] += xe[i]` after light-time correction

Changed SwephPlanCalculator to return geocentric Moon:
```php
// CORRECT CODE:
$xp_result = $xpm;  // Return GEOCENTRIC Moon
```

### Results After Fix
- ✅ Returns correct magnitude (~0.002 AU topocentric instead of ~1 AU barycentric)
- ✅ Topocentric correction now applies correctly
- ⚠️ Remaining coordinate error: ~10,000 km (vs perfect C)
- ❌ Eclipse test still finds wrong eclipse (2026-08-12 instead of 2024-04-08)

### Remaining Issues
Coordinate differences (PHP vs C):
- ΔX ≈ 7,600 km
- ΔY ≈ 4,400 km
- ΔZ ≈ 3,500 km
- Total ~10 km 3D error

Likely causes:
1. Precession/nutation differences
2. Aberration calculation differences
3. Light-time iteration convergence
4. Observer position calculation

## Next Steps

1. ✅ ~~Review PHP port of topocentric correction~~ - FIXED
2. ✅ ~~SwephPlanCalculator Earth addition bug~~ - FIXED
3. Compare precession implementation line-by-line with C
4. Compare nutation implementation line-by-line with C
5. Compare aberration calculation with C
6. Compare Observer::getObserver() with C `swi_get_observer()`
7. Verify light-time iteration convergence

## Files Modified
- `src/SwephFile/SwephPlanCalculator.php` - Fixed Moon return value
- `src/Swe/Moon/MoonTransform.php` - Added full app_pos_etc_moon port
- `src/SwephFile/SwedState.php` - Added TopoData structure
- `src/SwephFile/TopoData.php` - New class for observer data
- `src/Swe/Observer/Observer.php` - Topocentric observer calculation
- `src/Swe/Observer/ObserverCalculator.php` - Updated for TopoData
- `src/Swe/Functions/PlanetsFunctions.php` - Added MoonTransform call
