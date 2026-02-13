# CONTRIBUTING

Thanks for contributing to `nicobleiler/php-passphrase`.

## Commit messages

Use the Conventional Commits specification for all commit messages:

- https://www.conventionalcommits.org/
- Examples: `feat: add unicode-safe capitalization option`, `fix(wordlist): validate custom word list parsing`

## Release process and versioning

This repository uses semantic-release for Semantic Versioning (SemVer).

Commit types and breaking-change markers are used to determine the next release version automatically.

- `fix:` typically triggers a patch release
- `feat:` typically triggers a minor release
- `BREAKING CHANGE:` footer (or `!`) triggers a major release

## Target branch

Open contributor pull requests against `dev`.

This repository promotes changes through `dev -> alpha -> beta -> master`, so contributors should not target `alpha`, `beta`, or `master` directly unless explicitly requested by a maintainer.

## Pipeline checks

Pull requests run the following checks:

- Validate composer.json
- Running the test suite
- Scan for security vulnerabilities with Trivy

## Development checks

Before opening a pull request, run:

- `composer install`
- `composer test`

Keep changes focused and update docs/config examples when behavior changes.
