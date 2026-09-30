# Public API contract

Updated: 2026-09-30

This is the intended pre-1.0 contract for the procedural `swe_*` functions and the `Swisseph\OO` facade. It describes behavior to preserve while numerical parity is completed.

## Inputs

- Dates and Julian days are explicit about UT/UTC/TT in the method name or documentation.
- Geographic longitude is degrees east; latitude is degrees north; altitude is metres unless a function documents otherwise.
- A flag mask is preserved as a bit mask. Unknown or contradictory flags return a structured failure instead of being silently discarded.
- Body identifiers follow the constants in `Swisseph\Constants`.
- File paths must point to a caller-controlled directory; the package does not download ephemeris data implicitly.

## Calculation results

- Procedural functions retain their documented C-like output arrays and by-reference error parameter.
- A negative return code represents failure.
- OO results expose `isSuccess()` / `isError()`, the raw returned flag mask, six raw values and an optional error string.
- Callers must not infer the backend only from the requested flags. Applications should record returned flags and their own backend/data provenance.
- Arrays are populated deterministically on success; failure behavior is covered by regression tests before it is considered stable.

## State

Functions such as `swe_set_ephe_path`, `swe_set_sid_mode` and `swe_set_topo` mutate process-global state for compatibility with the upstream API. Consumers must serialize conflicting contexts or isolate them in separate processes.

## Errors and fallback

- Missing or unreadable required data is reported; tests may skip only when the test itself declares the data optional.
- A requested SWIEPH or JPL calculation must not be reported as verified parity when the engine fell back to another backend.
- PHP warnings/notices and debug output are not part of the API.
- Invalid dates, coordinates, identifiers and flags must fail consistently and without leaving partial global state.

## Compatibility policy before 1.0

API presence and numerical compatibility are tracked separately in [COMPATIBILITY.md](COMPATIBILITY.md). Breaking changes may occur before 1.0 but require a changelog entry and migration note. Once 1.0 is reached, Semantic Versioning applies to the documented public surface.

## Out of contract

- Bit-for-bit identity with every compiler/platform.
- Redistribution rights for caller-supplied ephemeris or catalogue files.
- Thread safety around mutable global settings.
- Fitness for safety-critical navigation or other high-consequence use.
