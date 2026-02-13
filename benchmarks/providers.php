<?php

declare(strict_types=1);

use NicoBleiler\Passphrase\PassphraseGenerator;

$providers = [
    [
        'name' => 'php-passphrase (EFF 5 words, ~64.6 bits)',
        'group' => 'passphrase',
        'callback' => static function (): string {
            static $generator = null;
            $generator ??= new PassphraseGenerator();

            return $generator->generate(
                numWords: 5,
                wordSeparator: '-',
                capitalize: false,
                includeNumber: false,
            );
        },
    ],
    [
        'name' => 'random_bytes(8) hex (~64 bits)',
        'group' => 'baseline',
        'callback' => static function (): string {
            return bin2hex(random_bytes(8));
        },
    ],
];

if (class_exists(\Illuminate\Support\Str::class)) {
    $providers[] = [
        'name' => 'Illuminate\\Support\\Str::random(11) (~65.5 bits)',
        'group' => 'baseline',
        'callback' => static function (): string {
            return \Illuminate\Support\Str::random(11);
        },
    ];
}

if (class_exists(\GenPhrase\Password::class)) {
    $providers[] = [
        'name' => 'genphrase/genphrase (65-bit target, diceware)',
        'group' => 'passphrase',
        'callback' => static function (): string {
            static $generator = null;

            if (! $generator instanceof \GenPhrase\Password) {
                $generator = new \GenPhrase\Password();
                $generator->removeWordlist('default');
                $generator->addWordlist('diceware.lst', 'diceware');
                $generator->disableSeparators(true);
                $generator->disableWordModifier(true);
            }

            return $generator->generate(65);
        },
    ];
}

if (
    class_exists(\Martbock\Diceware\WordGenerator::class)
    && class_exists(\Illuminate\Support\Facades\Facade::class)
    && class_exists(\Illuminate\Container\Container::class)
    && class_exists(\Illuminate\Filesystem\Filesystem::class)
) {
    $providers[] = [
        'name' => 'martbock/laravel-diceware (EFF 5 words, ~64.6 bits)',
        'group' => 'passphrase',
        'callback' => static function (): string {
            static $generator = null;

            if (! $generator instanceof \Martbock\Diceware\WordGenerator) {
                $container = new \Illuminate\Container\Container();
                $container->instance('files', new \Illuminate\Filesystem\Filesystem());
                \Illuminate\Support\Facades\Facade::setFacadeApplication($container);

                $generator = new \Martbock\Diceware\WordGenerator([
                    'number_of_words' => 5,
                    'separator' => '-',
                    'capitalize' => false,
                    'add_number' => false,
                    'wordlist' => 'eff',
                    'custom_wordlist_path' => null,
                    'number_of_dice' => 5,
                ]);
            }

            return $generator->generatePassphrase(5, '-');
        },
    ];
}

return $providers;
