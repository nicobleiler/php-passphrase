<?php

declare(strict_types=1);

namespace NicoBleiler\Passphrase\Benchmarks;

use GenPhrase\Password;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Martbock\Diceware\WordGenerator;
use NicoBleiler\Passphrase\PassphraseGenerator;
use PhpBench\Attributes as Bench;
use RuntimeException;

#[Bench\Iterations(20)]
class ProvidersBench
{
    /** @var callable():string */
    private $warmGenerator;

    #[Bench\ParamProviders(['provideProviders'])]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    public function benchGenerateCold(array $params): void
    {
        $generator = $this->createGenerator((string) $params['provider']);
        $generator();
    }

    #[Bench\ParamProviders(['provideProviders'])]
    #[Bench\BeforeMethods(['setUpWarm'])]
    #[Bench\Revs(100)]
    #[Bench\Warmup(2)]
    public function benchGenerateWarm(array $params): void
    {
        ($this->warmGenerator)();
    }

    public function setUpWarm(array $params): void
    {
        $this->warmGenerator = $this->createGenerator((string) $params['provider']);

        // Prime provider internals before warm measurement.
        ($this->warmGenerator)();
    }

    public function provideProviders(): iterable
    {
        yield 'php-passphrase (EFF 5 words, ~64.6 bits)' => [
            'provider' => 'php-passphrase',
            'kind' => 'passphrase',
            'entropy_bits' => 64.6,
        ];

        if (class_exists(Password::class)) {
            yield 'genphrase/genphrase (65-bit target, diceware)' => [
                'provider' => 'genphrase',
                'kind' => 'passphrase',
                'entropy_bits' => 65.0,
            ];
        }

        if (class_exists(WordGenerator::class)) {
            yield 'martbock/laravel-diceware (EFF 5 words, ~64.6 bits)' => [
                'provider' => 'laravel-diceware',
                'kind' => 'passphrase',
                'entropy_bits' => 64.6,
            ];
        }

        yield 'random_bytes(8) hex (~64 bits)' => [
            'provider' => 'random-bytes',
            'kind' => 'baseline',
            'entropy_bits' => 64.0,
        ];

        if (class_exists(Str::class)) {
            yield 'Illuminate\\Support\\Str::random(11) (~65.5 bits)' => [
                'provider' => 'illuminate-str-random',
                'kind' => 'baseline',
                'entropy_bits' => 65.5,
            ];
        }
    }

    /** @return callable():string */
    private function createGenerator(string $provider): callable
    {
        return match ($provider) {
            'php-passphrase' => $this->phpPassphraseGenerator(),
            'genphrase' => $this->genphraseGenerator(),
            'laravel-diceware' => $this->laravelDicewareGenerator(),
            'random-bytes' => static fn (): string => bin2hex(random_bytes(8)),
            'illuminate-str-random' => static fn (): string => Str::random(11),
            default => throw new RuntimeException(sprintf('Unknown benchmark provider: %s', $provider)),
        };
    }

    /** @return callable():string */
    private function phpPassphraseGenerator(): callable
    {
        $generator = new PassphraseGenerator;

        return static fn (): string => $generator->generate(
            numWords: 5,
            wordSeparator: '-',
            capitalize: false,
            includeNumber: false,
        );
    }

    /** @return callable():string */
    private function genphraseGenerator(): callable
    {
        $generator = new Password;
        $generator->removeWordlist('default');
        $generator->addWordlist('diceware.lst', 'diceware');
        $generator->disableSeparators(true);
        $generator->disableWordModifier(true);

        return static fn (): string => $generator->generate(65);
    }

    /** @return callable():string */
    private function laravelDicewareGenerator(): callable
    {
        $container = new Container;
        $container->instance('files', new Filesystem);
        Facade::setFacadeApplication($container);

        $generator = new WordGenerator([
            'number_of_words' => 5,
            'separator' => '-',
            'capitalize' => false,
            'add_number' => false,
            'wordlist' => 'eff',
            'custom_wordlist_path' => null,
            'number_of_dice' => 5,
        ]);

        return static fn (): string => $generator->generatePassphrase(5, '-');
    }
}
