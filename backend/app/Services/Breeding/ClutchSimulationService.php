<?php

namespace App\Services\Breeding;

/**
 * AGAPORA Layer 2 — compatibility-based clutch / reproductive simulation.
 *
 * GICA influences ONLY the simulated clutch-size distribution and simulated viability tendency.
 * RBGIA genetic probabilities are never modified here: a living chick samples its genotype from the
 * unchanged RBGIA distribution.
 *
 * The simulation is seeded and every draw is recorded so a stored result can be replayed and audited.
 * This is a simulation model, not a biological claim that a compatibility score sets the number of eggs.
 */
class ClutchSimulationService
{
    public const MIN_EGGS = 3;

    public const MAX_EGGS = 7;

    public const STATUS_LIVING_CHICK = 'living_chick';

    public const STATUS_UNFERTILIZED = 'unfertilized';

    public const STATUS_FAILED_TO_DEVELOP = 'failed_to_develop';

    public const STATUS_FAILED_TO_HATCH = 'failed_to_hatch';

    public const STATUS_LABELS = [
        self::STATUS_LIVING_CHICK => 'Hatched → Living Chick',
        self::STATUS_UNFERTILIZED => 'Unfertilized',
        self::STATUS_FAILED_TO_DEVELOP => 'Failed to Develop',
        self::STATUS_FAILED_TO_HATCH => 'Failed to Hatch',
    ];

    /**
     * Compatibility level table. Clutch percentages must sum to 100 per level.
     * Viability values are simulated per-stage success tendencies (not biological measurements).
     */
    public const LEVELS = [
        'very_high' => [
            'label' => 'Very High Compatibility',
            'min' => 90,
            'max' => 100,
            'clutch' => [3 => 5, 4 => 10, 5 => 25, 6 => 35, 7 => 25],
            'tendency' => '5–7 eggs',
            'viability' => ['fertilization' => 0.94, 'development' => 0.95, 'hatching' => 0.95],
            'viability_tendency' => 'Highest simulated viability tendency',
        ],
        'high' => [
            'label' => 'High Compatibility',
            'min' => 75,
            'max' => 89,
            'clutch' => [3 => 10, 4 => 15, 5 => 30, 6 => 30, 7 => 15],
            'tendency' => '4–7 eggs',
            'viability' => ['fertilization' => 0.91, 'development' => 0.93, 'hatching' => 0.94],
            'viability_tendency' => 'High simulated viability tendency',
        ],
        'moderate' => [
            'label' => 'Moderate Compatibility',
            'min' => 60,
            'max' => 74,
            'clutch' => [3 => 15, 4 => 25, 5 => 30, 6 => 20, 7 => 10],
            'tendency' => '4–6 eggs',
            'viability' => ['fertilization' => 0.88, 'development' => 0.91, 'hatching' => 0.92],
            'viability_tendency' => 'Normal simulated viability tendency',
        ],
        'low' => [
            'label' => 'Low Compatibility',
            'min' => 40,
            'max' => 59,
            'clutch' => [3 => 25, 4 => 30, 5 => 25, 6 => 15, 7 => 5],
            'tendency' => '3–5 eggs',
            'viability' => ['fertilization' => 0.82, 'development' => 0.88, 'hatching' => 0.90],
            'viability_tendency' => 'Increased simulated failure probability',
        ],
        'very_low' => [
            'label' => 'Very Low Compatibility',
            'min' => 0,
            'max' => 39,
            'clutch' => [3 => 35, 4 => 30, 5 => 20, 6 => 10, 7 => 5],
            'tendency' => '3–5 eggs',
            'viability' => ['fertilization' => 0.76, 'development' => 0.85, 'hatching' => 0.88],
            'viability_tendency' => 'Increased simulated failure probability',
        ],
    ];

    public const EXPLANATION = 'The compatibility score is used as a simulation factor for the expected clutch distribution and reproductive outcome. Actual egg production and hatch success may vary in real breeding conditions.';

    public const DISCLAIMER = 'Simulation model only. Actual clutch size is influenced by age, reproductive condition, nutrition, health, environment, breeding history, and individual variation. Lower compatibility level results in a higher simulated probability of unsuccessful reproductive outcomes; it is not a claim that the birds cannot breed.';

    private const ROLL_PRECISION = 10000;

    /**
     * @return array{key: string, label: string, min: int, max: int, clutch: array<int, int>, tendency: string, viability: array<string, float>, viability_tendency: string, score: ?int}
     */
    public function classify(?int $score): array
    {
        $bounded = $score === null ? null : max(0, min(100, $score));
        $key = 'moderate';

        if ($bounded !== null) {
            foreach (self::LEVELS as $levelKey => $level) {
                if ($bounded >= $level['min'] && $bounded <= $level['max']) {
                    $key = $levelKey;
                    break;
                }
            }
        }

        return [...self::LEVELS[$key], 'key' => $key, 'score' => $bounded];
    }

    /**
     * @param  list<array<string, mixed>>  $outcomeTemplates  RBGIA outcome cards (each carries probability.probability)
     * @return array<string, mixed>
     */
    public function simulate(?int $gicaScore, array $outcomeTemplates, ?int $seed = null): array
    {
        $seed ??= random_int(1, 2147483646);
        $level = $this->classify($gicaScore);
        $weights = $this->outcomeWeights($outcomeTemplates);

        mt_srand($seed);

        $clutchRoll = $this->roll();
        $clutchSize = $this->sampleClutchSize($level['clutch'], $clutchRoll);

        $eggs = [];
        $counts = [
            'eggs_laid' => $clutchSize,
            'fertilized' => 0,
            'developed' => 0,
            'hatched' => 0,
            'living_chicks' => 0,
            'unfertilized' => 0,
            'failed_to_develop' => 0,
            'failed_to_hatch' => 0,
        ];

        for ($number = 1; $number <= $clutchSize; $number++) {
            $egg = $this->simulateEgg($number, $level['viability'], $weights);
            $eggs[] = $egg;

            match ($egg['status']) {
                self::STATUS_UNFERTILIZED => $counts['unfertilized']++,
                self::STATUS_FAILED_TO_DEVELOP => $counts['failed_to_develop']++,
                self::STATUS_FAILED_TO_HATCH => $counts['failed_to_hatch']++,
                default => $counts['living_chicks']++,
            };

            if ($egg['status'] !== self::STATUS_UNFERTILIZED) {
                $counts['fertilized']++;
            }
            if (in_array($egg['status'], [self::STATUS_FAILED_TO_HATCH, self::STATUS_LIVING_CHICK], true)) {
                $counts['developed']++;
            }
            if ($egg['status'] === self::STATUS_LIVING_CHICK) {
                $counts['hatched']++;
            }
        }

        return [
            'model' => 'AGAPORA-Clutch-Simulation-v1',
            'seed' => $seed,
            'gica_score' => $gicaScore,
            'compatibility_level' => [
                'key' => $level['key'],
                'label' => $level['label'],
                'range' => $level['min'].'–'.$level['max'],
                'tendency' => $level['tendency'],
                'viability_tendency' => $level['viability_tendency'],
            ],
            'clutch_distribution' => $this->distributionRows($level['clutch']),
            'viability_modifiers' => $level['viability'],
            'clutch_roll' => $clutchRoll,
            'clutch_size' => $clutchSize,
            'allowed_range' => ['min' => self::MIN_EGGS, 'max' => self::MAX_EGGS],
            'genetic_outcome_pool' => [
                'source' => 'RBGIA theoretical distribution (unchanged by GICA)',
                'outcome_count' => count($weights),
                'weights' => array_map(fn (array $row) => [
                    'index' => $row['index'],
                    'outcome_key' => $row['outcome_key'],
                    'probability' => round($row['probability'], 6),
                ], $weights),
            ],
            'eggs' => $eggs,
            'counts' => $counts,
            'levels' => $this->levelsSummary(),
            'explanation' => self::EXPLANATION,
            'disclaimer' => self::DISCLAIMER,
            'pipeline' => [
                'GICA Score',
                'Compatibility Classification',
                'Compatibility Clutch Distribution',
                'Weighted Clutch Size Sample [3–7]',
                'Per egg: Fertilization → Development → Hatching',
                'Living chick: sample RBGIA genetic outcome (probabilities unchanged)',
            ],
        ];
    }

    /**
     * Turn RBGIA outcome templates + the simulation into the stored egg cards.
     *
     * @param  list<array<string, mixed>>  $outcomeTemplates
     * @param  array<string, mixed>  $simulation
     * @return list<array<string, mixed>>
     */
    public function applyToEggs(array $outcomeTemplates, array $simulation, mixed $species): array
    {
        $templates = array_values($outcomeTemplates);
        $cards = [];

        foreach ($simulation['eggs'] ?? [] as $egg) {
            $number = (int) $egg['egg_number'];
            $status = (string) $egg['status'];
            $meta = [
                'seed' => $simulation['seed'] ?? null,
                'compatibility_level' => $simulation['compatibility_level']['label'] ?? null,
                'stages' => $egg['stages'] ?? [],
                'genetic_outcome_roll' => $egg['genetic_outcome_roll'] ?? null,
            ];

            if ($status !== self::STATUS_LIVING_CHICK || $egg['outcome_index'] === null || ! isset($templates[$egg['outcome_index']])) {
                $cards[] = $this->nonLivingCard($number, $status, $species, $meta, $egg['outcome_index'] === null && $status === self::STATUS_LIVING_CHICK);
                continue;
            }

            $template = $templates[$egg['outcome_index']];
            $rbgiaKey = $template['outcome_key'] ?? ('joint-'.($egg['outcome_index'] + 1));

            $cards[] = [
                ...$template,
                'egg_number' => $number,
                'egg_outcome_id' => 'egg-'.$number,
                'outcome_key' => $rbgiaKey.'#egg-'.$number,
                'rbgia_outcome_key' => $rbgiaKey,
                'rbgia_outcome_index' => $egg['outcome_index'],
                'egg_status' => self::STATUS_LIVING_CHICK,
                'egg_status_label' => self::STATUS_LABELS[self::STATUS_LIVING_CHICK],
                'source' => 'clutch_simulation+RBGIA',
                'representation' => 'simulated_living_chick',
                'clutch_simulation' => $meta,
                'hatch_forecast_status' => 'Simulated living chick. Genetic outcome sampled from the unchanged RBGIA distribution ('
                    .($template['probability']['fraction'] ?? 'n/a').').',
                'genetic_explanation' => ($template['genetic_explanation'] ?? '')
                    .' Egg #'.$number.' status "'.self::STATUS_LABELS[self::STATUS_LIVING_CHICK].'" comes from the compatibility-based clutch simulation; the chick genotype comes from RBGIA only.',
            ];
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @return array<string, mixed>
     */
    public function summary(array $simulation): array
    {
        $counts = $simulation['counts'] ?? [];

        return [
            'gica_score' => $simulation['gica_score'] ?? null,
            'compatibility_level' => $simulation['compatibility_level']['label'] ?? null,
            'simulated_clutch' => $counts['eggs_laid'] ?? null,
            'living_chicks' => $counts['living_chicks'] ?? null,
            'hatched' => $counts['hatched'] ?? null,
            'unfertilized' => $counts['unfertilized'] ?? null,
            'failed_to_develop' => $counts['failed_to_develop'] ?? null,
            'failed_to_hatch' => $counts['failed_to_hatch'] ?? null,
            'explanation' => self::EXPLANATION,
        ];
    }

    /**
     * @param  array<string, float>  $viability
     * @param  list<array{index: int, outcome_key: ?string, probability: float, cumulative: float}>  $weights
     * @return array<string, mixed>
     */
    private function simulateEgg(int $number, array $viability, array $weights): array
    {
        $stages = [];

        foreach (['fertilization' => self::STATUS_UNFERTILIZED, 'development' => self::STATUS_FAILED_TO_DEVELOP, 'hatching' => self::STATUS_FAILED_TO_HATCH] as $stage => $failureStatus) {
            $roll = $this->roll();
            $threshold = (float) $viability[$stage];
            $passed = $roll <= $threshold;
            $stages[] = [
                'stage' => $stage,
                'roll' => $roll,
                'success_probability' => $threshold,
                'passed' => $passed,
            ];

            if (! $passed) {
                return [
                    'egg_number' => $number,
                    'status' => $failureStatus,
                    'status_label' => self::STATUS_LABELS[$failureStatus],
                    'stages' => $stages,
                    'outcome_index' => null,
                    'outcome_key' => null,
                    'genetic_outcome_roll' => null,
                ];
            }
        }

        $outcomeRoll = $this->roll();
        $picked = $this->sampleOutcome($weights, $outcomeRoll);

        return [
            'egg_number' => $number,
            'status' => self::STATUS_LIVING_CHICK,
            'status_label' => self::STATUS_LABELS[self::STATUS_LIVING_CHICK],
            'stages' => $stages,
            'outcome_index' => $picked['index'] ?? null,
            'outcome_key' => $picked['outcome_key'] ?? null,
            'genetic_outcome_roll' => $outcomeRoll,
        ];
    }

    /**
     * @param  array<int, int>  $distribution
     */
    private function sampleClutchSize(array $distribution, float $roll): int
    {
        $cumulative = 0.0;
        $total = array_sum($distribution) ?: 1;
        $last = self::MIN_EGGS;

        foreach ($distribution as $size => $percent) {
            $cumulative += $percent / $total;
            $last = (int) $size;
            if ($roll <= $cumulative) {
                return max(self::MIN_EGGS, min(self::MAX_EGGS, (int) $size));
            }
        }

        return max(self::MIN_EGGS, min(self::MAX_EGGS, $last));
    }

    /**
     * @param  list<array{index: int, outcome_key: ?string, probability: float, cumulative: float}>  $weights
     * @return array{index: int, outcome_key: ?string}|null
     */
    private function sampleOutcome(array $weights, float $roll): ?array
    {
        if ($weights === []) {
            return null;
        }

        foreach ($weights as $row) {
            if ($roll <= $row['cumulative']) {
                return ['index' => $row['index'], 'outcome_key' => $row['outcome_key']];
            }
        }

        $last = $weights[array_key_last($weights)];

        return ['index' => $last['index'], 'outcome_key' => $last['outcome_key']];
    }

    /**
     * Normalised RBGIA outcome weights. Probabilities are read, never altered.
     *
     * @param  list<array<string, mixed>>  $templates
     * @return list<array{index: int, outcome_key: ?string, probability: float, cumulative: float}>
     */
    private function outcomeWeights(array $templates): array
    {
        $rows = [];
        foreach (array_values($templates) as $index => $template) {
            if (! is_array($template)) {
                continue;
            }
            $probability = $template['probability']['probability'] ?? null;
            if ($probability === null && isset($template['probability']['fraction'])) {
                $probability = $this->fractionToFloat((string) $template['probability']['fraction']);
            }
            $probability = (float) ($probability ?? 0);
            if ($probability <= 0) {
                continue;
            }
            $rows[] = ['index' => $index, 'outcome_key' => $template['outcome_key'] ?? null, 'probability' => $probability];
        }

        $total = array_sum(array_column($rows, 'probability'));
        if ($total <= 0) {
            return [];
        }

        $cumulative = 0.0;
        foreach ($rows as &$row) {
            $row['probability'] = $row['probability'] / $total;
            $cumulative += $row['probability'];
            $row['cumulative'] = $cumulative;
        }
        unset($row);

        if ($rows !== []) {
            $rows[array_key_last($rows)]['cumulative'] = 1.0;
        }

        return $rows;
    }

    private function fractionToFloat(string $fraction): ?float
    {
        if (str_contains($fraction, '/')) {
            [$num, $den] = array_map('trim', explode('/', $fraction, 2));
            if (is_numeric($num) && is_numeric($den) && (float) $den !== 0.0) {
                return (float) $num / (float) $den;
            }
        }
        if (str_ends_with($fraction, '%') && is_numeric(rtrim($fraction, '%'))) {
            return (float) rtrim($fraction, '%') / 100;
        }

        return is_numeric($fraction) ? (float) $fraction : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function nonLivingCard(int $number, string $status, mixed $species, array $meta, bool $livingWithoutGenetics): array
    {
        $label = self::STATUS_LABELS[$status] ?? $status;
        $note = $livingWithoutGenetics
            ? 'Simulated as a living chick, but RBGIA returned no joint genetic outcome to sample. Genotype was not invented.'
            : 'Simulated reproductive outcome from the compatibility-based clutch simulation. No genetic outcome applies to this egg; nothing was invented.';

        return [
            'egg_number' => $number,
            'egg_outcome_id' => 'egg-'.$number,
            'outcome_key' => 'clutch-egg-'.$number.'-'.$status,
            'rbgia_outcome_key' => null,
            'rbgia_outcome_index' => null,
            'egg_status' => $status,
            'egg_status_label' => $label,
            'source' => 'clutch_simulation',
            'representation' => 'simulated_egg_outcome',
            'category' => 'clutch_egg',
            'trait_name' => $label,
            'species' => $species,
            'sex' => null,
            'sex_label' => null,
            'base_color' => null,
            'alleles' => [],
            'visual_mutations' => [],
            'split_hidden_genes' => [],
            'genotype' => null,
            'genotype_display' => null,
            'phenotype' => null,
            'probability' => null,
            'inherited_traits' => [],
            'genetic_explanation' => $note,
            'hatch_forecast_status' => $label,
            'clutch_simulation' => $meta,
            'image' => [
                'status' => 'not_applicable',
                'image_url' => null,
                'image_path' => null,
                'provider' => null,
                'message' => 'No chick image — this simulated egg did not produce a living chick.',
                'updated_at' => now()->toIso8601String(),
            ],
            'visualization_payload' => null,
        ];
    }

    private function roll(): float
    {
        return mt_rand(1, self::ROLL_PRECISION) / self::ROLL_PRECISION;
    }

    /**
     * @param  array<int, int>  $distribution
     * @return list<array{eggs: int, percent: int}>
     */
    private function distributionRows(array $distribution): array
    {
        $rows = [];
        foreach ($distribution as $eggs => $percent) {
            $rows[] = ['eggs' => (int) $eggs, 'percent' => (int) $percent];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function levelsSummary(): array
    {
        $rows = [];
        foreach (self::LEVELS as $key => $level) {
            $rows[] = [
                'key' => $key,
                'label' => $level['label'],
                'range' => $level['min'].'–'.$level['max'],
                'tendency' => $level['tendency'],
                'viability_tendency' => $level['viability_tendency'],
                'clutch' => $this->distributionRows($level['clutch']),
                'viability' => $level['viability'],
            ];
        }

        return $rows;
    }
}
