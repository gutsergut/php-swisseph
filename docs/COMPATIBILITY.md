# Compatibility status

Last verified locally: 2026-10-01 on PHP 8.5.

This document distinguishes API presence from numerical compatibility. An exported `swe_*` symbol is not considered compatible until its outputs, flags, error behavior and backend selection are verified against the same Swiss Ephemeris C version and data set.

## Confidence levels

| Level | Meaning |
|---|---|
| CI | Covered by the self-contained suite and required on every supported PHP version. |
| Diagnostic | Exercised by the full suite, with external-data requirements or incomplete independent parity coverage. |
| Unverified | Present in source but without a trustworthy parity result. |

## Current matrix

| Area | Level | Notes |
|---|---|---|
| Calendar and Julian day conversions | CI | Deterministic unit coverage. |
| Coordinate transforms and refraction | CI | Deterministic unit coverage. |
| Delta T, obliquity and sidereal helpers | CI | Approximation/model choice still needs a versioned parity corpus. |
| House systems and angles | CI | Multiple systems covered; full geographic/date boundary corpus is pending. |
| Procedural result shapes | CI | Basic shape, validation and wrapper behavior covered. |
| Moshier/VSOP planetary calculations | Diagnostic | Current regression vectors pass; independent native-C differential coverage is incomplete. |
| Rise/set/transit and phenomena | Diagnostic | Current regression vectors pass; boundary and model-version coverage is incomplete. |
| Asteroids and fictitious bodies | Diagnostic | External data required; independent parity corpus is incomplete. |
| Fixed stars and planetary moons | Diagnostic | External catalogue/data required; independent parity corpus is incomplete. |
| SWIEPH `.se1` backend | Diagnostic | External files required; returned backend flags must be asserted. |
| JPL backend | Diagnostic | Parser scaffolding is tested; end-to-end DE-file parity is not release-qualified. |
| Heliacal and eclipse families | Unverified | API presence does not yet establish full C behavior. |

## Test tiers

`composer test` runs the self-contained regression suite. It is intentionally small enough to run on every PHP version without proprietary or large ephemeris files.

The 2026-10-01 local run completed **66 tests and 472 assertions with no failures**. Strict Composer PSR-4/ambiguous-class checks also passed. GitHub's hosted matrix has not yet run successfully because the account is billing-locked; local results do not establish PHP 8.1–8.4 compatibility.

`composer test:full` runs every PHPUnit test and is a diagnostic quality gate. Set `SWEPH_EPHE_DIR` to licensed `.se1`/catalogue fixtures. The 2026-10-01 local run completed **211 tests and 1565 assertions with no failures** on PHP 8.5. This proves reproducibility of the repository's current expectations, not independent C parity.

The fixture files used for that run were not committed. Their SHA-256 values were:

| File | Bytes | SHA-256 |
|---|---:|---|
| `sepl_18.se1` | 484055 | `0b7e416e3c1be9e6a0dd1d711dae7f7685793a0e7df13f76363a493dc27b6ea1` |
| `semo_18.se1` | 1304771 | `ecfa54dbf5bc0b5a9bc3e04ed28629a821e98625eacae38f4070593bba0e2980` |
| `seas_18.se1` | 223002 | `5fd9c2aa1654e37c09a6aeb558076e795409b7dc4bd948ebc0faa7d4a7686b5b` |
| `sefstars.txt` | 136071 | `6093038469a4e7d928c4d912911ce2243284e9c1595485a625f3190e6c11c19c` |
| `seorbel.txt` | 6063 | `97b454ff78f4f4716b5cc987a93ca8f33e44ef4b524a165a155a8a4885fd2e18` |
| `de440.eph` | 102272352 | `29915576d0a6555766b99485ac3056ee415e86df4fce282611c31afb329ad062` |

## Required parity record

A parity fixture is accepted only when it includes:

- Swiss Ephemeris source/tag and reference executable hash;
- ephemeris file names and SHA-256 hashes;
- UTC input, time scale conversion and calendar;
- planet/body identifier, complete flag mask and requested backend;
- raw six-value result, returned flag mask and error string;
- tolerance and rationale for every compared field.

## Static analysis debt

PHPStan checks `src/` at its maximum level against the checked-in baseline of 4499 pre-existing findings. Passing this gate means no new findings beyond that baseline; it does not mean the legacy source is free of type issues. Reduce baseline entries together with verified source fixes.
