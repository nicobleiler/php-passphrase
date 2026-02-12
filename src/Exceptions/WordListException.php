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

    public static function tooSmall(int $count, int $minimum): self
    {
        return new self("Word list must contain at least {$minimum} words, got {$count}");
    }
}
