<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Tests;

use NicoBleiler\Passphrase\Exceptions\WordListException;
use NicoBleiler\Passphrase\Facades\Passphrase;
use NicoBleiler\Passphrase\PassphraseGenerator;
use NicoBleiler\Passphrase\PassphraseServiceProvider;
use NicoBleiler\Passphrase\WordList;
use Orchestra\Testbench\TestCase;

class LaravelIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PassphraseServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Passphrase' => Passphrase::class,
        ];
    }

    public function test_service_provider_registers_generator(): void
    {
        $generator = $this->app->make(PassphraseGenerator::class);
        $this->assertInstanceOf(PassphraseGenerator::class, $generator);
    }

    public function test_service_provider_registers_word_list(): void
    {
        $wordList = $this->app->make(WordList::class);
        $this->assertInstanceOf(WordList::class, $wordList);
        $this->assertSame(7776, $wordList->count());
    }

    public function test_generator_is_singleton(): void
    {
        $gen1 = $this->app->make(PassphraseGenerator::class);
        $gen2 = $this->app->make(PassphraseGenerator::class);
        $this->assertSame($gen1, $gen2);
    }

    public function test_facade_generates_passphrase(): void
    {
        $result = Passphrase::generate(numWords: 4, wordSeparator: '-');
        $words = explode('-', $result);
        $this->assertCount(4, $words);
    }

    public function test_config_defaults(): void
    {
        $this->assertSame(PassphraseGenerator::DEFAULT_NUM_WORDS, config('passphrase.num_words'));
        $this->assertSame(PassphraseGenerator::DEFAULT_WORD_SEPARATOR, config('passphrase.word_separator'));
        $this->assertSame(PassphraseGenerator::DEFAULT_CAPITALIZE, config('passphrase.capitalize'));
        $this->assertSame(PassphraseGenerator::DEFAULT_INCLUDE_NUMBER, config('passphrase.include_number'));
        $this->assertNull(config('passphrase.word_list'));
    }

    public function test_custom_word_list_from_config(): void
    {
        config(['passphrase.word_list' => ['correct', 'horse', 'battery', 'staple']]);

        // Re-register to pick up new config
        $this->app->forgetInstance(WordList::class);
        $this->app->forgetInstance(PassphraseGenerator::class);
        (new PassphraseServiceProvider($this->app))->register();

        $wordList = $this->app->make(WordList::class);
        $this->assertSame(4, $wordList->count());
        $this->assertSame(['correct', 'horse', 'battery', 'staple'], $wordList->all());
    }

    public function test_custom_word_list_can_be_loaded_via_require_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'custom_wl_');
        file_put_contents(
            $tmpFile,
            <<<'PHP'
                <?php

                return [
                    'correct',
                    'horse',
                    'battery',
                    'staple',
                ];
            PHP
        );

        try {
            config(['passphrase.word_list' => require $tmpFile]);

            // Re-register to pick up new config
            $this->app->forgetInstance(WordList::class);
            $this->app->forgetInstance(PassphraseGenerator::class);
            (new PassphraseServiceProvider($this->app))->register();

            $wordList = $this->app->make(WordList::class);
            $this->assertSame(4, $wordList->count());
            $this->assertSame(['correct', 'horse', 'battery', 'staple'], $wordList->all());
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_non_array_word_list_config_throws_clear_message(): void
    {
        config(['passphrase.word_list' => '/path/to/list.php']);

        $this->app->forgetInstance(WordList::class);
        $this->app->forgetInstance(PassphraseGenerator::class);
        (new PassphraseServiceProvider($this->app))->register();

        $this->expectExceptionObject(WordListException::invalidConfigType());

        $this->app->make(WordList::class);
    }

    public function test_config_defaults_are_applied_to_generator(): void
    {
        config([
            'passphrase.num_words' => 5,
            'passphrase.word_separator' => '.',
            'passphrase.capitalize' => true,
            'passphrase.include_number' => false,
        ]);

        // Re-register to pick up new config
        $this->app->forgetInstance(WordList::class);
        $this->app->forgetInstance(PassphraseGenerator::class);
        (new PassphraseServiceProvider($this->app))->register();

        $result = Passphrase::generate();
        $words = explode('.', $result);

        $this->assertCount(5, $words);
        foreach ($words as $word) {
            $cleaned = rtrim($word, '0123456789');
            if ($cleaned !== '') {
                $first = mb_substr($cleaned, 0, 1);
                $this->assertSame(mb_strtoupper($first), $first, "Expected '{$word}' to be capitalized");
            }
        }
    }

    public function test_config_include_number_is_applied(): void
    {
        config([
            'passphrase.num_words' => 3,
            'passphrase.word_separator' => '-',
            'passphrase.capitalize' => false,
            'passphrase.include_number' => true,
        ]);

        $this->app->forgetInstance(WordList::class);
        $this->app->forgetInstance(PassphraseGenerator::class);
        (new PassphraseServiceProvider($this->app))->register();

        $result = Passphrase::generate();
        $this->assertMatchesRegularExpression('/\d/', $result, 'Expected passphrase to contain a digit');
    }
}
