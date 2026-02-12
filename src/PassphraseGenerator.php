<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase;

use NicoBleiler\Passphrase\Exceptions\InvalidNumWordsException;

class PassphraseGenerator
{
    public const MINIMUM_NUM_WORDS = 3;
    public const MAXIMUM_NUM_WORDS = 20;

    private WordList $wordList;

    public function __construct(?WordList $wordList = null)
    {
        $this->wordList = $wordList ?? WordList::eff();
    }

    /**
     * Generate a passphrase.
     *
     * @param int $numWords Number of words (3-20)
     * @param string $wordSeparator Character(s) to separate words
     * @param bool $capitalize Capitalize first letter of each word
     * @param bool $includeNumber Append a random digit to a random word
     */
    public function generate(
        int $numWords = 3,
        string $wordSeparator = '-',
        bool $capitalize = false,
        bool $includeNumber = false,
    ): string {
        $this->validateNumWords($numWords);

        $words = $this->generateWords($numWords);

        if ($includeNumber) {
            $this->includeNumberInWords($words);
        }

        if ($capitalize) {
            $this->capitalizeWords($words);
        }

        return implode($wordSeparator, $words);
    }

    /**
     * Generate words using a seeded random number generator for deterministic output.
     * This is used internally for testing.
     *
     * @param int $numWords Number of words to generate
     * @param string $wordSeparator Separator between words
     * @param bool $capitalize Capitalize first letter of each word
     * @param bool $includeNumber Append a random digit to a random word
     * @param callable $rngInt A function that returns a random integer: fn(int $min, int $max): int
     */
    public function generateWithRng(
        int $numWords,
        string $wordSeparator,
        bool $capitalize,
        bool $includeNumber,
        callable $rngInt,
    ): string {
        $this->validateNumWords($numWords);

        $words = $this->generateWordsWithRng($numWords, $rngInt);

        if ($includeNumber) {
            $this->includeNumberInWordsWithRng($words, $rngInt);
        }

        if ($capitalize) {
            $this->capitalizeWords($words);
        }

        return implode($wordSeparator, $words);
    }

    /**
     * Generate random words from the word list.
     *
     * @return string[]
     */
    private function generateWords(int $numWords): array
    {
        $words = [];
        for ($i = 0; $i < $numWords; $i++) {
            $words[] = $this->wordList->randomWord();
        }

        return $words;
    }

    /**
     * Generate words using a custom RNG function.
     *
     * @return string[]
     */
    private function generateWordsWithRng(int $numWords, callable $rngInt): array
    {
        $wordCount = $this->wordList->count();
        $words = [];
        for ($i = 0; $i < $numWords; $i++) {
            $index = $rngInt(0, $wordCount - 1);
            $words[] = $this->wordList->wordAt($index);
        }

        return $words;
    }

    /**
     * Append a random digit (0-9) to a randomly selected word.
     *
     * @param string[] &$words
     */
    private function includeNumberInWords(array &$words): void
    {
        $index = random_int(0, count($words) - 1);
        $digit = random_int(0, 9);
        $words[$index] .= (string) $digit;
    }

    /**
     * Append a random digit (0-9) to a randomly selected word using a custom RNG.
     *
     * @param string[] &$words
     */
    private function includeNumberInWordsWithRng(array &$words, callable $rngInt): void
    {
        $index = $rngInt(0, count($words) - 1);
        $digit = $rngInt(0, 9);
        $words[$index] .= (string) $digit;
    }

    /**
     * Capitalize the first letter of each word.
     *
     * Supports multibyte/unicode characters.
     *
     * @param string[] &$words
     */
    private function capitalizeWords(array &$words): void
    {
        foreach ($words as &$word) {
            $word = self::capitalizeFirstLetter($word);
        }
    }

    /**
     * Capitalize the first letter of a string.
     *
     * Handles multibyte characters correctly (like Bitwarden's implementation).
     */
    public static function capitalizeFirstLetter(string $s): string
    {
        if ($s === '') {
            return '';
        }

        $firstChar = mb_substr($s, 0, 1, 'UTF-8');
        $rest = mb_substr($s, 1, null, 'UTF-8');

        return mb_strtoupper($firstChar, 'UTF-8') . $rest;
    }

    /**
     * Get the current word list.
     */
    public function getWordList(): WordList
    {
        return $this->wordList;
    }

    private function validateNumWords(int $numWords): void
    {
        if ($numWords < self::MINIMUM_NUM_WORDS || $numWords > self::MAXIMUM_NUM_WORDS) {
            throw new InvalidNumWordsException(self::MINIMUM_NUM_WORDS, self::MAXIMUM_NUM_WORDS);
        }
    }
}
