# Release process

## Release gate

Do not create a tag while required CI is red. Before the first stable `0.1.0` release, complete the unchecked correctness gates in [ROADMAP.md](ROADMAP.md), record the full parity result in [COMPATIBILITY.md](COMPATIBILITY.md), and add the released version/date to `CITATION.cff`. A development prerelease may be published earlier with its limitations stated explicitly.

## Create a release

1. Update `CHANGELOG.md`, compatibility evidence and version metadata.
2. Run `composer validate --strict` and `composer check` on a clean checkout.
3. Create an annotated, preferably signed tag: `git tag -s v0.1.0 -m "v0.1.0"`.
4. Push the tag: `git push origin v0.1.0`.
5. The release workflow reruns quality checks, builds `php-swisseph.zip` from the tagged Git tree with `git archive`, writes `SHA256SUMS` and creates the GitHub Release. Pre-release tags such as `v0.1.0-alpha.1` are marked as prereleases.
6. Download the archive, verify the checksum, install it in a clean sample project and run a smoke calculation.

## Packagist (one-time setup)

1. Sign in to Packagist with the maintainer account.
2. Submit `https://github.com/gutsergut/php-swisseph` and confirm the package name is `gutsergut/php-swisseph`.
3. Enable the GitHub/Packagist hook so tags update automatically.
4. Confirm Packagist shows AGPL-3.0-or-later, PHP requirements and the expected tag.

Do not store a Packagist token in the repository. GitHub Releases and Packagist publication are separate: a successful release workflow does not prove Packagist updated.
