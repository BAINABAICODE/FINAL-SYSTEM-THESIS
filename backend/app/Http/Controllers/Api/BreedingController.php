<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AnalyzeBreedingRequest;
use App\Models\BaseColor;
use App\Models\Bird;
use App\Models\BirdGrandparent;
use App\Models\BreedingPrediction;
use App\Models\LovebirdSpecies;
use App\Models\SpeciesBreedingCompatibility;
use App\Models\SplitGene;
use App\Models\VisualMutation;
use App\Services\Breeding\BreedingAnalysisService;
use App\Services\Breeding\BreedingPairValidator;
use App\Services\Breeding\RbgiaPredictor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class BreedingController extends Controller
{
    public function __construct(
        private readonly BreedingPairValidator $validator,
        private readonly RbgiaPredictor $predictor,
        private readonly BreedingAnalysisService $analysis,
    ) {}

    public function compatibilities(Request $request): JsonResponse
    {
        $query = SpeciesBreedingCompatibility::query()->orderBy('species_low_id')->orderBy('species_high_id');

        if ($request->filled('species_1_id') && $request->filled('species_2_id')) {
            [$low, $high] = SpeciesBreedingCompatibility::pairKey(
                $request->integer('species_1_id'),
                $request->integer('species_2_id'),
            );
            $query->where('species_low_id', $low)->where('species_high_id', $high);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function validatePair(Request $request): JsonResponse
    {
        $parents = $this->parents($request);

        return response()->json([
            'data' => $this->validator->validate($parents[0], $parents[1]),
        ]);
    }

    public function analyzePair(AnalyzeBreedingRequest $request): JsonResponse
    {
        $result = $this->analysis->analyze(
            $request->validated('parent_1'),
            $request->validated('parent_2'),
        );

        return response()->json(['data' => $result], 201);
    }

    public function predict(Request $request): JsonResponse
    {
        $parents = $this->parents($request);
        $validation = $this->validator->validate($parents[0], $parents[1]);
        $prediction = ($validation['can_predict'] ?? false)
            ? $this->predictor->predict($parents[0], $parents[1], $validation)
            : [
                'status' => 'blocked',
                'message' => 'RBGIA did not run because the pairing still has a blocking validation error.',
                'outcomes' => [],
            ];

        $saved = null;
        if ($validation['can_predict'] ?? false) {
            $saved = BreedingPrediction::query()->create([
                'parent_1_bird_id' => $parents[0]->bird_id,
                'parent_2_bird_id' => $parents[1]->bird_id,
                'status' => ($prediction['provisional'] ?? false) ? 'provisional' : 'completed',
                'parent_1' => $validation['parents']['parent_1'] ?? [],
                'parent_2' => $validation['parents']['parent_2'] ?? [],
                'validation' => $validation,
                'prediction' => $prediction,
            ]);
        }

        return response()->json([
            'data' => [
                'validation' => $validation,
                'prediction' => $prediction,
                'saved_prediction' => $saved ? [
                    'id' => $saved->id,
                    'status' => $saved->status,
                    'created_at' => $saved->created_at,
                ] : null,
            ],
        ]);
    }

    /**
     * @return array{0: Bird, 1: Bird}
     */
    private function parents(Request $request): array
    {
        if ($request->has('parent_1') || $request->has('parent_2')) {
            $request->validate([
                'parent_1' => ['required', 'array'],
                'parent_2' => ['required', 'array'],
                'parent_1.bird_id' => ['nullable', 'string', 'max:80'],
                'parent_2.bird_id' => ['nullable', 'string', 'max:80'],
                'parent_1.species_id' => ['nullable', 'integer', 'exists:lovebird_species,id'],
                'parent_2.species_id' => ['nullable', 'integer', 'exists:lovebird_species,id'],
                'parent_1.sex' => ['nullable', 'string'],
                'parent_2.sex' => ['nullable', 'string'],
                'parent_1.age_months' => ['nullable', 'integer', 'min:0', 'max:600'],
                'parent_2.age_months' => ['nullable', 'integer', 'min:0', 'max:600'],
                'parent_1.base_color_id' => ['nullable', 'integer', 'exists:base_colors,id'],
                'parent_2.base_color_id' => ['nullable', 'integer', 'exists:base_colors,id'],
                'parent_1.visual_mutation_ids' => ['nullable', 'array'],
                'parent_2.visual_mutation_ids' => ['nullable', 'array'],
                'parent_1.split_gene_ids' => ['nullable', 'array'],
                'parent_2.split_gene_ids' => ['nullable', 'array'],
                'parent_1.grandparents' => ['nullable', 'array'],
                'parent_2.grandparents' => ['nullable', 'array'],
            ]);

            return [
                $this->hydrateParent($request->input('parent_1', [])),
                $this->hydrateParent($request->input('parent_2', [])),
            ];
        }

        $validated = $request->validate([
            'parent_1_id' => ['required', 'integer', Rule::exists('birds', 'id')->where(fn ($query) => $query->where('user_id', $request->user()?->id))],
            'parent_2_id' => ['required', 'integer', Rule::exists('birds', 'id')->where(fn ($query) => $query->where('user_id', $request->user()?->id))],
        ]);

        $with = [
            'species',
            'baseColor',
            'visualMutations',
            'splitGenes',
            'grandparents',
        ];

        return [
            Bird::query()->with($with)->findOrFail($validated['parent_1_id']),
            Bird::query()->with($with)->findOrFail($validated['parent_2_id']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hydrateParent(array $payload): Bird
    {
        $speciesId = $payload['species_id'] ?? null;
        $mutationIds = array_values(array_filter(array_map('intval', $payload['visual_mutation_ids'] ?? [])));
        $geneIds = array_values(array_filter(array_map('intval', $payload['split_gene_ids'] ?? [])));

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

        $bird->setRelation('species', $speciesId ? LovebirdSpecies::query()->find($speciesId) : null);
        $bird->setRelation(
            'baseColor',
            ! empty($payload['base_color_id']) ? BaseColor::query()->find($payload['base_color_id']) : null,
        );
        $bird->setRelation(
            'visualMutations',
            $mutationIds === []
                ? collect()
                : VisualMutation::query()->whereIn('id', $mutationIds)->orderBy('sort_order')->get(),
        );
        $bird->setRelation(
            'splitGenes',
            $geneIds === []
                ? collect()
                : SplitGene::query()->whereIn('id', $geneIds)->orderBy('sort_order')->get(),
        );
        $bird->setRelation('grandparents', $this->hydrateGrandparents($payload['grandparents'] ?? []));

        return $bird;
    }

    /**
     * @return Collection<int, BirdGrandparent>
     */
    private function hydrateGrandparents(mixed $grandparents)
    {
        if (! is_array($grandparents)) {
            return collect();
        }

        $records = collect();

        foreach (Bird::GRANDPARENT_ROLES as $role) {
            $payload = $grandparents[$role] ?? null;
            if (! is_array($payload)) {
                continue;
            }

            $hasData = collect([
                $payload['species_id'] ?? null,
                $payload['base_color_id'] ?? null,
            ])->contains(fn ($value) => $value !== null && $value !== '')
                || ! empty($payload['visual_mutation_ids'])
                || ! empty($payload['split_gene_id']);

            if (! $hasData) {
                continue;
            }

            $record = new BirdGrandparent([
                'role' => $role,
                'species_id' => $payload['species_id'] ?? null,
                'base_color_id' => $payload['base_color_id'] ?? null,
                'split_gene_id' => $payload['split_gene_id'] ?? null,
            ]);
            $record->setRelation(
                'species',
                ! empty($payload['species_id']) ? LovebirdSpecies::query()->find($payload['species_id']) : null,
            );
            $records->push($record);
        }

        return $records;
    }
}
