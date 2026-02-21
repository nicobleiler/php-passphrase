<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase;

use NicoBleiler\Passphrase\Exceptions\InvalidEntropyBitsTargetException;
use NicoBleiler\Passphrase\Exceptions\InvalidNumWordsException;
use Random\Engine\Secure;
use Random\Randomizer;

class PassphraseGenerator
{
    /**
     * Minimum allowed number of words in a generated passphrase.
     *
     * @internal
     */
    public const MINIMUM_NUM_WORDS = 3;

    /**
     * Maximum allowed number of words in a generated passphrase.
     *
     * @internal
     *
     * @deprecated This constant is planned for removal in the next major version. Do not rely on a fixed maximum word count.
     */
    public const MAXIMUM_NUM_WORDS = 20;

    /**
     * Default number of words used when no explicit value is provided.
     *
     * @internal
     */
    public const DEFAULT_NUM_WORDS = 3;

    /**
     * Default separator used between words in generated passphrases.
     *
     * @internal
     */
    public const DEFAULT_WORD_SEPARATOR = '-';

    /**
     * Default capitalization setting for generated passphrases.
     *
     * @internal
     */
    public const DEFAULT_CAPITALIZE = false;

    /**
     * Default setting for appending a random digit to a generated passphrase.
     *
     * @internal
     */
    public const DEFAULT_INCLUDE_NUMBER = false;

    private WordList $wordList;

    private int $defaultNumWords = self::DEFAULT_NUM_WORDS;

    private string $defaultWordSeparator = self::DEFAULT_WORD_SEPARATOR;

    private bool $defaultCapitalize = self::DEFAULT_CAPITALIZE;

    private bool $defaultIncludeNumber = self::DEFAULT_INCLUDE_NUMBER;

    /**
     * @param  Randomizer  $randomizer
     *                                  Optional. Defaults to a cryptographically secure randomizer.
     *                                  Advanced use: inject for deterministic tests or reproducible output.
     */
    public function __construct(?WordList $wordList = null, private Randomizer $randomizer = new Randomizer(new Secure))
    {
        $this->wordList = $wordList ?? WordList::eff();
    }

    /**
     * Set the default generation options.
     *
     * These defaults are used by generate() when parameters are not explicitly provided.
     * In Laravel, the service provider calls this with values from config/passphrase.php.
     */
    public function setDefaults(
        int $numWords = self::DEFAULT_NUM_WORDS,
        string $wordSeparator = self::DEFAULT_WORD_SEPARATOR,
        bool $capitalize = self::DEFAULT_CAPITALIZE,
        bool $includeNumber = self::DEFAULT_INCLUDE_NUMBER,
    ): self {
        $this->validateNumWords($numWords);

        $this->defaultNumWords = $numWords;
        $this->defaultWordSeparator = $wordSeparator;
        $this->defaultCapitalize = $capitalize;
        $this->defaultIncludeNumber = $includeNumber;

        return $this;
    }

    /**
     * Generate a passphrase.
     *
     * Parameters default to the instance defaults set via setDefaults().
     * In Laravel, these come from config/passphrase.php.
     *
     * @param  ?int  $numWords  Number of words (minimum 3), null to use instance default
     * @param  ?string  $wordSeparator  Character(s) to separate words, null to use instance default
     * @param  ?bool  $capitalize  Capitalize first letter of each word, null to use instance default
     * @param  ?bool  $includeNumber  Append a random digit (0-9) to a random word, null to use instance default
     * @param  ?int  $targetEntropyBits  Optional. If set, adjusts numWords to meet or exceed this target entropy.
     *                                   Entropy is calculated conservatively only based on the number of words in the word list, ignoring the additional entropy from numbers.
     */
    public function generate(
        ?int $numWords = null,
        ?string $wordSeparator = null,
        ?bool $capitalize = null,
        ?bool $includeNumber = null,
        ?int $targetEntropyBits = null,
    ): string {
        $numWords ??= $this->defaultNumWords;
        $wordSeparator ??= $this->defaultWordSeparator;
        $capitalize ??= $this->defaultCapitalize;
        $includeNumber ??= $this->defaultIncludeNumber;

        if ($targetEntropyBits !== null) {
            $this->validateTargetEntropyBits($targetEntropyBits);

            $desiredNumWords = (int) ceil($targetEntropyBits / $this->wordList->entropyPerWord());
            $numWords = max($desiredNumWords, self::MINIMUM_NUM_WORDS);
        }

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
     * Generate random words from the word list.
     *
     * @return string[]
     */
    private function generateWords(int $numWords): array
    {
        $wordCount = $this->wordList->count();
        $words = [];
        for ($i = 0; $i < $numWords; $i++) {
            $index = $this->randomizer->getInt(0, $wordCount - 1);
            $words[] = $this->wordList->wordAt($index);
        }

        return $words;
    }

    /**
     * Append a random digit (0-9) to a randomly selected word.
     *
     * @param  string[]  &$words
     */
    private function includeNumberInWords(array &$words): void
    {
        $wordCount = count($words);

        if ($wordCount === 0) {
            return;
        }

        $max = $wordCount - 1;
        $index = $this->randomizer->getInt(0, $max);
        $digit = $this->randomizer->getInt(0, 9);
        $words[$index] .= (string) $digit;
    }

    /**
     * Capitalize the first letter of each word.
     *
     * Supports multibyte/unicode characters.
     *
     * @param  string[]  &$words
     */
    private function capitalizeWords(array &$words): void
    {
        foreach ($words as &$word) {
            $word = self::capitalizeFirstLetter($word);
        }

        unset($word);
    }

    /**
     * Capitalize the first letter of a string.
     *
     * Handles multibyte characters correctly.
     */
    public static function capitalizeFirstLetter(string $s): string
    {
        if ($s === '') {
            return '';
        }

        $firstChar = mb_substr($s, 0, 1, 'UTF-8');
        $rest = mb_substr($s, 1, null, 'UTF-8');

        return mb_strtoupper($firstChar, 'UTF-8').$rest;
    }

    /**
     * Get the current word list.
     */
    public function getWordList(): WordList
    {
        return $this->wordList;
    }

    /**
     * Validate the configured number of words.
     *
     * @throws InvalidNumWordsException
     */
    private function validateNumWords(int $numWords): void
    {
        if ($numWords < self::MINIMUM_NUM_WORDS || $numWords > self::MAXIMUM_NUM_WORDS) {
            throw new InvalidNumWordsException(self::MINIMUM_NUM_WORDS, self::MAXIMUM_NUM_WORDS);
        }
    }

    /**
     * Validate the target entropy bits.
     *
     * @throws InvalidEntropyBitsTargetException
     */
    private function validateTargetEntropyBits(int $targetEntropyBits): void
    {
        if ($targetEntropyBits <= 0) {
            throw new InvalidEntropyBitsTargetException;
        }
    }
}
