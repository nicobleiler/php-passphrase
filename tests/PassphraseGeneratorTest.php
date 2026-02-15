<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Tests;

use NicoBleiler\Passphrase\Exceptions\InvalidNumWordsException;
use NicoBleiler\Passphrase\PassphraseGenerator;
use NicoBleiler\Passphrase\WordList;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

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
        $this->generator = new PassphraseGenerator(
            randomizer: new Randomizer(new Mt19937(0)),
        );
    }

    // -------------------------------------------------------
    // Validation tests
    // -------------------------------------------------------

    public function test_minimum_num_words(): void
    {
        $this->expectException(InvalidNumWordsException::class);
        $this->expectExceptionMessage(sprintf(
            "'num_words' must be between %d and %d",
            PassphraseGenerator::MINIMUM_NUM_WORDS,
            PassphraseGenerator::MAXIMUM_NUM_WORDS,
        ));
        $this->generator->generate(numWords: PassphraseGenerator::MINIMUM_NUM_WORDS - 1);
    }

    public function test_maximum_num_words(): void
    {
        $this->expectException(InvalidNumWordsException::class);
        $this->expectExceptionMessage(sprintf(
            "'num_words' must be between %d and %d",
            PassphraseGenerator::MINIMUM_NUM_WORDS,
            PassphraseGenerator::MAXIMUM_NUM_WORDS,
        ));
        $this->generator->generate(numWords: PassphraseGenerator::MAXIMUM_NUM_WORDS + 1);
    }

    public function test_zero_num_words(): void
    {
        $this->expectException(InvalidNumWordsException::class);
        $this->generator->generate(numWords: 0);
    }

    public function test_boundary_minimum_num_words_valid(): void
    {
        $result = $this->generator->generate(numWords: PassphraseGenerator::MINIMUM_NUM_WORDS);
        $words = explode('-', $result);
        $this->assertCount(PassphraseGenerator::MINIMUM_NUM_WORDS, $words);
    }

    public function test_boundary_maximum_num_words_valid(): void
    {
        $result = $this->generator->generate(numWords: PassphraseGenerator::MAXIMUM_NUM_WORDS);
        $words = explode('-', $result);
        $this->assertCount(PassphraseGenerator::MAXIMUM_NUM_WORDS, $words);
    }

    // -------------------------------------------------------
    // gen_words tests — analogous to Bitwarden's test_gen_words
    // -------------------------------------------------------

    public function test_gen_words_deterministic(): void
    {
        $gen1 = new PassphraseGenerator(randomizer: new Randomizer(new Mt19937(42)));
        $gen2 = new PassphraseGenerator(randomizer: new Randomizer(new Mt19937(42)));

        $result1 = $gen1->generate(numWords: 4, wordSeparator: ' ');
        $result2 = $gen2->generate(numWords: 4, wordSeparator: ' ');

        $this->assertCount(4, explode(' ', $result1));
        $this->assertSame($result1, $result2);
    }

    public function test_gen_words_different_counts(): void
    {
        $result4 = $this->generator->generate(numWords: 4, wordSeparator: ' ');
        $this->assertCount(4, explode(' ', $result4));

        $result3 = $this->generator->generate(numWords: 3, wordSeparator: ' ');
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
        $generator = new PassphraseGenerator($wordList, new Randomizer(new Mt19937(0)));

        $result = $generator->generate(
            numWords: 3,
            wordSeparator: ' ',
            capitalize: true,
            includeNumber: false,
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
        $generator = new PassphraseGenerator($wordList, new Randomizer(new Mt19937(0)));

        $result = $generator->generate(
            numWords: 3,
            wordSeparator: ' ',
            capitalize: false,
            includeNumber: true,
        );

        $words = explode(' ', $result);
        $this->assertCount(3, $words);

        // Exactly one word should end with a digit
        $wordsWithDigit = array_filter($words, fn($w) => preg_match('/\d$/', $w));
        $this->assertCount(1, $wordsWithDigit);
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
        $result = $this->generator->generate(
            numWords: 4,
            wordSeparator: '👨🏻‍❤️‍💋‍👨🏻',
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
        $result = $this->generator->generate(
            numWords: 4,
            wordSeparator: '-',
            capitalize: true,
            includeNumber: true,
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
        $wordsWithDigit = array_filter($parts, fn($p) => preg_match('/\d$/', $p));
        $this->assertCount(1, $wordsWithDigit);
    }

    public function test_passphrase_deterministic_same_seed(): void
    {
        for ($seed = 0; $seed < 5; $seed++) {
            $gen1 = new PassphraseGenerator(randomizer: new Randomizer(new Mt19937($seed)));
            $gen2 = new PassphraseGenerator(randomizer: new Randomizer(new Mt19937($seed)));

            $result1 = $gen1->generate(numWords: 4, wordSeparator: '-', capitalize: true, includeNumber: true);
            $result2 = $gen2->generate(numWords: 4, wordSeparator: '-', capitalize: true, includeNumber: true);

            $this->assertSame($result1, $result2, "Seed {$seed} should produce identical output");
        }
    }

    public function test_passphrase_no_capitalize_no_number(): void
    {
        $result = $this->generator->generate(
            numWords: 5,
            wordSeparator: ';',
            capitalize: false,
            includeNumber: false,
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
        $result = $this->generator->generate(
            numWords: 3,
            wordSeparator: ' ',
            capitalize: true,
            includeNumber: false,
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
    // setDefaults tests
    // -------------------------------------------------------

    public function test_set_defaults_are_used_by_generate(): void
    {
        $wordList = WordList::fromArray(['alpha', 'bravo', 'charlie']);
        $generator = new PassphraseGenerator($wordList);

        $generator->setDefaults(
            numWords: 3,
            wordSeparator: '.',
            capitalize: true,
            includeNumber: false,
        );

        $result = $generator->generate();
        $words = explode('.', $result);

        $this->assertCount(3, $words);
        foreach ($words as $word) {
            $first = mb_substr($word, 0, 1);
            $this->assertSame(mb_strtoupper($first), $first, "Expected '{$word}' to be capitalized");
        }
    }

    public function test_explicit_params_override_defaults(): void
    {
        $wordList = WordList::fromArray(['alpha', 'bravo', 'charlie']);
        $generator = new PassphraseGenerator($wordList);

        $generator->setDefaults(
            numWords: 5,
            wordSeparator: '.',
            capitalize: true,
            includeNumber: true,
        );

        // Explicitly pass different values
        $result = $generator->generate(
            numWords: 3,
            wordSeparator: '-',
            capitalize: false,
            includeNumber: false,
        );

        $words = explode('-', $result);
        $this->assertCount(3, $words);
        $this->assertSame(mb_strtolower($result), $result);
        $this->assertDoesNotMatchRegularExpression('/\d/', $result);
    }

    public function test_set_defaults_returns_self(): void
    {
        $generator = new PassphraseGenerator();
        $result = $generator->setDefaults();
        $this->assertSame($generator, $result);
    }

    public function test_set_defaults_validates_num_words(): void
    {
        $generator = new PassphraseGenerator();

        $this->expectException(InvalidNumWordsException::class);
        $generator->setDefaults(numWords: PassphraseGenerator::MINIMUM_NUM_WORDS - 1);
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
