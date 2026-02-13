<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase;

use NicoBleiler\Passphrase\Exceptions\WordListException;

class WordList
{
    /** @var string[] */
    private array $words;

    private ?int $wordCount = null;

    /**
     * @param string[] $words
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

        if ($cachedEff instanceof self) {
            return $cachedEff;
        }

        $cachedEff = self::fromFile(self::effWordListPath());

        return $cachedEff;
    }

    /**
     * Load a word list from a file.
     *
     * Supports two formats:
     * - One word per line
     * - EFF format: numeric index, whitespace, then word (e.g. "11111\tabacus")
     */
    public static function fromFile(string $path): self
    {
        if (! file_exists($path) || ! is_readable($path)) {
            throw WordListException::fileNotFound($path);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw WordListException::fileNotFound($path);
        }

        $lines = preg_split('/\r?\n/', trim($contents));
        if ($lines === false || count($lines) === 0) {
            throw WordListException::empty();
        }

        $words = [];
        $isDiceWareFormat = preg_match('/^\d+\s+.+$/', $lines[0]) === 1;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Support Dice Ware style or plain word per line
            if ($isDiceWareFormat && preg_match('/^\d+\s+(.+)$/', $line, $matches)) {
                $words[] = $matches[1];
            } else {
                $words[] = $line;
            }
        }

        if (count($words) === 0) {
            throw WordListException::empty();
        }

        return new self($words);
    }

    /**
     * Create a word list from an array of words.
     *
     * @param string[] $words
     */
    public static function fromArray(array $words): self
    {
        if (count($words) === 0) {
            throw WordListException::empty();
        }

        return new self($words);
    }

    /**
     * Get a random word from the list.
     */
    public function randomWord(): string
    {
        return $this->words[random_int(0, $this->count() - 1)];
    }

    /**
     * Get the word at a specific index.
     */
    public function wordAt(int $index): string
    {
        return $this->words[$index];
    }

    /**
     * Get the number of words in the list.
     */
    public function count(): int
    {
        if ($this->wordCount !== null) {
            return $this->wordCount;
        }

        $this->wordCount = count($this->words);

        return $this->wordCount;
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
     * Get the path to the bundled EFF large word list.
     */
    public static function effWordListPath(): string
    {
        return dirname(__DIR__) . '/resources/wordlists/eff_large_wordlist.txt';
    }
}
