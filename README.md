# PHP Passphrase Generator

[![Latest Version](https://img.shields.io/packagist/v/nicobleiler/php-passphrase.svg)](https://packagist.org/packages/nicobleiler/php-passphrase)
[![Downloads](https://img.shields.io/packagist/dt/nicobleiler/php-passphrase.svg)](https://packagist.org/packages/nicobleiler/php-passphrase)
[![PHP Version](https://img.shields.io/packagist/php-v/nicobleiler/php-passphrase.svg)](https://packagist.org/packages/nicobleiler/php-passphrase)
[![Code Size](https://img.shields.io/github/languages/code-size/nicobleiler/php-passphrase)](https://github.com/nicobleiler/php-passphrase)
[![Wordlist Size](https://img.shields.io/github/size/nicobleiler/php-passphrase/resources/wordlists/eff_large_wordlist.php?label=wordlist)](https://github.com/nicobleiler/php-passphrase/blob/master/resources/wordlists/eff_large_wordlist.php)[![CI](https://github.com/nicobleiler/php-passphrase/actions/workflows/test.yml/badge.svg)](https://github.com/nicobleiler/php-passphrase/actions/workflows/test.yml)
[![License](https://img.shields.io/packagist/l/nicobleiler/php-passphrase.svg)](LICENSE)

A Bitwarden-inspired passphrase generator for PHP with first-class Laravel integration.

Generates secure, memorable passphrases using the [EFF long word list](https://www.eff.org/dice) (7,776 words) by default, with full support for custom word lists.

## Installation

```bash
composer require nicobleiler/php-passphrase
```

Laravel will auto-discover the service provider. For other frameworks, see [Standalone Usage](#standalone-usage).

## Quick Start

### Laravel (Facade)

```php
use NicoBleiler\Passphrase\Facades\Passphrase;

// Default: 3 words, hyphen separator, no capitalize, no number
Passphrase::generate();
// "candle-rubber-glimpse"

// Customize everything
Passphrase::generate(
    numWords: 5,
    wordSeparator: '.',
    capitalize: true,
    includeNumber: true,
);
// "Candle.Rubber3.Glimpse.Obtain.Willow"
```

### Laravel (Dependency Injection)

```php
use NicoBleiler\Passphrase\PassphraseGenerator;

class AuthController
{
    public function __construct(
        private PassphraseGenerator $passphrase,
    ) {}

    public function temporaryPassword(): string
    {
        return $this->passphrase->generate(
            numWords: 4,
            capitalize: true,
            includeNumber: true,
        );
    }
}
```

### Standalone Usage

```php
use NicoBleiler\Passphrase\PassphraseGenerator;

$generator = new PassphraseGenerator();
echo $generator->generate(); // "candle-rubber-glimpse"
```

You can set instance-level defaults that apply whenever `generate()` is called without explicit parameters:

```php
$generator = new PassphraseGenerator();
$generator->setDefaults(
    numWords: 5,
    wordSeparator: '.',
    capitalize: true,
    includeNumber: true,
);

echo $generator->generate(); // "Candle.Rubber3.Glimpse.Obtain.Willow"

// Explicit params still override defaults
echo $generator->generate(numWords: 3, wordSeparator: '-');
```

### Custom Randomizer

By default, `PassphraseGenerator` uses PHP's cryptographically secure `Random\Engine\Secure`. You can inject a custom `Random\Randomizer` for deterministic output (e.g. testing, demos, reproducible benchmarks):

```php
use NicoBleiler\Passphrase\PassphraseGenerator;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

$generator = new PassphraseGenerator(
    randomizer: new Randomizer(new Xoshiro256StarStar(12345)),
);

// Same seed always produces the same passphrase
echo $generator->generate(); // deterministic output
```

> **Security note:** Only use non-secure engines for testing or demos. For real passphrase generation, always use the default `Secure` engine.

## Options

| Parameter | Type | Default | Description |
|---|---|---|---|
| `numWords` | `?int` | `3` | Number of words (3–20). `null` uses instance/config default. |
| `wordSeparator` | `?string` | `'-'` | Character(s) between words. `null` uses instance/config default. |
| `capitalize` | `?bool` | `false` | Capitalize the first letter of each word. `null` uses instance/config default. |
| `includeNumber` | `?bool` | `false` | Append a random digit (0–9) to one random word. `null` uses instance/config default. |

All parameters are nullable — passing `null` (or omitting them) uses the defaults set via `setDefaults()` or `config/passphrase.php` in Laravel.

These match [Bitwarden's passphrase generator options](https://bitwarden.com/passphrase-generator/) exactly.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=passphrase-config
```

This creates `config/passphrase.php`:

```php
return [
    'num_words'      => 3,
    'word_separator'  => '-',
    'capitalize'      => false,
    'include_number'  => false,

    // null = bundled EFF long word list (7,776 words)
    // Or provide your own word list as a PHP array of strings
    'word_list'       => null,

    // Optional words to remove from the active word list
    // Works with both bundled EFF and custom word_list values
    'excluded_words'      => [],
];
```

These config values are automatically used as defaults when calling `Passphrase::generate()` without explicit parameters. You can still override any option per-call.

## Custom Word Lists

### Via Config (Laravel)

Provide `word_list` as a PHP array of strings:

```php
// config/passphrase.php
'word_list' => ['correct', 'horse', 'battery', 'staple'],

// Optionally remove specific words from the active list
'excluded_words' => ['horse'],
```

Or load it from a dedicated PHP file:

```php
// config/passphrase.php
'word_list' => require base_path('config/custom_word_list.php'),
```

```php
// config/custom_word_list.php
<?php

return [
    'correct',
    'horse',
    'battery',
    'staple',
];
```

### Programmatically

```php
use NicoBleiler\Passphrase\WordList;
use NicoBleiler\Passphrase\PassphraseGenerator;

// From an array
$wordList = WordList::fromArray(['correct', 'horse', 'battery', 'staple']);

$generator = new PassphraseGenerator($wordList);
echo $generator->generate(numWords: 4);
```

You can also publish the bundled EFF word list to your resources folder:

```bash
php artisan vendor:publish --tag=passphrase-wordlists
```

This is optional and mainly useful if you want a local copy to inspect or customize. By default, the package reads the bundled EFF list directly.

## How It Works

The generation algorithm mirrors [Bitwarden's Rust implementation](https://sdk-api-docs.bitwarden.com/src/bitwarden_generators/passphrase.rs.html):

### Sequence diagram

```mermaid
sequenceDiagram
    participant Client
    participant ServiceProvider
    participant PassphraseGenerator
    participant Randomizer
    participant WordList

    Client->>ServiceProvider: Boot (Laravel)
    ServiceProvider->>WordList: Create (singleton)
    ServiceProvider->>PassphraseGenerator: Create with Randomizer(Secure)
    ServiceProvider->>PassphraseGenerator: setDefaults(config values)
    activate PassphraseGenerator
    PassphraseGenerator-->>ServiceProvider: self (fluent)
    deactivate PassphraseGenerator

    Client->>PassphraseGenerator: generate() [no params]
    activate PassphraseGenerator
    PassphraseGenerator->>PassphraseGenerator: Use instance defaults
    PassphraseGenerator->>Randomizer: getInt() [select words]
    activate Randomizer
    Randomizer-->>PassphraseGenerator: random indices
    deactivate Randomizer
    PassphraseGenerator->>WordList: wordAt(index)
    activate WordList
    WordList-->>PassphraseGenerator: word string
    deactivate WordList
    PassphraseGenerator->>Randomizer: getInt() [digit if needed]
    activate Randomizer
    Randomizer-->>PassphraseGenerator: random digit
    deactivate Randomizer
    PassphraseGenerator-->>Client: passphrase string
    deactivate PassphraseGenerator
```

## Testing

```bash
composer test
```

Or directly:

```bash
vendor/bin/phpunit
```

The test suite includes tests modeled after Bitwarden's own test cases:

- Validation (word count bounds)
- Deterministic generation with seeded `Xoshiro256StarStar` engine
- Capitalize behavior (including Unicode)
- Number inclusion
- Separator handling (including multi-byte emoji)
- EFF word list integrity
- Laravel integration (service provider, facade, config defaults)
  
## Performance (2026-02-16)

These benchmarks were run on a local Ryzen 9 5950X machine running Windows 11 with PHP 8.5.0.

In this run the available benchmark providers were: `php-passphrase`, `genphrase/genphrase`, `martbock/laravel-diceware`, `random_bytes`, `Illuminate\\Support\\Str::random`, and `Illuminate\\Support\\Str::password` (providers are included automatically when their packages are installed).

> **Note on cold runs:** `benchGenerateCold` includes setup and first-use initialization (autoloading, object construction, and initial word-list work). Cold-run `rstdev` is therefore expected to be higher and should be interpreted as startup-cost signal, not steady-state throughput.

```text
benchGenerateCold
+----------------+--------------------------------------------------------------------+------+-----+-----------+-----------+----------+----------+
| benchmark      | set                                                                | revs | its | mem_peak  | mode      | mean     | rstdev   |
+----------------+--------------------------------------------------------------------+------+-----+-----------+-----------+----------+----------+
| ProvidersBench | php-passphrase (EFF 5 words, ~64.6 bits)                           | 1    | 20  | 1.612mb   | 331.431μs | 504μs    | ±141.06% |
| ProvidersBench | genphrase/genphrase (65-bit target, diceware)                      | 1    | 20  | 1.366mb   | 1.662ms   | 3.788ms  | ±240.86% |
| ProvidersBench | martbock/laravel-diceware (EFF 5 words, ~64.6 bits)                | 1    | 20  | 958.76kb  | 3.68ms    | 4.745ms  | ±82.04%  |
| ProvidersBench | random_bytes(8) hex (~64 bits)                                     | 1    | 20  | 494.856kb | 9.818μs   | 11.6μs   | ±35.59%  |
| ProvidersBench | Illuminate\Support\Str::random(11) (~65.5 bits)                    | 1    | 20  | 494.872kb | 182.63μs  | 245.95μs | ±77.25%  |
| ProvidersBench | Illuminate\Support\Str::password(10) (default options, ~64.6 bits) | 1    | 20  | 1.143mb   | 921.507μs | 1.371ms  | ±132.20% |
+----------------+--------------------------------------------------------------------+------+-----+-----------+-----------+----------+----------+

benchGenerateWarm
+----------------+--------------------------------------------------------------------+------+-----+-----------+---------+----------+---------+
| benchmark      | set                                                                | revs | its | mem_peak  | mode    | mean     | rstdev  |
+----------------+--------------------------------------------------------------------+------+-----+-----------+---------+----------+---------+
| ProvidersBench | php-passphrase (EFF 5 words, ~64.6 bits)                           | 100  | 20  | 495.12kb  | 1.353μs | 1.406μs  | ±14.18% |
| ProvidersBench | genphrase/genphrase (65-bit target, diceware)                      | 100  | 20  | 1.364mb   | 6.715μs | 6.829μs  | ±3.74%  |
| ProvidersBench | martbock/laravel-diceware (EFF 5 words, ~64.6 bits)                | 100  | 20  | 510.016kb | 2.099ms | 2.073ms  | ±2.68%  |
| ProvidersBench | random_bytes(8) hex (~64 bits)                                     | 100  | 20  | 495.112kb | 0.125μs | 0.132μs  | ±24.62% |
| ProvidersBench | Illuminate\Support\Str::random(11) (~65.5 bits)                    | 100  | 20  | 495.128kb | 0.532μs | 0.563μs  | ±16.54% |
| ProvidersBench | Illuminate\Support\Str::password(10) (default options, ~64.6 bits) | 100  | 20  | 587.672kb | 11.86μs | 11.927μs | ±2.81%  |
+----------------+--------------------------------------------------------------------+------+-----+-----------+---------+----------+---------+
```

Relative comparisons (mean times):

- Cold run: `php-passphrase` (504 μs) is ~7.5× faster than `genphrase` (3.788 ms) and ~9.4× faster than `martbock/laravel-diceware` (4.745 ms).
- Warm run: `php-passphrase` (1.406 μs) is ~4.9× faster than `genphrase` (6.829 μs) and ~1,475× faster than `martbock/laravel-diceware` (2.073 ms).

These values are environment-dependent; run `composer bench` locally if you need numbers for a different machine or PHP version.
## Benchmarking

Run the benchmark suite with:

```bash
composer bench
```

The benchmark suite uses [PHPBench](https://github.com/phpbench/phpbench) and compares providers with near-matched entropy targets.

Each provider is measured in two scenarios:

- `benchGenerateCold` — includes provider setup + first generation (cold start)
- `benchGenerateWarm` — measures steady-state generation after setup warmup

Default stability settings (configured in `phpbench.json`):

- iterations: `20`
- revolutions: `1` (cold subject), `100` (warm subject)
- warmup: `2` (warm subject only)

You can override scale from CLI when needed, for example:

```bash
vendor/bin/phpbench run --report=providers --iterations=40 --revs=5 --retry-threshold=5
```

Compared providers:

- `php-passphrase` with EFF 5 words (~64.6 bits)
- `random_bytes(8)` (~64 bits)
- `Illuminate\\Support\\Str::random(11)` (~65.5 bits)
- `Illuminate\\Support\\Str::password(10)` with default options (~64.6 bits)

Optional providers can be added `composer require --dev genphrase/genphrase martbock/laravel-diceware` and will be included in the benchmark suite if present:

- `genphrase/genphrase` with a 65-bit target on diceware mode
- `martbock/laravel-diceware` with EFF 5 words (~64.6 bits)

The `eurosat7/random` package cannot currently be required directly via Composer VCS because its upstream `composer.json` has no valid package `name`.

Use a local package-repository override in your `composer.json` instead:

```json
{
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "eurosat7/random",
                "version": "dev-main",
                "source": {
                    "type": "git",
                    "url": "https://github.com/eurosat7/random.git",
                    "reference": "main"
                },
                "autoload": {
                    "psr-4": {
                        "Eurosat7\\Random\\": "src/"
                    }
                },
                "require": {
                    "php": ">=8.2"
                }
            }
        }
    ]
}
```

Then run:

```bash
composer require --dev eurosat7/random:dev-main
```

When installed, the benchmark also includes:

- `eurosat7/random` via `Eurosat7\Random\Generator::password(10)` (~64+ bits)

Baseline and comparison runs:

```bash
composer bench:baseline
composer bench:compare
```

## Requirements

- PHP 8.2+
- `ext-mbstring` (for multibyte/Unicode capitalization support)
- Laravel 11+ *(optional, for Laravel integration)*

## License

MIT — see [LICENSE](LICENSE).

## Credits

- Passphrase generation logic inspired by [Bitwarden](https://github.com/bitwarden)
- Word list from the [Electronic Frontier Foundation](https://www.eff.org/dice), licensed under [CC-BY 4.0](https://creativecommons.org/licenses/by/4.0/)
