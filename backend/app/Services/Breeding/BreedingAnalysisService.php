<?php

namespace App\Services\Breeding;

use App\Models\Bird;
use App\Models\BirdPair;
use App\Models\BreedingPrediction;
use App\Models\ComputationResult;
use App\Services\Birds\BirdRecordPersister;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BreedingAnalysisService
{
    public function __construct(
        private readonly BirdRecordPersister $birds,
        private readonly BreedingPairValidator $validator,
        private readonly RbgiaPredictor $predictor,
        private readonly OffspringVisualizationService $visualizations,
        private readonly OffspringPromptPdfService $promptPdf,
        private readonly ComputationResultPresenter $presenter,
        private readonly AnalysisPayloadCompressor $compressor,
        private readonly ClutchSimulationService $clutch,
    ) {}

    /**
     * @param  array<string, mixed>  $parentOne
     * @param  array<string, mixed>  $parentTwo
     * @return array<string, mixed>
     */
    public function analyze(array $parentOne, array $parentTwo): array
    {
        $hydrated = [
            $this->hydrate($parentOne),
            $this->hydrate($parentTwo),
        ];
        $preview = $this->validator->validate($hydrated[0], $hydrated[1]);

        if (! ($preview['can_predict'] ?? false)) {
            throw ValidationException::withMessages([
                'analysis' => $this->blockingMessages($preview),
            ]);
        }

        // Persist parents first in a short transaction, then compute genetics outside
        // so long Punnett work does not hold an open MySQL transaction.
        $parents = DB::transaction(function () use ($parentOne, $parentTwo) {
            $savedOne = $this->birds->persist($parentOne);
            $savedTwo = $this->birds->persist($parentTwo);

            if ((int) $savedOne->id === (int) $savedTwo->id) {
                throw ValidationException::withMessages([
                    'analysis' => ['Parent 1 and Parent 2 cannot be the same bird.'],
                ]);
            }

            $savedOne->load(['species', 'baseColor', 'visualMutations', 'splitGenes', 'grandparents']);
            $savedTwo->load(['species', 'baseColor', 'visualMutations', 'splitGenes', 'grandparents']);

            return [$savedOne, $savedTwo];
        });

        [$savedOne, $savedTwo] = $parents;
        $validation = $this->validator->validate($savedOne, $savedTwo);
        if (! ($validation['can_predict'] ?? false)) {
            throw ValidationException::withMessages([
                'analysis' => $this->blockingMessages($validation),
            ]);
        }

        $prediction = $this->predictor->predict($savedOne, $savedTwo, $validation);
        $compatibility = $validation['compatibility'] ?? [];
        $warnings = $validation['warnings'] ?? [];
        $warningSummary = collect($warnings)->pluck('message')->filter()->implode(' ');
        // Layer 1 — RBGIA outcome templates (one per theoretical genetic outcome, probabilities untouched).
        $outcomeTemplates = $this->visualizations->collectEggOutcomes($prediction, $validation);
        $presentation = $this->presenter->build($savedOne, $savedTwo, $validation, $prediction, $outcomeTemplates);

        // Layer 2 — GICA-driven clutch simulation. Needs the GICA score, so it runs after the first compose.
        $gicaScore = isset($presentation['gica']['score']) ? (int) round((float) $presentation['gica']['score']) : null;
        $clutchSimulation = $this->clutch->simulate($gicaScore, $outcomeTemplates);
        $speciesForEggs = $validation['parents']['parent_1']['species'] ?? $validation['parents']['parent_2']['species'] ?? null;
        $eggOutcomes = $this->clutch->applyToEggs($outcomeTemplates, $clutchSimulation, $speciesForEggs);
        $presentation = $this->presenter->build($savedOne, $savedTwo, $validation, $prediction, $eggOutcomes, $clutchSimulation);

        $storedPrediction = $this->compressor->compressPrediction($prediction);
        $storedEggs = $this->compressor->compressEggs($eggOutcomes);
        $storedPresentation = $this->compressor->compressPresentation($presentation);

        // Fresh connection before large JSON writes (avoids stale/closed sockets).
        DB::reconnect();

        $saved = DB::transaction(function () use (
            $savedOne,
            $savedTwo,
            $validation,
            $prediction,
            $storedPrediction,
            $compatibility,
            $warnings,
            $warningSummary,
            $storedEggs,
            $storedPresentation,
            $eggOutcomes,
            $presentation,
        ) {
            $pair = BirdPair::query()->create([
                'parent_1_bird_id' => $savedOne->id,
                'parent_2_bird_id' => $savedTwo->id,
                'pairing_type' => $compatibility['breeding_type'] ?? $compatibility['compatibility_status'] ?? null,
                'species_1_id' => $savedOne->species_id,
                'species_2_id' => $savedTwo->species_id,
                'pairing_status' => 'analyzed',
                'compatibility_status' => $compatibility['compatibility_status'] ?? null,
                'fertility_status' => $compatibility['fertility_status'] ?? null,
                'risk_level' => $compatibility['risk_level'] ?? null,
                'warning_summary' => $warningSummary !== '' ? $warningSummary : null,
            ]);

            $snapshot = [
                'parent_1' => $validation['parents']['parent_1'] ?? [],
                'parent_2' => $validation['parents']['parent_2'] ?? [],
                'parent_1_record_id' => $savedOne->id,
                'parent_2_record_id' => $savedTwo->id,
                'captured_at' => now()->toIso8601String(),
            ];

            $computation = ComputationResult::query()->create([
                'bird_pair_id' => $pair->id,
                'prediction_id' => null,
                'status' => ($prediction['provisional'] ?? false) ? 'provisional' : 'completed',
                'compatibility_result' => $compatibility,
                'genetic_compatibility' => [
                    'errors' => $validation['errors'] ?? [],
                    'warnings' => $warnings,
                    'information' => $validation['information'] ?? [],
                    'gica' => $storedPresentation['gica'] ?? null,
                    'method' => AgaporaGeneticEngine::METHOD,
                ],
                'overall_compatibility' => [
                    'can_predict' => $validation['can_predict'] ?? false,
                    'compatibility_status' => $compatibility['compatibility_status'] ?? null,
                    'label' => $compatibility['label'] ?? null,
                    'prediction_allowed' => $compatibility['prediction_allowed'] ?? false,
                    'species_compatibility' => $storedPresentation['species_compatibility'] ?? null,
                    'gica_label' => $storedPresentation['gica']['label'] ?? null,
                    'gica_score' => $storedPresentation['gica']['score'] ?? null,
                ],
                'fertility_result' => $compatibility['fertility_status'] ?? null,
                'risk_level' => $compatibility['risk_level'] ?? null,
                'warnings' => $warnings,
                'estimated_clutch_count' => $storedPresentation['clutch_simulation']['clutch_size']
                    ?? $storedPresentation['reproductive_forecast']['eggs_forecast']
                    ?? (count($eggOutcomes) ?: null),
                'offspring_probability' => $this->outcomesFor($storedPrediction, 'base_color'),
                'predicted_base_colors' => $this->outcomesFor($storedPrediction, 'base_color'),
                'predicted_visual_mutations' => $this->outcomesFor($storedPrediction, 'visual_mutation'),
                'predicted_split_genes' => $this->outcomesFor($storedPrediction, 'split_gene'),
                'predicted_phenotypes' => [],
                'predicted_appearance' => $prediction['message'] ?? null,
                'predicted_offspring_image' => null,
                'rbgia_data' => $storedPrediction,
                'genetic_calculation' => [
                    'message' => $prediction['message'] ?? null,
                    'provisional' => $prediction['provisional'] ?? false,
                    'confidence' => $prediction['confidence'] ?? null,
                    'algorithm' => $storedPresentation['algorithm'] ?? null,
                    'method' => AgaporaGeneticEngine::METHOD,
                ],
                'scientific_information' => [
                    'scientific_basis' => $compatibility['scientific_basis'] ?? null,
                    'scientific_source' => $compatibility['scientific_source'] ?? null,
                    'verification_status' => $compatibility['verification_status'] ?? null,
                    'notes' => $compatibility['notes'] ?? null,
                ],
                'parent_snapshot' => $snapshot,
                'offspring_visualizations' => [],
                'result_presentation' => null,
            ]);

            $savedPrediction = BreedingPrediction::query()->create([
                'bird_pair_id' => $pair->id,
                'computation_result_id' => $computation->id,
                'parent_1_bird_id' => $savedOne->bird_id,
                'parent_2_bird_id' => $savedTwo->bird_id,
                'status' => $computation->status,
                'parent_1' => $validation['parents']['parent_1'] ?? [],
                'parent_2' => $validation['parents']['parent_2'] ?? [],
                'validation' => [
                    'can_predict' => $validation['can_predict'] ?? false,
                    'errors' => $validation['errors'] ?? [],
                    'warnings' => $warnings,
                    'information' => $validation['information'] ?? [],
                    'compatibility' => $compatibility,
                    'parents' => $validation['parents'] ?? [],
                ],
                'prediction' => $storedPrediction,
            ]);

            $computation->update([
                'prediction_id' => $savedPrediction->id,
                'result_presentation' => $storedPresentation,
                'offspring_visualizations' => $storedEggs,
                'predicted_phenotypes' => array_map(fn (array $egg) => [
                    'egg_number' => $egg['egg_number'] ?? null,
                    'egg_outcome_id' => $egg['egg_outcome_id'] ?? null,
                    'outcome_key' => $egg['outcome_key'] ?? null,
                    'category' => $egg['category'] ?? null,
                    'name' => $egg['trait_name'] ?? null,
                    'sex' => $egg['sex'] ?? null,
                    'base_color' => $egg['base_color'] ?? null,
                    'alleles' => $egg['alleles'] ?? null,
                    'dark_factor' => $egg['dark_factor'] ?? null,
                    'genotype' => $egg['genotype'] ?? null,
                    'fraction' => $egg['probability']['fraction'] ?? null,
                    'phenotype' => $egg['phenotype'] ?? null,
                    'hatch_forecast_status' => $egg['hatch_forecast_status'] ?? null,
                ], $storedEggs),
            ]);

            return [
                'computation' => $computation->fresh(['birdPair', 'prediction']),
                'validation' => $validation,
                'prediction' => $prediction,
                'egg_outcomes' => $eggOutcomes,
                'presentation' => $presentation,
            ];
        });

        // Post-genetics AI assistants only — never modify RBGIA/GICA.
        $eggsWithDescriptions = $this->visualizations->attachVisualDescriptions($saved['egg_outcomes']);
        $eggsWithImages = $this->visualizations->attachImagesToStoredEggs(
            $eggsWithDescriptions,
            (int) $saved['computation']->id,
        );

        $promptPdf = $this->promptPdf->writeForComputation((int) $saved['computation']->id, $eggsWithImages);
        $eggsWithImages = array_map(function (array $egg) use ($promptPdf) {
            $egg['image_prompt_pdf'] = $promptPdf;

            return $egg;
        }, $eggsWithImages);

        $pair = $saved['computation']->birdPair;
        $parentOneModel = Bird::query()->with(['species', 'baseColor', 'visualMutations', 'splitGenes'])->find($pair?->parent_1_bird_id);
        $parentTwoModel = Bird::query()->with(['species', 'baseColor', 'visualMutations', 'splitGenes'])->find($pair?->parent_2_bird_id);

        $presentation = ($parentOneModel && $parentTwoModel)
            ? $this->presenter->build(
                $parentOneModel,
                $parentTwoModel,
                $saved['validation'],
                $saved['prediction'],
                $eggsWithImages,
                $saved['presentation']['clutch_simulation'] ?? null,
            )
            : $saved['presentation'];

        $presentation['image_prompt_pdf'] = $promptPdf;

        unset($presentation['ai_interpretation'], $presentation['computation_status']);
        $storedPresentation = $this->compressor->compressPresentation($presentation);
        $storedEggs = $this->compressor->compressEggs($eggsWithImages);

        $firstImage = collect($eggsWithImages)
            ->map(fn (array $egg) => $egg['image']['image_url'] ?? null)
            ->filter()
            ->first();

        DB::reconnect();
        $saved['computation']->update([
            'offspring_visualizations' => $storedEggs,
            'predicted_offspring_image' => $firstImage,
            'result_presentation' => $storedPresentation,
            'ai_interpretation' => null,
        ]);

        return $this->present($saved['computation']->fresh(['birdPair', 'prediction']));
    }

    public function present(ComputationResult $result): array
    {
        $result->loadMissing(['birdPair', 'prediction']);

        $presentation = $result->result_presentation ?? [];
        $eggs = $result->offspring_visualizations ?? [];

        if (is_array($presentation)) {
            unset($presentation['ai_interpretation'], $presentation['computation_status']);
            if (empty($presentation['egg_chick_examples']) && is_array($eggs)) {
                $presentation['egg_chick_examples'] = $eggs;
            }
        }

        return [
            'id' => $result->id,
            'bird_pair_id' => $result->bird_pair_id,
            'prediction_id' => $result->prediction_id,
            'status' => $result->status,
            'pairing' => [
                'id' => $result->birdPair?->id,
                'parent_1_bird_id' => $result->birdPair?->parent_1_bird_id,
                'parent_2_bird_id' => $result->birdPair?->parent_2_bird_id,
                'pairing_type' => $result->birdPair?->pairing_type,
                'pairing_status' => $result->birdPair?->pairing_status,
                'species_1_id' => $result->birdPair?->species_1_id,
                'species_2_id' => $result->birdPair?->species_2_id,
            ],
            'compatibility_result' => $result->compatibility_result,
            'genetic_compatibility' => $result->genetic_compatibility,
            'overall_compatibility' => $result->overall_compatibility,
            'fertility_result' => $result->fertility_result,
            'risk_level' => $result->risk_level,
            'warnings' => $result->warnings,
            'estimated_clutch_count' => $result->estimated_clutch_count,
            'offspring_probability' => $result->offspring_probability,
            'predicted_base_colors' => $result->predicted_base_colors,
            'predicted_visual_mutations' => $result->predicted_visual_mutations,
            'predicted_split_genes' => $result->predicted_split_genes,
            'predicted_phenotypes' => $result->predicted_phenotypes,
            'predicted_appearance' => $result->predicted_appearance,
            'predicted_offspring_image' => $result->predicted_offspring_image,
            'rbgia_data' => $result->rbgia_data,
            'genetic_calculation' => $result->genetic_calculation,
            'scientific_information' => $result->scientific_information,
            'parent_snapshot' => $result->parent_snapshot,
            'egg_outcomes' => $eggs,
            'offspring_visualizations' => $eggs,
            'result_presentation' => $presentation,
            'image_prompt_pdf' => $eggs[0]['image_prompt_pdf'] ?? ($presentation['image_prompt_pdf'] ?? null),
            'created_at' => $result->created_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hydrate(array $payload): Bird
    {
        $speciesId = $payload['species_id'] ?? null;
        $mutationIds = $this->idList($payload['visual_mutation_ids'] ?? []);
        $geneIds = $this->idList($payload['split_gene_ids'] ?? []);

        $bird = new Bird([
            'bird_id' => trim((string) ($payload['bird_id'] ?? '')),
            'species_id' => $speciesId,
            'sex' => $payload['sex'] ?? null,
            'age_months' => $payload['age_months'] ?? null,
            'base_color_id' => $payload['base_color_id'] ?? null,
        ]);

        if (! empty($payload['source_bird_id'])) {
            $bird->id = (int) $payload['source_bird_id'];
        }

        $bird->setRelation('species', $speciesId ? \App\Models\LovebirdSpecies::query()->find($speciesId) : null);
        $bird->setRelation('baseColor', ! empty($payload['base_color_id']) ? \App\Models\BaseColor::query()->find($payload['base_color_id']) : null);
        $bird->setRelation(
            'visualMutations',
            $mutationIds === [] ? collect() : \App\Models\VisualMutation::query()->whereIn('id', $mutationIds)->orderBy('sort_order')->get(),
        );
        $bird->setRelation(
            'splitGenes',
            $geneIds === [] ? collect() : \App\Models\SplitGene::query()->whereIn('id', $geneIds)->orderBy('sort_order')->get(),
        );
        $bird->setRelation('grandparents', collect());

        return $bird;
    }

    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private function snapshot(array $validation, array $prediction, Bird $parentOne, Bird $parentTwo): array
    {
        return [
            'parent_1' => $validation['parents']['parent_1'] ?? [],
            'parent_2' => $validation['parents']['parent_2'] ?? [],
            'parent_1_record_id' => $parentOne->id,
            'parent_2_record_id' => $parentTwo->id,
            'prediction' => $prediction,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @return list<array<string, mixed>>
     */
    private function outcomesFor(array $prediction, string $category): array
    {
        return array_values(array_filter(
            $prediction['outcomes'] ?? [],
            fn ($outcome) => is_array($outcome) && ($outcome['category'] ?? null) === $category,
        ));
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @return list<array<string, mixed>>
     */
    private function phenotypes(array $prediction): array
    {
        $rows = [];
        foreach ($prediction['outcomes'] ?? [] as $outcome) {
            if (! is_array($outcome)) {
                continue;
            }
            foreach ($outcome['results'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'category' => $outcome['category'] ?? null,
                    'name' => $outcome['name'] ?? null,
                    'sex' => $row['sex'] ?? null,
                    'genotype' => $row['genotype'] ?? null,
                    'fraction' => $row['fraction'] ?? null,
                    'phenotype' => $row['phenotype'] ?? $row['appearance'] ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $validation
     * @return list<string>
     */
    private function blockingMessages(array $validation): array
    {
        $messages = collect($validation['errors'] ?? [])
            ->pluck('message')
            ->filter()
            ->values()
            ->all();

        if ($messages !== []) {
            return $messages;
        }

        $blocked = collect($validation['warnings'] ?? [])
            ->pluck('message')
            ->filter()
            ->values()
            ->all();

        return $blocked !== []
            ? $blocked
            : ['Blocking breeding errors must be resolved before analysis can start.'];
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function idList(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $normalized[] = (int) $id;
        }

        return array_values(array_unique($normalized));
    }
}
