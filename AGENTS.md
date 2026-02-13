# AGENTS.md

Guidance for AI/coding agents working in this repository.

## Project Summary

- **Type:** PHP library
- **Goal:** Generate secure, memorable passphrases (Bitwarden-inspired)
- **Primary namespace:** `NicoBleiler\Passphrase\`
- **PHP version:** 8.2+
- **Optional integration:** Laravel 11+

## Repository Layout

- `src/` — Core library code
  - `PassphraseGenerator.php` — main generation logic
  - `WordList.php` — word list parsing/loading
  - `PassphraseServiceProvider.php` — Laravel service wiring
  - `Facades/Passphrase.php` — Laravel facade
  - `Exceptions/` — package-specific exception types
- `config/passphrase.php` — publishable Laravel config defaults
- `resources/wordlists/eff_large_wordlist.txt` — bundled default word list
- `tests/` — PHPUnit tests (unit + Laravel integration via Testbench)

## Setup & Validation

1. Install dependencies:
   - `composer install`
2. Run tests:
   - `composer test`
   - or `vendor/bin/phpunit`

Agents should run tests after meaningful changes, especially for behavior updates.

## Coding Guidelines

- Preserve **PSR-4** structure and the `NicoBleiler\Passphrase\` namespace.
- Keep public APIs stable unless explicitly asked to change them.
- Prefer small, focused changes over broad refactors.
- Avoid introducing framework-specific coupling in core classes unless change is Laravel integration related.
- Keep error handling explicit with package exception types where appropriate.
- Maintain compatibility with PHP 8.2.

## Testing Guidelines

- Add/adjust tests in `tests/` for any behavioral change.
- Keep deterministic tests deterministic (seeded randomness patterns where already used).
- Validate edge cases already reflected by the suite:
  - word-count bounds
  - separator behavior (including multibyte)
  - capitalization behavior (including Unicode)
  - optional number insertion behavior
  - EFF/custom word list parsing behavior

## Word List & Security Notes

- Default behavior relies on the bundled EFF long list.
- Support both plain one-word-per-line lists and EFF-style `dice\tword` format.
- Do not add logging/output that could leak generated passphrases.

## Laravel Integration Notes

- Keep service provider bindings/facade alias behavior intact.
- If config keys change, update tests and docs consistently.

## Documentation Expectations

When changing behavior or public options:

- Update `README.md` and `AGENTS.md` examples/options.
- Ensure configuration docs stay aligned with `config/passphrase.php`.

## Agent Workflow Expectation

- Understand current behavior before editing.
- Implement the minimum safe change.
- Run tests.
- Summarize what changed and why.
