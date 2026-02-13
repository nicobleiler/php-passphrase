<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

/** @return list<string> */
function officialProviderNames(): array
{
    return [
        'php-passphrase (EFF 5 words, ~64.6 bits)',
        'genphrase/genphrase (65-bit target, diceware)',
        'martbock/laravel-diceware (EFF 5 words, ~64.6 bits)',
        'random_bytes(8) hex (~64 bits)',
        'Illuminate\\Support\\Str::random(11) (~65.5 bits)',
    ];
}

/**
 * @return array{
 *   iterations: int,
 *   warmup: int,
 *   json: bool,
 *   providers: list<string>
 * }
 */
function benchmarkOptions(): array
{
    /** @var array<string, mixed> $raw */
     $raw = getopt('', ['iterations::', 'warmup::', 'json', 'provider::']);

    $iterations = isset($raw['iterations']) ? max(1, (int) $raw['iterations']) : 1000;
    $warmup = isset($raw['warmup']) ? max(0, (int) $raw['warmup']) : 100;
    $json = array_key_exists('json', $raw);

    $providers = [];
    if (array_key_exists('provider', $raw)) {
        $providerRaw = $raw['provider'];
        if (is_array($providerRaw)) {
            $providers = array_values(array_filter(array_map('strval', $providerRaw), static fn (string $name): bool => $name !== ''));
        } elseif ($providerRaw !== false && $providerRaw !== null) {
            $providers = [strval($providerRaw)];
        }
    }

    return [
        'iterations' => $iterations,
        'warmup' => $warmup,
        'json' => $json,
        'providers' => $providers,
    ];
}

/**
 * @param list<array{name: string, group: string, callback: callable}> $providers
 * @param list<string> $requiredNames
 *
 * @return list<string>
 */
function missingProviderNames(array $providers, array $requiredNames): array
{
    $availableNames = [];
    foreach ($providers as $provider) {
        $availableNames[$provider['name']] = true;
    }

    $missing = [];
    foreach ($requiredNames as $requiredName) {
        if (! isset($availableNames[$requiredName])) {
            $missing[] = $requiredName;
        }
    }

    return $missing;
}

/**
 * @param list<array{name: string, group: string, callback: callable}> $providers
 *
 * @return list<array{name: string, group: string, callback: callable}>
 */
function filterProviders(array $providers, array $selectedNames): array
{
    if ($selectedNames === []) {
        return $providers;
    }

    $selectedLookup = array_fill_keys($selectedNames, true);

    return array_values(array_filter(
        $providers,
        static fn (array $provider): bool => isset($selectedLookup[$provider['name']])
    ));
}

/**
 * @param callable(): string $callback
 *
 * @return array{
 *   cold_start_duration_ms: float,
 *   total_duration_ms: float,
 *   average_duration_ms: float,
 *   slowest_iteration_ms: float,
 *   slowest_iteration_index: int,
 *   fastest_iteration_ms: float,
 *   fastest_iteration_index: int,
 *   retained_memory_delta_bytes: int,
 *   peak_memory_delta_bytes: int,
 *   sample_outputs: list<string>
 * }
 */
function benchmarkProvider(callable $callback, int $iterations, int $warmup): array
{
    gc_collect_cycles();
     $baselineMemory = memory_get_usage(false);
    $maxMemoryUsage = $baselineMemory;

    $coldStartBegin = hrtime(true);
    $coldStartOutput = $callback();
    $coldStartDurationMs = (hrtime(true) - $coldStartBegin) / 1_000_000;
    $maxMemoryUsage = max($maxMemoryUsage, memory_get_usage(false));

    for ($i = 0; $i < $warmup; $i++) {
        $callback();
        $maxMemoryUsage = max($maxMemoryUsage, memory_get_usage(false));
    }

    $sampleOutputs = [$coldStartOutput];
    $slowestIterationMs = -1.0;
    $slowestIterationIndex = -1;
    $fastestIterationMs = INF;
    $fastestIterationIndex = -1;

    $benchmarkStart = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $iterationStart = hrtime(true);
        $output = $callback();
        $iterationNs = hrtime(true) - $iterationStart;
        $iterationMs = $iterationNs / 1_000_000;
        $maxMemoryUsage = max($maxMemoryUsage, memory_get_usage(false));

        if ($i < 4) {
            $sampleOutputs[] = $output;
        }

        if ($iterationMs > $slowestIterationMs) {
            $slowestIterationMs = $iterationMs;
            $slowestIterationIndex = $i;
        }

        if ($iterationMs < $fastestIterationMs) {
            $fastestIterationMs = $iterationMs;
            $fastestIterationIndex = $i;
        }
    }

    $totalDurationNs = hrtime(true) - $benchmarkStart;
    $totalDurationMs = $totalDurationNs / 1_000_000;
    $retainedMemory = memory_get_usage(false);

    return [
        'cold_start_duration_ms' => $coldStartDurationMs,
        'total_duration_ms' => $totalDurationMs,
        'average_duration_ms' => $totalDurationMs / $iterations,
        'slowest_iteration_ms' => $slowestIterationMs,
        'slowest_iteration_index' => $slowestIterationIndex,
        'fastest_iteration_ms' => $fastestIterationMs,
        'fastest_iteration_index' => $fastestIterationIndex,
        'retained_memory_delta_bytes' => max(0, $retainedMemory - $baselineMemory),
        'peak_memory_delta_bytes' => max(0, $maxMemoryUsage - $baselineMemory),
        'sample_outputs' => $sampleOutputs,
    ];
}

/**
 * @param array<int, array{name: string, group: string, status: string, stats?: array<string, mixed>}> $providerResults
 *
 * @return array{
 *   best_average_speed?: array{name: string, value_ms: float},
 *   lowest_memory_consumption?: array{name: string, value_bytes: int},
 *   fastest_cold_start?: array{name: string, value_ms: float}
 * }
 */
function benchmarkWinners(array $providerResults): array
{
    $okResults = array_values(array_filter(
        $providerResults,
        static fn (array $result): bool => $result['status'] === 'ok' && isset($result['stats']) && is_array($result['stats'])
    ));

    if ($okResults === []) {
        return [];
    }

    usort(
        $okResults,
        static fn (array $a, array $b): int => ($a['stats']['average_duration_ms'] <=> $b['stats']['average_duration_ms'])
    );
    $bestAverageSpeed = $okResults[0];

    usort(
        $okResults,
        static fn (array $a, array $b): int =>
            ($a['stats']['retained_memory_delta_bytes'] <=> $b['stats']['retained_memory_delta_bytes'])
            ?: ($a['stats']['peak_memory_delta_bytes'] <=> $b['stats']['peak_memory_delta_bytes'])
    );
    $lowestMemory = $okResults[0];

    usort(
        $okResults,
        static fn (array $a, array $b): int => ($a['stats']['cold_start_duration_ms'] <=> $b['stats']['cold_start_duration_ms'])
    );
    $fastestColdStart = $okResults[0];

    return [
        'best_average_speed' => [
            'name' => $bestAverageSpeed['name'],
            'value_ms' => $bestAverageSpeed['stats']['average_duration_ms'],
        ],
        'lowest_memory_consumption' => [
            'name' => $lowestMemory['name'],
            'value_bytes' => $lowestMemory['stats']['retained_memory_delta_bytes'],
        ],
        'fastest_cold_start' => [
            'name' => $fastestColdStart['name'],
            'value_ms' => $fastestColdStart['stats']['cold_start_duration_ms'],
        ],
    ];
}

/**
 * @param array<int, array{name: string, group: string, status: string, stats?: array<string, mixed>}> $providerResults
 *
 * @return array<int, array{name: string, group: string, status: string, stats?: array<string, mixed>}>
 */
function filterResultsByGroup(array $providerResults, string $group): array
{
    return array_values(array_filter(
        $providerResults,
        static fn (array $result): bool => $result['group'] === $group
    ));
}

$options = benchmarkOptions();

/** @var list<array{name: string, group: string, callback: callable}> $providers */
$providers = require __DIR__ . '/providers.php';

 $officialNames = officialProviderNames();
 $missingOfficialProviders = missingProviderNames($providers, $officialNames);

if ($missingOfficialProviders !== []) {
    fwrite(STDERR, "Official benchmark providers are missing:\n");
    foreach ($missingOfficialProviders as $missingProvider) {
        fwrite(STDERR, "- {$missingProvider}\n");
    }
    exit(1);
}

$providers = filterProviders($providers, $officialNames);

$providers = filterProviders($providers, $options['providers']);

if ($providers === []) {
    fwrite(STDERR, "No benchmark providers selected.\n");
    exit(1);
}

$results = [
    'environment' => [
        'php_version' => PHP_VERSION,
        'os_family' => PHP_OS_FAMILY,
        'iterations' => $options['iterations'],
        'warmup' => $options['warmup'],
    ],
    'providers' => [],
];

foreach ($providers as $provider) {
    try {
        $stats = benchmarkProvider($provider['callback'], $options['iterations'], $options['warmup']);
        $results['providers'][] = [
            'name' => $provider['name'],
            'group' => $provider['group'],
            'status' => 'ok',
            'stats' => $stats,
        ];
    } catch (Throwable $throwable) {
        $results['providers'][] = [
            'name' => $provider['name'],
            'group' => $provider['group'],
            'status' => 'error',
            'error' => [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
            ],
        ];
    }
}

$results['category_winners_all'] = benchmarkWinners($results['providers']);
$results['category_winners_passphrase'] = benchmarkWinners(filterResultsByGroup($results['providers'], 'passphrase'));

if ($options['json']) {
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

echo 'PHP Passphrase Benchmarks' . PHP_EOL;
echo str_repeat('=', 24) . PHP_EOL;
echo 'PHP ' . $results['environment']['php_version'] . ' on ' . $results['environment']['os_family'] . PHP_EOL;
echo 'Iterations: ' . $results['environment']['iterations'] . ', Warmup: ' . $results['environment']['warmup'] . PHP_EOL;
echo PHP_EOL;

foreach ($results['providers'] as $providerResult) {
    echo '- ' . $providerResult['name'] . PHP_EOL;

    if ($providerResult['status'] !== 'ok') {
        echo '  ERROR: ' . $providerResult['error']['class'] . ': ' . $providerResult['error']['message'] . PHP_EOL;
        echo PHP_EOL;
        continue;
    }

    $stats = $providerResult['stats'];
    echo sprintf("  cold:    %.6f ms\n", $stats['cold_start_duration_ms']);
    echo sprintf("  total:   %.3f ms\n", $stats['total_duration_ms']);
    echo sprintf("  average: %.6f ms\n", $stats['average_duration_ms']);
    echo sprintf(
        "  slowest: %.6f ms (iteration %d)\n",
        $stats['slowest_iteration_ms'],
        $stats['slowest_iteration_index']
    );
    echo sprintf(
        "  fastest: %.6f ms (iteration %d)\n",
        $stats['fastest_iteration_ms'],
        $stats['fastest_iteration_index']
    );
    echo sprintf(
        "  memory:  %d bytes retained delta, %d bytes peak delta\n",
        $stats['retained_memory_delta_bytes'],
        $stats['peak_memory_delta_bytes']
    );

    if ($stats['sample_outputs'] !== []) {
        echo "  samples:\n";
        foreach ($stats['sample_outputs'] as $index => $output) {
            echo sprintf("    %d: %s\n", $index, $output);
        }
    }

    echo PHP_EOL;
}

$winnersAll = $results['category_winners_all'];
if (is_array($winnersAll) && $winnersAll !== []) {
    echo 'Category winners (all providers)' . PHP_EOL;
    echo str_repeat('-', 16) . PHP_EOL;
    echo sprintf(
        "- Best average speed: %s (%.6f ms)\n",
        $winnersAll['best_average_speed']['name'],
        $winnersAll['best_average_speed']['value_ms']
    );
    echo sprintf(
        "- Lowest memory consumption: %s (%d bytes)\n",
        $winnersAll['lowest_memory_consumption']['name'],
        $winnersAll['lowest_memory_consumption']['value_bytes']
    );
    echo sprintf(
        "- Fastest cold start: %s (%.6f ms)\n",
        $winnersAll['fastest_cold_start']['name'],
        $winnersAll['fastest_cold_start']['value_ms']
    );
    echo PHP_EOL;
}

$winnersPassphrase = $results['category_winners_passphrase'];
if (is_array($winnersPassphrase) && $winnersPassphrase !== []) {
    echo 'Category winners (passphrase libraries)' . PHP_EOL;
    echo str_repeat('-', 16) . PHP_EOL;
    echo sprintf(
        "- Best average speed: %s (%.6f ms)\n",
        $winnersPassphrase['best_average_speed']['name'],
        $winnersPassphrase['best_average_speed']['value_ms']
    );
    echo sprintf(
        "- Lowest memory consumption: %s (%d bytes)\n",
        $winnersPassphrase['lowest_memory_consumption']['name'],
        $winnersPassphrase['lowest_memory_consumption']['value_bytes']
    );
    echo sprintf(
        "- Fastest cold start: %s (%.6f ms)\n",
        $winnersPassphrase['fastest_cold_start']['name'],
        $winnersPassphrase['fastest_cold_start']['value_ms']
    );
    echo PHP_EOL;
}
