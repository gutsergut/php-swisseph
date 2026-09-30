# Changelog

All notable changes are documented here. The project follows Semantic Versioning; before 1.0, breaking changes increment the minor version.

## [Unreleased]

### Added

- PHP 8.1–8.5 CI matrix, strict static analysis baseline and deterministic self-contained test tier.
- Tag-driven GitHub release workflow with Composer archive and SHA-256 checksums.
- Compatibility, contribution, security and release documentation.
- Composer package metadata for `gutsergut/php-swisseph`.

### Changed

- Replaced unverified complete-compatibility claims with an evidence-based compatibility matrix.
- Split the default regression suite from the full diagnostic parity suite.
- Normalized the AGPL licence file and moved attribution to `NOTICE`.

### Removed

- Committed Composer dependencies, local IDE state, stale lock/cache files and the unused Windows DLL.

[Unreleased]: https://github.com/gutsergut/php-swisseph/commits/main
