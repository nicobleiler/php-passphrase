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

        $compiledPath = self::effCompiledWordListPath();
        $words = require $compiledPath;

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
        if ($lines === false || count($lines) === 0) {
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
     * Get the path to the compiled bundled EFF large word list.
     */
    public static function effCompiledWordListPath(): string
    {
        return dirname(__DIR__) . '/resources/wordlists/eff_large_wordlist.php';
    }
}
