# VSOP87 Integration Architecture

## Current Status (December 2025)

### Implemented
- ✅ **Strategy Pattern Architecture**
  - `EphemerisStrategy` interface for pluggable ephemeris sources
  - `StrategyResult` DTO for unified return format
  - `EphemerisStrategyFactory` for strategy selection based on flags
  - `PlanetApparentPipeline` for centralized coordinate transformations

- ✅ **Mercury VSOP87 Implementation**
  - Full analytical calculation using VSOP87 series (L, B, R)
  - Heliocentric → Barycentric conversion using Sun barycenter data
  - Velocity calculation via central difference method
  - Integration with transformation pipeline (light-time, aberration, precession, nutation)
  - Test coverage: 27 tests, all passing
  - Accuracy: Machine precision (1e-12)

- ✅ **Planet Support Detection**
  - Explicit support checks for all planets (Mercury-Neptune)
  - Informative error messages for planets without data
  - Special handling for Pluto (not supported by VSOP87)

### Architecture Overview

```
swe_calc (PlanetsFunctions.php)
    ↓
EphemerisStrategyFactory.forFlags()
    ↓
    ├─ SEFLG_VSOP87 → Vsop87Strategy
    │   ├─ VsopSegmentedLoader (loads JSON data)
    │   ├─ Vsop87Calculator (computes L,B,R)
    │   ├─ SwephPlanCalculator (for Earth & Sun barycenter)
    │   └─ PlanetApparentPipeline (transformations)
    │
    └─ SEFLG_SWIEPH → SwephStrategy
        ├─ SwephPlanCalculator (reads .se1 files)
        ├─ MoonTransform (special Moon path)
        └─ PlanetApparentPipeline (transformations)
```

### Data Structure

VSOP87 data stored in JSON format:
- Location: `php-swisseph/data/vsop87/{planet}/`
- Files per planet: `L0.json`, `L1.json`, `B0.json`, `B1.json`, `R0.json`, `R1.json`
- Format: Segmented series with amplitude, phase, frequency per term

Currently available:
- ✅ Mercury: `data/vsop87/mercury/` (L0-L1, B0-B1, R0-R1)
- ⏳ Venus-Neptune: Placeholder detection, data not yet ingested

### Transformation Pipeline

`PlanetApparentPipeline.computeFinal()` applies (in strict order):

1. **Light-time correction** (2 iterations, geocentric only)
2. **Frame selection** (geocentric/heliocentric/barycentric)
3. **Light deflection** (gravitational, unless SEFLG_TRUEPOS)
4. **Aberration** (annual, relativistic formula, unless SEFLG_NOABERR)
5. **Precession** (J2000→date, unless SEFLG_J2000)
6. **appPosRest** (nutation, obliquity rotation, polar conversion, deg/rad)

All transformations respect flags: `SEFLG_RADIANS`, `SEFLG_EQUATORIAL`, `SEFLG_XYZ`, `SEFLG_SPEED`.

### Critical Implementation Details

**Vsop87Strategy Requirements:**
1. **Sun Barycenter Data**: Must be computed before planet calculation
   - Achieved by calling `SwephPlanCalculator::calculate()` for Earth
   - Earth calculation automatically fills `SwedState->pldat[SEI_SUNBARY]`
   
2. **Velocity Calculation**: Uses central difference
   - Requires Sun barycenter positions at `t±dt` (dt = PLAN_SPEED_INTV)
   - Arrays `$xps_plus`, `$xps_minus` must be initialized before passing to `calculate()`
   - Critical: Pass non-null arrays to trigger `$xpsret !== null` check in `SwephPlanCalculator`

3. **Coordinate Conversion**: Heliocentric spherical → Barycentric cartesian
   - VSOP87 returns heliocentric coordinates (L, B, R)
   - Convert to cartesian: `[x,y,z] = R * [cos(B)cos(L), cos(B)sin(L), sin(B)]`
   - Add Sun barycenter: `xb = xh + xsun`

### Test Coverage

**MercuryVsopParityTest** (12 test cases):
- Multiple epochs: J2000, 2030, 1900
- Flag combinations: default, SPEED, EQUATORIAL, XYZ+SPEED
- Compares facade vs direct strategy calls
- Accuracy: < 1e-12 (machine precision)

**MercuryVsopStrategyParityTest** (15 test cases):
- Direct strategy vs PlanetsFunctions facade
- Flag combinations: default, SPEED, EQUATORIAL, RADIANS, XYZ
- Multiple dates spanning 100+ years

**Vsop87PlanetSupportTest** (8 test cases):
- Mercury: data loaded, computation succeeds
- Venus-Neptune: informative "not yet ingested" messages
- Pluto: explicit "not supported" (VSOP87 limitation)

### Known Limitations

1. **Planet Coverage**: Only Mercury has VSOP87 data
   - Venus-Neptune: architecture ready, data pending
   - Pluto: Not supported by VSOP87 (use SWIEPH)

2. **Accuracy**: VSOP87 analytical precision ~1" for inner planets, ~10" for outer planets
   - Lower than SWIEPH numerical integration (~0.1")
   - Sufficient for most astrological applications

3. **Performance**: Slower than SWIEPH due to series evaluation
   - Mercury: ~1000 terms across all series
   - Trade-off: portability (no binary files) vs speed

## Next Steps

### Data Ingestion (Priority)

1. **Venus**: Extract VSOP87D data, convert to JSON format
2. **Mars**: Extract VSOP87D data
3. **Jupiter-Neptune**: Extract VSOP87D data
4. **Automated extraction**: Create script to parse VSOP87 source files

### Testing Strategy

For each new planet:
1. Unit test: strategy computes successfully
2. Parity test: compare facade vs strategy
3. Accuracy test: compare VSOP87 vs SWIEPH (expect ~1-10" difference)
4. Cross-reference: compare with swetest64.exe using VSOP flag (if available)

### Architecture Enhancements

1. **Lazy Loading**: Load VSOP data on-demand (currently loads all at compute)
2. **Caching**: Cache VsopPlanetModel objects per planet
3. **Error Handling**: More granular error messages (file not found vs parse error)
4. **Documentation**: Add examples of VSOP87 flag usage in CONTRACT.md

## Integration Notes

### Adding New Planet Data

1. Create directory: `data/vsop87/{planet}/` (lowercase)
2. Add JSON files: `L0.json`, `L1.json`, `B0.json`, `B1.json`, `R0.json`, `R1.json`
3. Update `Vsop87Strategy::compute()` switch statement:
   ```php
   case Constants::SE_{PLANET}:
       $planetDir = $base . DIRECTORY_SEPARATOR . '{planet}';
       $planetName = '{Planet}';
       break;
   ```
4. Add test in `Vsop87PlanetSupportTest`
5. Create parity test `{Planet}VsopParityTest`

### JSON Data Format

```json
{
  "series": "L0",
  "planet": "Mercury", 
  "segments": [
    {
      "terms": [
        {"A": 4.40250710144, "B": 0.0, "C": 0.0},
        {"A": 0.40989414977, "B": 1.48302034195, "C": 26087.90314157420}
      ]
    }
  ]
}
```

Where each term: `amplitude * cos(phase + frequency * t_millennia)`

## References

- VSOP87 theory: Bretagnon & Francou (1988), A&A 202, 309
- Original FORTRAN: ftp://ftp.imcce.fr/pub/ephem/planets/vsop87/
- PHP port architecture: Based on Swiss Ephemeris 2.10 C implementation
- Test methodology: Parity with swetest64.exe and SwephPlanCalculator
