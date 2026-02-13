<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Tests;

use NicoBleiler\Passphrase\Exceptions\WordListException;
use NicoBleiler\Passphrase\WordList;
use PHPUnit\Framework\TestCase;

class WordListTest extends TestCase
{
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

    public function test_from_file_not_found_throws(): void
    {
        $this->expectException(WordListException::class);
        $this->expectExceptionMessage('Word list file not found');
        WordList::fromFile('/nonexistent/path/to/wordlist.txt');
    }

    public function test_from_file_plain_format(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'wl_');
        file_put_contents($tmpFile, "apple\nbanana\ncherry\n");

        try {
            $wordList = WordList::fromFile($tmpFile);
            $this->assertSame(3, $wordList->count());
            $this->assertSame('apple', $wordList->wordAt(0));
            $this->assertSame('cherry', $wordList->wordAt(2));
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_from_file_eff_format(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'wl_');
        file_put_contents($tmpFile, "11111\tabacus\n11112\tabdomen\n11113\tabdominal\n");

        try {
            $wordList = WordList::fromFile($tmpFile);
            $this->assertSame(3, $wordList->count());
            $this->assertSame('abacus', $wordList->wordAt(0));
            $this->assertSame('abdominal', $wordList->wordAt(2));
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_from_file_ignores_blank_lines(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'wl_');
        file_put_contents($tmpFile, "apple\n\nbanana\n\n\ncherry\n");

        try {
            $wordList = WordList::fromFile($tmpFile);
            $this->assertSame(3, $wordList->count());
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_random_word_returns_word_from_list(): void
    {
        $words = ['alpha', 'bravo', 'charlie'];
        $wordList = WordList::fromArray($words);

        for ($i = 0; $i < 50; $i++) {
            $this->assertContains($wordList->randomWord(), $words);
        }
    }

    public function test_custom_word_list_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'custom_wl_');
        $customWords = ['correct', 'horse', 'battery', 'staple'];
        file_put_contents($tmpFile, implode("\n", $customWords));

        try {
            $wordList = WordList::fromFile($tmpFile);
            $this->assertSame(4, $wordList->count());
            $this->assertSame($customWords, $wordList->all());
        } finally {
            unlink($tmpFile);
        }
    }
}
