<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Facades;

use Illuminate\Support\Facades\Facade;
use NicoBleiler\Passphrase\PassphraseGenerator;

class Passphrase extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PassphraseGenerator::class;
    }
}
