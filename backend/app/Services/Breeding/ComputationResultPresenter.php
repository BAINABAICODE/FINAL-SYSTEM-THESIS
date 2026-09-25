<?php

namespace App\Services\Breeding;

use App\Models\Bird;

/**
 * Thin adapter: Computation & Result payload is assembled by AgaporaGeneticEngine.
 */
class ComputationResultPresenter
{
    public function __construct(
        private readonly AgaporaGeneticEngine $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $prediction
     * @param  list<array<string, mixed>>  $eggOutcomes
     * @param  array<string, mixed>|null  $clutchSimulation
     * @return array<string, mixed>
     */
    public function build(Bird $parentOne, Bird $parentTwo, array $validation, array $prediction, array $eggOutcomes, ?array $clutchSimulation = null): array
    {
        return $this->engine->compose($parentOne, $parentTwo, $validation, $prediction, $eggOutcomes, $clutchSimulation);
    }
}
