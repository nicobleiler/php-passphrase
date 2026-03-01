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
  - `WordList.php` — word list loading/validation
  - `PassphraseServiceProvider.php` — Laravel service wiring
  - `Facades/Passphrase.php` — Laravel facade
  - `Exceptions/` — package-specific exception types
- `config/passphrase.php` — publishable Laravel config defaults
- `resources/wordlists/eff_large_wordlist.php` — bundled default word list (compiled PHP array)
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
- `PassphraseGenerator` uses `Random\Randomizer` (default: `Random\Engine\Secure`) for all randomness. Do not use `random_int()` directly.
- The `Randomizer` is injected via constructor for testability. Do not add separate RNG-callable methods.

## Testing Guidelines

- Add/adjust tests in `tests/` for any behavioral change.
- Deterministic tests inject a seeded `Random\Randomizer` (using `Xoshiro256StarStar` engine) via the constructor. Do not use custom callable RNG patterns.
- Validate edge cases already reflected by the suite:
  - word-count bounds
  - separator behavior (including multibyte)
  - capitalization behavior (including Unicode)
  - optional number insertion behavior
  - EFF/custom word list array behavior

## Word List & Security Notes

- Default behavior relies on the bundled EFF long list.
- Custom word lists are provided as PHP arrays (config `word_list` or `WordList::fromArray()`).
- Optional exclusion of words is provided via config `excluded_words` and `$wordlist->excludeWords()`.
- Do not add logging/output that could leak generated passphrases.

## Laravel Integration Notes

- Keep service provider bindings/facade alias behavior intact.
- The service provider wires `config/passphrase.php` values into `PassphraseGenerator::setDefaults()`.
- `Passphrase::generate()` without arguments uses config defaults; explicit params override them.
- If config keys change, update tests and docs consistently.

## Documentation Expectations

When changing behavior or public options:

- Update `README.md` and `AGENTS.md` examples/options.
- Ensure configuration docs stay aligned with `config/passphrase.php`.

## Commit Message Expectations

- Use the Conventional Commits format from https://www.conventionalcommits.org/.
- Prefer clear, scoped commit types (for example: `feat:`, `fix:`, `docs:`, `chore:`).
- Use `!` or a `BREAKING CHANGE:` footer when introducing breaking changes.

## Agent Workflow Expectation

- Understand current behavior before editing.
- Implement the minimum safe change.
- Run tests.
- Summarize what changed and why.
