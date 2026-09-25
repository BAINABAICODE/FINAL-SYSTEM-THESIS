<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComputationResult;
use App\Services\ChickImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Secure proxy: React → Laravel → OpenRouter (Hugging Face fallback).
 * Never returns API keys. Never recalculates genetics.
 */
class ChickImageController extends Controller
{
    public function __construct(
        private readonly ChickImageService $images,
    ) {}

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'egg_number' => ['required', 'integer', 'min:1'],
            'species' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'string', 'max:64'],
            'base_color' => ['required', 'string', 'max:128'],
            'visual_mutations' => ['nullable', 'array'],
            'visual_mutations.*' => ['string', 'max:128'],
            'split_genes' => ['nullable', 'array'],
            'split_genes.*' => ['string', 'max:128'],
            'scientific_name' => ['nullable', 'string', 'max:255'],
            'computation_result_id' => ['nullable', 'integer', 'exists:computation_results,id'],
            'outcome_key' => ['nullable', 'string', 'max:191'],
            'force' => ['nullable', 'boolean'],
        ]);

        $result = $this->images->generateChickImage([
            'egg_number' => $validated['egg_number'],
            'species' => $validated['species'],
            'scientific_name' => $validated['scientific_name'] ?? null,
            'sex' => $validated['sex'],
            'base_color' => $validated['base_color'],
            'visual_mutations' => $validated['visual_mutations'] ?? [],
            'split_genes' => $validated['split_genes'] ?? [],
            'force' => (bool) ($validated['force'] ?? false),
        ]);

        if (! empty($validated['computation_result_id'])) {
            $this->persistToComputation(
                (int) $validated['computation_result_id'],
                (int) $validated['egg_number'],
                $validated['outcome_key'] ?? null,
                $result,
            );
        }

        $status = $result['success'] ? 200 : (
            ($result['status'] ?? '') === 'pending_configuration' ? 503 : 502
        );

        return response()->json([
            'success' => (bool) $result['success'],
            'egg_number' => $result['egg_number'],
            'image_url' => $result['image_url'],
            'image_path' => $result['image_path'],
            'cached' => (bool) ($result['cached'] ?? false),
            'status' => $result['status'],
            'message' => $result['success']
                ? (($result['cached'] ?? false)
                    ? 'Reused an existing image for this genetic outcome.'
                    : 'Chick image generated successfully.')
                : ($result['error'] ?? 'Image generation failed.'),
            'genetic_result' => $result['genetic_result'],
            // Prompt is safe to show; token is never included.
            'prompt' => $result['prompt'] ?? null,
            'provider' => $result['provider'] ?? 'openrouter',
            'model' => $result['model'] ?? null,
        ], $status);
    }

    public function generateAll(Request $request, ComputationResult $computationResult): JsonResponse
    {
        $force = (bool) $request->boolean('force', false);
        $eggs = is_array($computationResult->offspring_visualizations)
            ? $computationResult->offspring_visualizations
            : [];

        $generated = [];
        $failures = 0;

        foreach ($eggs as $index => $egg) {
            if (! is_array($egg)) {
                continue;
            }

            if (isset($egg['egg_status']) && $egg['egg_status'] !== 'living_chick') {
                // Simulated non-living eggs carry no genetic outcome — nothing to visualize.
                continue;
            }

            if (! $force
                && ($egg['image']['status'] ?? null) === 'generated'
                && ! empty($egg['image']['image_url'])
            ) {
                $generated[] = [
                    'egg_number' => $egg['egg_number'] ?? ($index + 1),
                    'success' => true,
                    'cached' => true,
                    'image_url' => $egg['image']['image_url'],
                    'status' => 'generated',
                ];
                continue;
            }

            $payload = $this->eggToPayload($egg, $index + 1);
            $payload['force'] = $force;
            $result = $this->images->generateChickImage($payload);
            $eggs[$index] = $this->mergeImageOntoEgg($egg, $result);

            $generated[] = [
                'egg_number' => $result['egg_number'],
                'success' => (bool) $result['success'],
                'cached' => (bool) ($result['cached'] ?? false),
                'image_url' => $result['image_url'],
                'status' => $result['status'],
                'message' => $result['error'] ?? null,
            ];

            if (! $result['success']) {
                $failures++;
            }
        }

        $firstUrl = collect($eggs)
            ->map(fn ($egg) => is_array($egg) ? ($egg['image']['image_url'] ?? null) : null)
            ->filter()
            ->first();

        $presentation = $computationResult->result_presentation ?? [];
        if (is_array($presentation)) {
            $presentation['egg_chick_examples'] = $eggs;
        }

        $computationResult->update([
            'offspring_visualizations' => $eggs,
            'predicted_offspring_image' => $firstUrl,
            'result_presentation' => $presentation,
        ]);

        return response()->json([
            'success' => $failures === 0,
            'generated_count' => count(array_filter($generated, fn ($row) => $row['success'])),
            'failed_count' => $failures,
            'results' => $generated,
            'egg_outcomes' => $eggs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function persistToComputation(
        int $computationResultId,
        int $eggNumber,
        ?string $outcomeKey,
        array $result,
    ): void {
        try {
            $computation = ComputationResult::query()->find($computationResultId);
            if (! $computation) {
                return;
            }

            $eggs = is_array($computation->offspring_visualizations)
                ? $computation->offspring_visualizations
                : [];

            $updated = false;
            foreach ($eggs as $index => $egg) {
                if (! is_array($egg)) {
                    continue;
                }

                $matchesKey = $outcomeKey && (($egg['outcome_key'] ?? null) === $outcomeKey);
                $matchesNumber = (int) ($egg['egg_number'] ?? 0) === $eggNumber;
                if (! $matchesKey && ! $matchesNumber) {
                    continue;
                }

                $eggs[$index] = $this->mergeImageOntoEgg($egg, $result);
                $updated = true;
                break;
            }

            if (! $updated) {
                return;
            }

            $presentation = $computation->result_presentation ?? [];
            if (is_array($presentation)) {
                $presentation['egg_chick_examples'] = $eggs;
            }

            $firstUrl = collect($eggs)
                ->map(fn ($egg) => is_array($egg) ? ($egg['image']['image_url'] ?? null) : null)
                ->filter()
                ->first();

            $computation->update([
                'offspring_visualizations' => $eggs,
                'predicted_offspring_image' => $firstUrl ?: $computation->predicted_offspring_image,
                'result_presentation' => $presentation,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to persist chick image onto computation result', [
                'computation_result_id' => $computationResultId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $egg
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function mergeImageOntoEgg(array $egg, array $result): array
    {
        $egg['composed_image_prompt'] = $result['prompt'] ?? ($egg['composed_image_prompt'] ?? null);
        $egg['image'] = [
            'status' => $result['success'] ? 'generated' : ($result['status'] ?? 'failed'),
            'image_url' => $result['image_url'] ?? null,
            'image_path' => $result['image_path'] ?? null,
            'provider' => $result['provider'] ?? 'openrouter',
            'model' => $result['model'] ?? null,
            'exact_prompt_sent' => $result['prompt'] ?? null,
            'generation_error' => $result['error'] ?? null,
            'cached' => (bool) ($result['cached'] ?? false),
            'signature' => $result['signature'] ?? null,
            'message' => $result['success']
                ? 'Image generated from the calculated egg/chick outcome.'
                : ($result['error'] ?? 'Image generation failed.'),
            'updated_at' => now()->toIso8601String(),
        ];

        return $egg;
    }

    /**
     * @param  array<string, mixed>  $egg
     * @return array<string, mixed>
     */
    private function eggToPayload(array $egg, int $fallbackNumber): array
    {
        $traits = is_array($egg['collected_traits'] ?? null) ? $egg['collected_traits'] : [];

        return [
            'egg_number' => $egg['egg_number'] ?? $fallbackNumber,
            'species' => $traits['species'] ?? $egg['species'] ?? 'lovebird',
            'scientific_name' => $traits['scientific_name'] ?? $egg['scientific_name'] ?? null,
            'sex' => $traits['sex'] ?? $egg['sex_label'] ?? $egg['sex'] ?? 'Not recorded',
            'base_color' => $traits['base_color'] ?? $egg['base_color'] ?? 'Not recorded',
            'visual_mutations' => $traits['visual_mutations'] ?? $egg['visual_mutations'] ?? [],
            'split_genes' => $traits['split_hidden_genes'] ?? $egg['split_hidden_genes'] ?? $egg['split_genes'] ?? [],
        ];
    }
}
