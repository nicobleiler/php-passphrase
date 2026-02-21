<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Tests;

use NicoBleiler\Passphrase\Exceptions\WordListException;
use NicoBleiler\Passphrase\WordList;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

class WordListTest extends TestCase
{
    public function test_eff_compiled_word_list_file_exists(): void
    {
        $this->assertFileExists(WordList::effCompiledWordListPath());
    }

    public function test_eff_compiled_word_list_is_valid_array_of_strings(): void
    {
        $words = require WordList::effCompiledWordListPath();

        $this->assertIsArray($words);
        $this->assertNotEmpty($words);
        $this->assertContainsOnlyString($words);
        $this->assertSame(7776, count($words));
        $this->assertSame('abacus', $words[0]);
        $this->assertSame('zoom', $words[count($words) - 1]);
    }

    public function test_eff_word_list_loads_7776_words(): void
    {
        $wordList = WordList::eff();
        $this->assertSame(7776, $wordList->count());
    }

    public function test_eff_word_list_first_word(): void
    {
        $wordList = WordList::eff();
        $this->assertSame('abacus', $wordList->wordAt(0));
    }

    public function test_eff_word_list_last_word(): void
    {
        $wordList = WordList::eff();
        $this->assertSame('zoom', $wordList->wordAt($wordList->count() - 1));
    }

    public function test_eff_word_list_is_cached_between_calls(): void
    {
        $first = WordList::eff();
        $second = WordList::eff();

        $this->assertSame($first, $second);
    }

    public function test_from_array(): void
    {
        $words = ['alpha', 'bravo', 'charlie'];
        $wordList = WordList::fromArray($words);

        $this->assertSame(3, $wordList->count());
        $this->assertSame('alpha', $wordList->wordAt(0));
        $this->assertSame('charlie', $wordList->wordAt(2));
    }

    public function test_from_array_empty_throws(): void
    {
        $this->expectException(WordListException::class);
        $this->expectExceptionMessage('Word list is empty');
        WordList::fromArray([]);
    }

    public function test_custom_word_list_array(): void
    {
        $customWords = ['correct', 'horse', 'battery', 'staple'];
        $wordList = WordList::fromArray($customWords);

        $this->assertSame(4, $wordList->count());
        $this->assertSame($customWords, $wordList->all());
    }

    public function test_entropy_per_word_returns_log2_word_count(): void
    {
        $wordList = WordList::fromArray(['alpha', 'bravo', 'charlie', 'delta']);

        $this->assertSame(2.0, $wordList->entropyPerWord());
    }

    public function test_entropy_per_word_single_word_returns_zero(): void
    {
        $wordList = WordList::fromArray(['only']);

        $this->assertSame(0.0, $wordList->entropyPerWord());
    }

    public function test_entropy_per_word_non_power_of_two(): void
    {
        $wordList = WordList::fromArray(['alpha', 'bravo', 'charlie']);

        $this->assertEqualsWithDelta(log(3, 2), $wordList->entropyPerWord(), 1e-10);
    }

    public function test_word_at_negative_index_throws(): void
    {
        $wordList = WordList::fromArray(['alpha', 'bravo']);

        $this->expectException(OutOfRangeException::class);
        $wordList->wordAt(-1);
    }

    public function test_word_at_out_of_bounds_throws(): void
    {
        $wordList = WordList::fromArray(['alpha', 'bravo']);

        $this->expectException(OutOfRangeException::class);
        $wordList->wordAt(2);
    }

    public function test_from_array_non_string_throws(): void
    {
        $this->expectException(WordListException::class);
        $this->expectExceptionMessage('Word list must contain only strings');
        WordList::fromArray([42, 'hello']); // @phpstan-ignore argument.type
    }
}
