<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase;

use Illuminate\Support\ServiceProvider;
use NicoBleiler\Passphrase\Exceptions\WordListException;

class PassphraseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/passphrase.php', 'passphrase');

        $this->app->singleton(WordList::class, function (): \NicoBleiler\Passphrase\WordList {
            $wordList = config('passphrase.word_list');

            if ($wordList !== null) {
                if (! is_array($wordList)) {
                    throw WordListException::invalidConfigType();
                }

                return WordList::fromArray($wordList);
            }

            return WordList::eff();
        });

        $this->app->singleton(PassphraseGenerator::class, function ($app): \NicoBleiler\Passphrase\PassphraseGenerator {
            $generator = new PassphraseGenerator($app->make(WordList::class));

            $generator->setDefaults(
                numWords: (int) config('passphrase.num_words', 3),
                wordSeparator: (string) config('passphrase.word_separator', '-'),
                capitalize: (bool) config('passphrase.capitalize', false),
                includeNumber: (bool) config('passphrase.include_number', false),
            );

            return $generator;
        });

        $this->app->alias(PassphraseGenerator::class, 'passphrase');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/passphrase.php' => config_path('passphrase.php'),
            ], 'passphrase-config');

            $this->publishes([
                __DIR__.'/../resources/wordlists' => resource_path('wordlists'),
            ], 'passphrase-wordlists');
        }
    }
}
