<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Tests;

use NicoBleiler\Passphrase\Exceptions\InvalidNumWordsException;
use NicoBleiler\Passphrase\PassphraseGenerator;
use NicoBleiler\Passphrase\WordList;
use PHPUnit\Framework\TestCase;

/**
 * Tests modeled after Bitwarden's passphrase generator tests.
 *
 * @see https://sdk-api-docs.bitwarden.com/src/bitwarden_generators/passphrase.rs.html
 */
class PassphraseGeneratorTest extends TestCase
{
    private PassphraseGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PassphraseGenerator();
    }

    // -------------------------------------------------------
    // Seeded RNG helper — provides deterministic sequences
    // for reproducible tests, similar to Bitwarden's
    // ChaCha8Rng::from_seed approach.
    // -------------------------------------------------------

    /**
     * Create a deterministic RNG function using a seed.
     *
     * Returns a callable fn(int $min, int $max): int that produces
     * a deterministic sequence of integers based on the seed.
     */
    private function seededRng(int $seed = 0): callable
    {
        // Use a simple LCG seeded PRNG for deterministic output
        $state = $seed;

        return function (int $min, int $max) use (&$state): int {
            // Linear congruential generator parameters (Numerical Recipes)
            $state = (1664525 * $state + 1013904223) & 0x7FFFFFFF;
            $range = $max - $min + 1;

            return $min + ($state % $range);
        };
    }

    // -------------------------------------------------------
    // Validation tests
    // -------------------------------------------------------

    public function test_minimum_num_words(): void
    {
        $this->expectException(InvalidNumWordsException::class);
        $this->expectExceptionMessage("'num_words' must be between 3 and 20");
        $this->generator->generate(numWords: 2);
    }

    public function test_maximum_num_words(): void
    {
        $this->expectException(InvalidNumWordsException::class);
        $this->expectExceptionMessage("'num_words' must be between 3 and 20");
        $this->generator->generate(numWords: 21);
    }

    public function test_zero_num_words(): void
    {
        $this->expectException(InvalidNumWordsException::class);
        $this->generator->generate(numWords: 0);
    }

    public function test_boundary_minimum_num_words_valid(): void
    {
        $result = $this->generator->generate(numWords: 3);
        $words = explode('-', $result);
        $this->assertCount(3, $words);
    }

    public function test_boundary_maximum_num_words_valid(): void
    {
        $result = $this->generator->generate(numWords: 20);
        $words = explode('-', $result);
        $this->assertCount(20, $words);
    }

    // -------------------------------------------------------
    // gen_words tests — analogous to Bitwarden's test_gen_words
    // -------------------------------------------------------

    public function test_gen_words_deterministic(): void
    {
        $rng = $this->seededRng(42);

        $result = $this->generator->generateWithRng(
            numWords: 4,
            wordSeparator: ' ',
            capitalize: false,
            includeNumber: false,
            rngInt: $rng,
        );

        $words = explode(' ', $result);
        $this->assertCount(4, $words);

        // Same seed must produce the same result
        $rng2 = $this->seededRng(42);

        $result2 = $this->generator->generateWithRng(
            numWords: 4,
            wordSeparator: ' ',
            capitalize: false,
            includeNumber: false,
            rngInt: $rng2,
        );

        $this->assertSame($result, $result2);
    }

    public function test_gen_words_different_counts(): void
    {
        $rng = $this->seededRng(0);

        // 4 words
        $result4 = $this->generator->generateWithRng(4, ' ', false, false, $rng);
        $this->assertCount(4, explode(' ', $result4));

        // 1 word would be below minimum, so test 3
        $rng = $this->seededRng(1);
        $result3 = $this->generator->generateWithRng(3, ' ', false, false, $rng);
        $this->assertCount(3, explode(' ', $result3));
    }

    // -------------------------------------------------------
    // capitalize tests — analogous to Bitwarden's test_capitalize
    // -------------------------------------------------------

    public function test_capitalize_first_letter(): void
    {
        $this->assertSame('Hello', PassphraseGenerator::capitalizeFirstLetter('hello'));
        $this->assertSame('1ello', PassphraseGenerator::capitalizeFirstLetter('1ello'));
        $this->assertSame('Hello', PassphraseGenerator::capitalizeFirstLetter('Hello'));
        $this->assertSame('H', PassphraseGenerator::capitalizeFirstLetter('h'));
        $this->assertSame('', PassphraseGenerator::capitalizeFirstLetter(''));
    }

    public function test_capitalize_first_letter_unicode(): void
    {
        // Bitwarden also tests non-ASCII: "áéíóú" -> "Áéíóú"
        $this->assertSame('Áéíóú', PassphraseGenerator::capitalizeFirstLetter('áéíóú'));
    }

    // -------------------------------------------------------
    // capitalize_words tests — analogous to test_capitalize_words
    // -------------------------------------------------------

    public function test_capitalize_words(): void
    {
        $wordList = WordList::fromArray(['hello', 'world']);
        $generator = new PassphraseGenerator($wordList);

        // Use a seeded RNG that picks words in order
        $idx = 0;
        $rng = function (int $min, int $max) use (&$idx, $wordList): int {
            // For word selection, just go in order
            if ($max === $wordList->count() - 1) {
                return $idx++ % $wordList->count();
            }

            return $min; // For any other random call, return minimum
        };

        $result = $generator->generateWithRng(
            numWords: 3, // request 3 to pass validation — but only 2 unique words
            wordSeparator: ' ',
            capitalize: true,
            includeNumber: false,
            rngInt: $rng,
        );

        // Each word should be capitalized
        $words = explode(' ', $result);
        foreach ($words as $word) {
            $this->assertTrue(
                ctype_upper($word[0]),
                "Expected '{$word}' to have a capitalized first letter"
            );
        }
    }

    // -------------------------------------------------------
    // include_number tests — analogous to test_include_number
    // -------------------------------------------------------

    public function test_include_number(): void
    {
        $wordList = WordList::fromArray(['hello', 'world']);
        $generator = new PassphraseGenerator($wordList);

        $calls = 0;
        $sequence = [
            0, // first word index -> 'hello'
            1, // second word index -> 'world'
            0, // third word index -> 'hello'
            1, // include_number: pick word index 1 ('world')
            7, // include_number: append digit 7
        ];
        $rng = function (int $min, int $max) use (&$calls, $sequence): int {
            return $sequence[$calls++];
        };

        $result = $generator->generateWithRng(
            numWords: 3,
            wordSeparator: ' ',
            capitalize: false,
            includeNumber: true,
            rngInt: $rng,
        );

        $this->assertSame('hello world7 hello', $result);
    }

    public function test_include_number_appends_digit_0_to_9(): void
    {
        // Generate many passphrases and ensure appended digit is always 0-9
        for ($i = 0; $i < 50; $i++) {
            $result = $this->generator->generate(
                numWords: 3,
                wordSeparator: ' ',
                capitalize: false,
                includeNumber: true,
            );

            $words = explode(' ', $result);
            $hasNumber = false;
            foreach ($words as $word) {
                if (preg_match('/\d$/', $word)) {
                    $hasNumber = true;
                    $digit = (int) substr($word, -1);
                    $this->assertGreaterThanOrEqual(0, $digit);
                    $this->assertLessThanOrEqual(9, $digit);
                }
            }
            $this->assertTrue($hasNumber, 'Expected at least one word to have an appended number');
        }
    }

    // -------------------------------------------------------
    // Separator tests — analogous to Bitwarden's test_separator
    // -------------------------------------------------------

    public function test_separator(): void
    {
        $rng = $this->seededRng(99);

        $result = $this->generator->generateWithRng(
            numWords: 4,
            wordSeparator: '👨🏻‍❤️‍💋‍👨🏻',
            capitalize: false,
            includeNumber: false,
            rngInt: $rng,
        );

        $parts = explode('👨🏻‍❤️‍💋‍👨🏻', $result);
        $this->assertCount(4, $parts);
    }

    public function test_various_separators(): void
    {
        $separators = ['-', ' ', '.', '_', ';', '|', '::'];

        foreach ($separators as $separator) {
            $result = $this->generator->generate(
                numWords: 3,
                wordSeparator: $separator,
            );

            $parts = explode($separator, $result);
            $this->assertCount(3, $parts, "Failed for separator: '{$separator}'");
        }
    }

    // -------------------------------------------------------
    // Full passphrase tests — analogous to test_passphrase
    // -------------------------------------------------------

    public function test_passphrase_with_all_options(): void
    {
        $rng = $this->seededRng(42);

        $result = $this->generator->generateWithRng(
            numWords: 4,
            wordSeparator: '-',
            capitalize: true,
            includeNumber: true,
            rngInt: $rng,
        );

        $parts = explode('-', $result);
        $this->assertCount(4, $parts);

        // Each word capitalized
        foreach ($parts as $part) {
            // Strip trailing digit if present
            $word = rtrim($part, '0123456789');
            if ($word !== '') {
                $this->assertTrue(
                    mb_strtoupper(mb_substr($word, 0, 1)) === mb_substr($word, 0, 1),
                    "Expected '{$part}' to be capitalized"
                );
            }
        }

        // Exactly one word should end with a digit
        $wordsWithDigit = array_filter($parts, fn ($p) => preg_match('/\d$/', $p));
        $this->assertCount(1, $wordsWithDigit);
    }

    public function test_passphrase_deterministic_same_seed(): void
    {
        for ($seed = 0; $seed < 5; $seed++) {
            $rng1 = $this->seededRng($seed);
            $rng2 = $this->seededRng($seed);

            $result1 = $this->generator->generateWithRng(4, '-', true, true, $rng1);
            $result2 = $this->generator->generateWithRng(4, '-', true, true, $rng2);

            $this->assertSame($result1, $result2, "Seed {$seed} should produce identical output");
        }
    }

    public function test_passphrase_no_capitalize_no_number(): void
    {
        $rng = $this->seededRng(10);

        $result = $this->generator->generateWithRng(
            numWords: 5,
            wordSeparator: ';',
            capitalize: false,
            includeNumber: false,
            rngInt: $rng,
        );

        $parts = explode(';', $result);
        $this->assertCount(5, $parts);

        // No digits anywhere
        $this->assertDoesNotMatchRegularExpression('/\d/', $result);

        // All lowercase
        $this->assertSame(mb_strtolower($result), $result);
    }

    public function test_passphrase_capitalize_no_number(): void
    {
        $rng = $this->seededRng(20);

        $result = $this->generator->generateWithRng(
            numWords: 3,
            wordSeparator: ' ',
            capitalize: true,
            includeNumber: false,
            rngInt: $rng,
        );

        $parts = explode(' ', $result);
        $this->assertCount(3, $parts);

        foreach ($parts as $word) {
            $first = mb_substr($word, 0, 1);
            $this->assertSame(mb_strtoupper($first), $first);
        }

        // No digits
        $this->assertDoesNotMatchRegularExpression('/\d/', $result);
    }

    // -------------------------------------------------------
    // Default values test
    // -------------------------------------------------------

    public function test_default_generation(): void
    {
        $result = $this->generator->generate();
        $words = explode('-', $result);

        // Default is 3 words with '-' separator
        $this->assertCount(3, $words);
    }

    // -------------------------------------------------------
    // EFF word list tests
    // -------------------------------------------------------

    public function test_eff_word_list_loads(): void
    {
        $wordList = WordList::eff();
        $this->assertSame(7776, $wordList->count());
    }

    public function test_all_generated_words_are_in_word_list(): void
    {
        $wordList = WordList::eff();
        $allWords = $wordList->all();

        for ($i = 0; $i < 20; $i++) {
            $result = $this->generator->generate(
                numWords: 5,
                wordSeparator: '|',
                capitalize: false,
                includeNumber: false,
            );

            $generatedWords = explode('|', $result);
            foreach ($generatedWords as $word) {
                $this->assertContains($word, $allWords, "Word '{$word}' not found in EFF word list");
            }
        }
    }
}
