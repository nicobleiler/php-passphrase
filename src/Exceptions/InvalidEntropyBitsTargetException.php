<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Exceptions;

use InvalidArgumentException;

class InvalidEntropyBitsTargetException extends InvalidArgumentException
{
    public static function belowMinimum(): self
    {
        return new self('Target entropy bits must be greater than 0');
    }
}
