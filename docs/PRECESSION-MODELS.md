# Precession Models in Swiss Ephemeris

## Overview

Swiss Ephemeris supports multiple precession/obliquity models for astronomical calculations. Different models have different accuracy and applicability ranges.

## Supported Models

| Model | Code | Year | Accuracy | Time Range | Notes |
|-------|------|------|----------|------------|-------|
| **Vondrak 2011** | `SEMOD_PREC_VONDRAK_2011` (9) | 2011 | Best | Long-term | **DEFAULT** since SE v1.70 |
| IAU 2006 | `SEMOD_PREC_IAU_2006` (8) | 2006 | Excellent | ±200 centuries | Modern standard |
| Bretagnon 2003 | `SEMOD_PREC_BRETAGNON_2003` (7) | 2003 | Excellent | Long-term | |
| IAU 2000 | `SEMOD_PREC_IAU_2000` (6) | 2000 | Excellent | ±200 centuries | |
| Simon 1994 | `SEMOD_PREC_SIMON_1994` (5) | 1994 | Very good | Long-term | |
| Williams 1994 | `SEMOD_PREC_WILLIAMS_1994` (4) | 1994 | Very good | Long-term | |
| Will-Eps-Lask | `SEMOD_PREC_WILL_EPS_LASK` (3) | 1994 | Very good | Long-term | Williams + Laskar |
| Laskar 1986 | `SEMOD_PREC_LASKAR_1986` (2) | 1986 | Good | Long-term | |
| **IAU 1976** | `SEMOD_PREC_IAU_1976` (1) | 1976 | Good | ±2 centuries | **Default before SE v1.70** |
| Newcomb | `SEMOD_PREC_NEWCOMB` (11) | 1895 | Historical | Limited | For compatibility |
| Owen 1990 | `SEMOD_PREC_OWEN_1990` (10) | 1990 | - | - | Alternative |

## Key Differences

### Obliquity at J2000.0 (JD 2451545.0)

```
IAU 1976:  84381.448" = 23.4392911° = 0.40909280422233 rad
IAU 2000:  84381.406" = 23.4392794° = 0.40909260060058 rad
IAU 2006:  84381.406" = 23.4392794° = 0.40909260060058 rad  (same as 2000)
Vondrak 2011: (polynomial, but ~84381.406" at J2000)
```

**Difference**: IAU 1976 vs 2006 = **0.042" = 0.04 arcseconds**

At Earth-Saturn distance (~8.6 AU = 1.3 billion km):
- Angular difference 0.04" corresponds to ~250 km positional difference
- Much smaller than other error sources

### Time-Dependent Changes

The models differ more significantly far from J2000:
- **IAU 1976**: Simple polynomial, less accurate >2000 years from J2000
- **IAU 2006**: More terms, accurate to ±20000 years
- **Vondrak 2011**: Most sophisticated, best for long-term calculations

## Current Implementation

**PHP Port (v0.1.x)**: Uses **IAU 1976** constants hardcoded
- Obliquity J2000: `0.40909280422232897` rad
- Matches C default behavior for short time ranges
- Sufficient for ±200 year accuracy

**C Swiss Ephemeris (v2.10)**:
- Default: **Vondrak 2011**
- Can be changed via `swe_set_ephe_path()` and model flags
- Short-term default: Vondrak 2011 (before v1.70: IAU 1976)

## Future Enhancements

### Phase 1: Configuration Support
Add settings to select precession model:
```php
// Example API (not yet implemented)
swe_set_prec_model(SEMOD_PREC_VONDRAK_2011);  // long-term
swe_set_prec_model_short(SEMOD_PREC_IAU_2006); // short-term
```

### Phase 2: Implement Additional Models
Port all precession models from C:
- Vondrak 2011 polynomial
- IAU 2006 formula
- Bretagnon 2003
- etc.

### Phase 3: Auto-Selection
Automatically choose best model based on date range:
- Near J2000 (±200 years): IAU 2006
- Long-term: Vondrak 2011
- Historical dates: Newcomb (for consistency with old calculations)

## References

- IAU 1976: Lieske et al., "Expressions for the Precession Quantities Based upon the IAU (1976) System of Astronomical Constants", A&A 58, 1-16 (1977)
- IAU 2000: Capitaine et al., "Expressions for IAU 2000 precession quantities", A&A 412, 567-586 (2003)
- IAU 2006: Capitaine et al., "Improvement of the IAU 2000 precession model", A&A 432, 355-367 (2005)
- Vondrak 2011: Vondrak et al., "New precession expressions, valid for long time intervals", A&A 534, A22 (2011)
- Swiss Ephemeris Documentation: https://www.astro.com/ftp/swisseph/doc/

## Impact on Calculations

For typical astrological/astronomical calculations:
- **Natal charts (one date)**: Difference <1" (negligible)
- **Transits (decades)**: Difference <10" (very small)
- **Historical events (centuries)**: IAU 2006/Vondrak recommended
- **Long-term cycles (millennia)**: Vondrak 2011 required

## Development Notes

When implementing new models:
1. Port polynomial coefficients exactly from C
2. Test against C swetest output for multiple dates
3. Document accuracy range and limitations
4. Add regression tests for edge cases

## See Also

- `src/Obliquity.php` - Mean obliquity calculations
- `с-swisseph/swisseph/swephlib.c` - C reference implementation (swi_epsiln function)
- `с-swisseph/swisseph/swephexp.h` - Model constants and definitions
