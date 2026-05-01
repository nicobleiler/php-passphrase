<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use NicoBleiler\Passphrase\Exceptions\ConfigException;
use NicoBleiler\Passphrase\Exceptions\WordListException;

class PassphraseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/passphrase.php', 'passphrase');

        $this->app->singleton(WordList::class, function (): WordList {
            $wordList = config('passphrase.word_list');
            $excludedWords = config('passphrase.excluded_words', []);

            if (! is_array($excludedWords)) {
                throw ConfigException::invalidExcludedWords();
            }

            foreach ($excludedWords as $excludedWord) {
                if (! is_string($excludedWord)) {
                    throw WordListException::invalidExcludedWordsType();
                }
            }

            /** @var array<string> $excludedWords */
            if ($wordList !== null) {
                if (! is_array($wordList)) {
                    throw ConfigException::invalidWordList();
                }

                foreach ($wordList as $configuredWord) {
                    if (! is_string($configuredWord)) {
                        throw WordListException::invalidType();
                    }
                }

                /** @var array<string> $wordList */

                return WordList::fromArray($wordList)->excludeWords($excludedWords);
            }

            return WordList::eff()->excludeWords($excludedWords);
        });

        $this->app->singleton(PassphraseGenerator::class, function (Application $app): PassphraseGenerator {
            $numWords = config('passphrase.num_words', PassphraseGenerator::DEFAULT_NUM_WORDS);
            if (! is_int($numWords)) {
                throw ConfigException::invalidNumWords();
            }

            $wordSeparator = config('passphrase.word_separator', PassphraseGenerator::DEFAULT_WORD_SEPARATOR);
            if (! is_string($wordSeparator)) {
                throw ConfigException::invalidWordSeparator();
            }

            $capitalize = config('passphrase.capitalize', PassphraseGenerator::DEFAULT_CAPITALIZE);
            if (! is_bool($capitalize)) {
                throw ConfigException::invalidCapitalize();
            }

            $includeNumber = config('passphrase.include_number', PassphraseGenerator::DEFAULT_INCLUDE_NUMBER);
            if (! is_bool($includeNumber)) {
                throw ConfigException::invalidIncludeNumber();
            }

            $generator = new PassphraseGenerator($app->make(WordList::class));
            $generator->setDefaults(
                numWords: $numWords,
                wordSeparator: $wordSeparator,
                capitalize: $capitalize,
                includeNumber: $includeNumber,
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
