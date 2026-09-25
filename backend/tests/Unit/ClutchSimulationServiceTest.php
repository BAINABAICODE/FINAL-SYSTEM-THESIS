<?php

namespace Tests\Unit;

use App\Services\Breeding\ClutchSimulationService;
use Tests\TestCase;

class ClutchSimulationServiceTest extends TestCase
{
    private ClutchSimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClutchSimulationService;
    }

    public function test_every_level_distribution_sums_to_100_and_stays_within_3_to_7(): void
    {
        foreach (ClutchSimulationService::LEVELS as $key => $level) {
            $this->assertSame(100, array_sum($level['clutch']), "Level {$key} must sum to 100");
            $this->assertSame([3, 4, 5, 6, 7], array_keys($level['clutch']), "Level {$key} must cover 3–7 eggs only");
        }
    }

    public function test_classification_thresholds(): void
    {
        $this->assertSame('very_high', $this->service->classify(100)['key']);
        $this->assertSame('very_high', $this->service->classify(90)['key']);
        $this->assertSame('high', $this->service->classify(89)['key']);
        $this->assertSame('high', $this->service->classify(75)['key']);
        $this->assertSame('moderate', $this->service->classify(74)['key']);
        $this->assertSame('moderate', $this->service->classify(60)['key']);
        $this->assertSame('low', $this->service->classify(59)['key']);
        $this->assertSame('low', $this->service->classify(40)['key']);
        $this->assertSame('very_low', $this->service->classify(39)['key']);
        $this->assertSame('very_low', $this->service->classify(0)['key']);
        $this->assertSame('moderate', $this->service->classify(null)['key']);
    }

    public function test_same_seed_replays_identically_and_clutch_stays_in_range(): void
    {
        $templates = $this->templates();
        $first = $this->service->simulate(84, $templates, 12345);
        $second = $this->service->simulate(84, $templates, 12345);

        $this->assertSame($first['eggs'], $second['eggs']);
        $this->assertSame($first['clutch_size'], $second['clutch_size']);

        foreach (range(1, 500) as $seed) {
            $sim = $this->service->simulate(25, $templates, $seed);
            $this->assertGreaterThanOrEqual(3, $sim['clutch_size']);
            $this->assertLessThanOrEqual(7, $sim['clutch_size']);
            $this->assertCount($sim['clutch_size'], $sim['eggs']);
            $c = $sim['counts'];
            $this->assertSame($c['eggs_laid'], $c['living_chicks'] + $c['unfertilized'] + $c['failed_to_develop'] + $c['failed_to_hatch']);
        }
    }

    public function test_low_compatibility_never_produces_zero_eggs_and_high_never_guarantees_seven(): void
    {
        $templates = $this->templates();
        $sizesHigh = [];
        $sizesLow = [];
        foreach (range(1, 400) as $seed) {
            $sizesHigh[] = $this->service->simulate(95, $templates, $seed)['clutch_size'];
            $sizesLow[] = $this->service->simulate(20, $templates, $seed)['clutch_size'];
        }

        $this->assertGreaterThan(1, count(array_unique($sizesHigh)), 'High compatibility must still be probabilistic');
        $this->assertNotContains(0, $sizesLow);
        $this->assertGreaterThanOrEqual(3, min($sizesLow));
        $this->assertGreaterThan(array_sum($sizesLow) / count($sizesLow), array_sum($sizesHigh) / count($sizesHigh), 'Higher compatibility should tend toward larger clutches');
    }

    public function test_gica_does_not_change_rbgia_probabilities_in_the_outcome_pool(): void
    {
        $templates = $this->templates();
        $poolHigh = $this->service->simulate(95, $templates, 7)['genetic_outcome_pool']['weights'];
        $poolLow = $this->service->simulate(20, $templates, 7)['genetic_outcome_pool']['weights'];

        $this->assertSame($poolHigh, $poolLow);
        $this->assertEqualsWithDelta(0.75, $poolHigh[0]['probability'], 1e-9);
        $this->assertEqualsWithDelta(0.25, $poolHigh[1]['probability'], 1e-9);
    }

    public function test_living_chicks_sample_from_rbgia_distribution_only(): void
    {
        $templates = $this->templates();
        $green = 0;
        $blue = 0;
        $living = 0;
        foreach (range(1, 600) as $seed) {
            $sim = $this->service->simulate(88, $templates, $seed);
            foreach ($sim['eggs'] as $egg) {
                if ($egg['status'] !== ClutchSimulationService::STATUS_LIVING_CHICK) {
                    $this->assertNull($egg['outcome_index']);
                    continue;
                }
                $living++;
                $egg['outcome_index'] === 0 ? $green++ : $blue++;
            }
        }

        $this->assertGreaterThan(0, $living);
        $this->assertEqualsWithDelta(0.75, $green / $living, 0.05, 'Sampled outcomes must follow the 75/25 RBGIA distribution');
    }

    public function test_apply_to_eggs_keeps_genetics_for_living_and_never_invents_for_failed(): void
    {
        $templates = $this->templates();
        $sim = $this->service->simulate(50, $templates, 99);
        $cards = $this->service->applyToEggs($templates, $sim, ['common_name' => "Fischer's Lovebird"]);

        $this->assertCount($sim['clutch_size'], $cards);
        foreach ($cards as $i => $card) {
            $this->assertSame($i + 1, $card['egg_number']);
            if ($card['egg_status'] === ClutchSimulationService::STATUS_LIVING_CHICK) {
                $this->assertNotNull($card['genotype']);
                $this->assertNotNull($card['rbgia_outcome_key']);
                $this->assertStringContainsString('#egg-'.($i + 1), $card['outcome_key']);
            } else {
                $this->assertNull($card['genotype']);
                $this->assertNull($card['sex']);
                $this->assertSame('not_applicable', $card['image']['status']);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            ['outcome_key' => 'joint-1', 'genotype' => 'bl+/bl', 'sex' => 'cock', 'base_color' => 'Green', 'probability' => ['probability' => 0.75, 'fraction' => '3/4'], 'genetic_explanation' => 'RBGIA outcome 1.'],
            ['outcome_key' => 'joint-2', 'genotype' => 'bl/bl', 'sex' => 'hen', 'base_color' => 'Blue', 'probability' => ['probability' => 0.25, 'fraction' => '1/4'], 'genetic_explanation' => 'RBGIA outcome 2.'],
        ];
    }
}
