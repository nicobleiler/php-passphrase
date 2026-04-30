<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Exceptions;

use RuntimeException;

class ConfigException extends RuntimeException
{
    public static function invalidNumWords(): self
    {
        return new self('Number of words config must be an integer');
    }

    public static function invalidWordSeparator(): self
    {
        return new self('Word separator config must be a string');
    }

    public static function invalidCapitalize(): self
    {
        return new self('Capitalize config must be a boolean');
    }

    public static function invalidIncludeNumber(): self
    {
        return new self('Include number config must be a boolean');
    }
}
