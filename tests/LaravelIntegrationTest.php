<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Tests;

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
        $this->assertSame(3, config('passphrase.num_words'));
        $this->assertSame('-', config('passphrase.word_separator'));
        $this->assertFalse(config('passphrase.capitalize'));
        $this->assertFalse(config('passphrase.include_number'));
        $this->assertNull(config('passphrase.word_list_path'));
    }

    public function test_custom_word_list_from_config(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'wl_');
        file_put_contents($tmpFile, "correct\nhorse\nbattery\nstaple\n");

        try {
            config(['passphrase.word_list_path' => $tmpFile]);

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
}
