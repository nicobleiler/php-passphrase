<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Exceptions;

use InvalidArgumentException;

class InvalidNumWordsException extends InvalidArgumentException
{
    public function __construct(int $minimum, int $maximum)
    {
        parent::__construct("'num_words' must be between {$minimum} and {$maximum}");
    }
}
