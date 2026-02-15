<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Facades;

use Illuminate\Support\Facades\Facade;
use NicoBleiler\Passphrase\PassphraseGenerator;

/**
 * @method static string generate(?int $numWords = null, ?string $wordSeparator = null, ?bool $capitalize = null, ?bool $includeNumber = null)
 * @method static self setDefaults(int $numWords = 3, string $wordSeparator = '-', bool $capitalize = false, bool $includeNumber = false)
 *
 * @see \NicoBleiler\Passphrase\PassphraseGenerator
 */
class Passphrase extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PassphraseGenerator::class;
    }
}
