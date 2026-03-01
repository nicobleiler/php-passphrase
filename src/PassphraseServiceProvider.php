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
            $excludedWords = config('passphrase.excluded_words', []);

            if (! is_array($excludedWords)) {
                throw WordListException::invalidExcludedWordsConfigType();
            }

            if ($wordList !== null) {
                if (! is_array($wordList)) {
                    throw WordListException::invalidConfigType();
                }

                return WordList::fromArray($wordList)->excludeWords($excludedWords);
            }

            return WordList::eff()->excludeWords($excludedWords);
        });

        $this->app->singleton(PassphraseGenerator::class, function ($app): \NicoBleiler\Passphrase\PassphraseGenerator {
            $generator = new PassphraseGenerator($app->make(WordList::class));

            $generator->setDefaults(
                numWords: (int) config('passphrase.num_words', PassphraseGenerator::DEFAULT_NUM_WORDS),
                wordSeparator: (string) config('passphrase.word_separator', PassphraseGenerator::DEFAULT_WORD_SEPARATOR),
                capitalize: (bool) config('passphrase.capitalize', PassphraseGenerator::DEFAULT_CAPITALIZE),
                includeNumber: (bool) config('passphrase.include_number', PassphraseGenerator::DEFAULT_INCLUDE_NUMBER),
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
