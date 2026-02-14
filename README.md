# PHP Passphrase Generator

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

## Options

| Parameter | Type | Default | Description |
|---|---|---|---|
| `numWords` | `int` | `3` | Number of words (3–20) |
| `wordSeparator` | `string` | `'-'` | Character(s) between words |
| `capitalize` | `bool` | `false` | Capitalize the first letter of each word |
| `includeNumber` | `bool` | `false` | Append a random digit (0–9) to one random word |

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
    // Or set an absolute path to your own word list file
    'word_list_path'  => null,
];
```

## Custom Word Lists

### Via Config (Laravel)

Point `word_list_path` to any text file — one word per line, or EFF format (`12345\tword`):

```php
// config/passphrase.php
'word_list_path' => resource_path('wordlists/my-custom-list.txt'),
```

### Programmatically

```php
use NicoBleiler\Passphrase\WordList;
use NicoBleiler\Passphrase\PassphraseGenerator;

// From a file (plain or EFF format)
$wordList = WordList::fromFile('/path/to/wordlist.txt');

// From an array
$wordList = WordList::fromArray(['correct', 'horse', 'battery', 'staple']);

$generator = new PassphraseGenerator($wordList);
echo $generator->generate(numWords: 4);
```

You can also publish the bundled EFF word list to your resources folder:

```bash
php artisan vendor:publish --tag=passphrase-wordlists
```

## How It Works

The generation algorithm mirrors [Bitwarden's Rust implementation](https://sdk-api-docs.bitwarden.com/src/bitwarden_generators/passphrase.rs.html):

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
- Deterministic generation with seeded RNG
- Capitalize behavior (including Unicode)
- Number inclusion
- Separator handling (including multi-byte emoji)
- EFF word list integrity
- Laravel integration (service provider, facade, config)

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
- `genphrase/genphrase` with a 65-bit target on diceware mode
- `martbock/laravel-diceware` with EFF 5 words (~64.6 bits)
- `random_bytes(8)` (~64 bits)
- `Illuminate\\Support\\Str::random(11)` (~65.5 bits)

Baseline and comparison runs:

```bash
composer bench:baseline
composer bench:compare
```

## Requirements

- PHP 8.2+
- Laravel 11+ *(optional, for Laravel integration)*

## License

MIT — see [LICENSE](LICENSE).

## Credits

- Passphrase generation logic inspired by [Bitwarden](https://github.com/bitwarden)
- Word list from the [Electronic Frontier Foundation](https://www.eff.org/dice), licensed under [CC-BY 4.0](https://creativecommons.org/licenses/by/4.0/)
