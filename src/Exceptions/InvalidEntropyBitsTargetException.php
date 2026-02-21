<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Exceptions;

use InvalidArgumentException;

class InvalidEntropyBitsTargetException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Target entropy bits must be greater than 0');
    }
}
