<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Exceptions;

use RuntimeException;

class WordListException extends RuntimeException
{
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
}
