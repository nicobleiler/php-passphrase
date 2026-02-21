<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Exceptions;

use RuntimeException;

class WordListException extends RuntimeException
{
    public static function invalidConfigType(): self
    {
        return new self('Word list config must be an array of strings');
    }

    public static function fileNotFound(string $path): self
    {
        return new self("Word list file not found: {$path}");
    }

    public static function empty(): self
    {
        return new self('Word list is empty');
    }

    public static function invalidType(): self
    {
        return new self('Word list must contain only strings');
    }

    public static function invalidExcludedWordsType(): self
    {
        return new self('Excluded words must contain only strings');
    }

    public static function invalidExcludedWordsConfigType(): self
    {
        return new self('Excluded words config must be an array of strings');
    }
}
