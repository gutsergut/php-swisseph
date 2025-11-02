# Fix: swe_cotrans Polar Coordinate Transformation

## Date: 2025-01-XX

## Problem
The `swe_cotrans()` function had a critical bug causing ~17° errors in round-trip coordinate transformations (HOR→EQU→HOR). The `AzaltQuickTest.php` showed perfect azimuth (0° error) but broken altitude (~17° error).

## Root Causes

### 1. Wrong Parameter Order (FIXED)
**C API signature:**
```c
void swe_cotrans(double *xpo, double *xpn, double eps);
```
Order: `(input, output, angle)`

**PHP wrapper had:**
```php
function swe_cotrans(array $xpo, float $eps, array &$xpn): int
```
Order: `(input, angle, output)` ❌ WRONG

**Fixed to:**
```php
function swe_cotrans(array $xpo, array &$xpn, float $eps): int
```
Order: `(input, output, angle)` ✅ CORRECT

### 2. Wrong Coordinate System (FIXED - Main Issue!)
**PHP implementation worked with cartesian coordinates:**
```php
// OLD (WRONG):
$x = (float)$xpo[0];
$y = (float)$xpo[1];
$z = (float)$xpo[2];
$yp = $y * $ca - $z * $sa;
$zp = $y * $sa + $z * $ca;
$xpn = [$x, $yp, $zp];
```

**C implementation works with polar coordinates:**
```c
// swephlib.c:223-247
x[0] *= DEGTORAD;        // Convert lon to radians
x[1] *= DEGTORAD;        // Convert lat to radians
x[2] = 1;                // Set radius = 1
swi_polcart(x, x);       // Polar → Cartesian
swi_coortrf(x, x, e);    // Rotate around X-axis
swi_cartpol(x, x);       // Cartesian → Polar
xpn[0] = x[0] * RADTODEG;
xpn[1] = x[1] * RADTODEG;
xpn[2] = xpo[2];         // Copy input radius
```

**Fixed PHP to match:**
```php
// NEW (CORRECT):
$x = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
$x[0] = Math::degToRad($xpo[0]);
$x[1] = Math::degToRad($xpo[1]);
$x[2] = 1.0;

Coordinates::polCart($x, $x);    // Polar → Cartesian
Coordinates::coortrf($x, $x, $eps_rad);  // Rotate X-axis
Coordinates::cartPol($x, $x);    // Cartesian → Polar

$xpn = [
    Math::radToDeg($x[0]),
    Math::radToDeg($x[1]),
    $xpo[2] ?? 1.0
];
```

## Changes Made

### 1. `src/Coordinates.php`
- **Added** `Coordinates::coortrf(array $xpo, array &$xpn, float $eps)`
  - Port of `swi_coortrf()` from swephlib.c:279-292
  - Rotates cartesian coordinates around X-axis
  - Delegates to existing `coortrf2()` after computing sin/cos

### 2. `src/Swe/Functions/TransformFunctions.php`
- **Rewrote** `TransformFunctions::cotrans()` to work with polar coordinates
- Changed signature: `(input, angle, output)` → `(input, output, angle)`
- Now matches C algorithm exactly:
  1. Convert angles deg→rad, set radius=1
  2. polar→cartesian (Coordinates::polCart)
  3. Rotate around X-axis (Coordinates::coortrf)
  4. cartesian→polar (Coordinates::cartPol)
  5. Convert angles rad→deg, copy input radius
- **Updated** `TransformFunctions::cotransSp()` to match new signature
- **Added** `use Swisseph\Coordinates;`

### 3. `src/functions.php`
- **Fixed** `swe_cotrans()` wrapper signature
  - Was: `(array $xpo, float $eps, array &$xpn)`
  - Now: `(array $xpo, array &$xpn, float $eps)`
- **Fixed** `swe_cotrans_sp()` wrapper signature
- **Updated** wrapper calls to TransformFunctions with correct parameter order

### 4. `src/Swe/Functions/HorizonFunctions.php`
- **Updated** all 4 calls to `\swe_cotrans()` to match new signature
  - Line 61: `\swe_cotrans($xra, $xra, -$eps_true);`
  - Line 74: `\swe_cotrans($x, $x, 90.0 - $geopos[1]);`
  - Line 129: `\swe_cotrans($xaz, $xaz, $dang);`
  - Line 141: `\swe_cotrans($xaz, $x, $eps_true);`
- All use correct in-place transformations where applicable

## Test Results

### Before Fix
```
--- Test 2: Round-trip (HOR -> EQU -> HOR) ---
Input Hor: Az=180.0000°, Alt=45.0000°
-> Equatorial: RA=113.4606°, Dec=36.0761°
-> Back to Hor: Az=180.0000°, Alt=27.8127° (true)
Round-trip error: Az=0.000000°, Alt=-17.187271°  ❌
```

### After Fix
```
--- Test 2: Round-trip (HOR -> EQU -> HOR) ---
Input Hor: Az=180.0000°, Alt=45.0000°
-> Equatorial: RA=113.4606°, Dec=83.0000°
-> Back to Hor: Az=180.0000°, Alt=45.0000° (true)
Round-trip error: Az=0.000000°, Alt=0.000000°  ✅
```

### Additional Tests
- ✅ `AzaltQuickTest.php` - all round-trip tests pass
- ✅ `CotransTest.php` - 8/8 comprehensive tests pass
- ✅ `CoordinatesRoundtripTest.php` - still passes
- ✅ `HousesExTest.php` - still passes (uses coordinate transforms)

## Impact
- **Breaking change:** `swe_cotrans()` parameter order changed
- **Affects:** Only `HorizonFunctions` internally (already updated)
- **Benefits:**
  - Round-trip coordinate errors reduced from ~17° to 0°
  - Perfect compliance with C API
  - All horizon coordinate transformations now accurate

## Commit
```
commit 8e56c4f
fix: Correct swe_cotrans to work with polar coordinates
```

## Related Issues
- Fixes altitude transformation errors in azalt/azalt_rev
- Resolves round-trip coordinate transformation failures
- Ensures CONTRACT.md compliance for swe_cotrans API

## References
- C source: `swephlib.c:223-292` (swe_cotrans, swi_coortrf)
- C source: `swecl.c:2788-2878` (swe_azalt, swe_azalt_rev)
- Test: `tests/AzaltQuickTest.php`
- Test: `tests/CotransTest.php`
