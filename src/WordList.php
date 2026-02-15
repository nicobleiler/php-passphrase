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

        if ($cachedEff instanceof self) {
            return $cachedEff;
        }

        $compiledPath = self::effCompiledWordListPath();

        if (! file_exists($compiledPath)) {
            throw WordListException::fileNotFound($compiledPath);
        }

        $words = require $compiledPath;

        if (! is_array($words) || $words === []) {
            throw WordListException::empty();
        }

        $cachedEff = self::fromArray($words);

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
        if ($lines === false || $lines === []) {
            throw WordListException::empty();
        }

        $words = [];
        $firstNonEmptyLine = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $firstNonEmptyLine = $line;
                break;
            }
        }

        if ($firstNonEmptyLine === '') {
            throw WordListException::empty();
        }

        $firstWhitespacePos = strcspn($firstNonEmptyLine, " \t");
        $isDiceWareFormat = $firstWhitespacePos > 0
            && $firstWhitespacePos < strlen($firstNonEmptyLine)
            && ctype_digit(substr($firstNonEmptyLine, 0, $firstWhitespacePos));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($isDiceWareFormat) {
                $whitespacePos = strcspn($line, " \t");

                if ($whitespacePos > 0 && $whitespacePos < strlen($line)) {
                    $diceKey = substr($line, 0, $whitespacePos);
                    if (ctype_digit($diceKey)) {
                        $word = ltrim(substr($line, $whitespacePos));
                        if ($word !== '') {
                            $words[] = $word;

                            continue;
                        }
                    }
                }
            }

            $words[] = $line;
        }

        if ($words === []) {
            throw WordListException::empty();
        }

        return new self($words);
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
     * Get the path to the compiled bundled EFF large word list.
     */
    public static function effCompiledWordListPath(): string
    {
        return dirname(__DIR__).'/resources/wordlists/eff_large_wordlist.php';
    }
}
