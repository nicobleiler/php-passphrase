<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase;

use NicoBleiler\Passphrase\Exceptions\WordListException;
use OutOfRangeException;

class WordList
{
    /** @var string[] */
    private array $words;

    private ?int $wordCount = null;

    /**
     * @param  string[]  $words
     */
    private function __construct(array $words)
    {
        $this->words = array_values($words);
    }

    /**
     * Load the bundled EFF long word list.
     */
    public static function eff(): self
    {
        static $cachedEff = null;

        return $cachedEff ??= self::loadBundledEff();
    }

    /**
     * Load and validate the bundled compiled EFF word list.
     */
    private static function loadBundledEff(): self
    {
        $compiledPath = self::effCompiledWordListPath();

        if (! file_exists($compiledPath)) {
            throw WordListException::fileNotFound($compiledPath);
        }

        $words = require $compiledPath;

        if (! is_array($words) || $words === []) {
            throw WordListException::empty();
        }

        return self::fromArray($words);
    }

    /**
     * Create a word list from an array of words.
     *
     * @param  string[]  $words
     */
    public static function fromArray(array $words): self
    {
        if ($words === []) {
            throw WordListException::empty();
        }

        foreach ($words as $word) {
            if (! is_string($word)) {
                throw WordListException::invalidType();
            }
        }

        return new self($words);
    }

    /**
     * Get the word at a specific index.
     *
     * @throws OutOfRangeException If the index is out of bounds
     */
    public function wordAt(int $index): string
    {
        if ($index < 0 || $index >= $this->count()) {
            throw new OutOfRangeException(
                sprintf('Word index %d is out of range [0, %d)', $index, $this->count())
            );
        }

        return $this->words[$index];
    }

    /**
     * Get the number of words in the list.
     */
    public function count(): int
    {
        return $this->wordCount ??= count($this->words);
    }

    /**
     * Get all words.
     *
     * @return string[]
     */
    public function all(): array
    {
        return $this->words;
    }

    /**
     * Get the path to the compiled bundled EFF large word list.
     */
    public static function effCompiledWordListPath(): string
    {
        return dirname(__DIR__).'/resources/wordlists/eff_large_wordlist.php';
    }
}
