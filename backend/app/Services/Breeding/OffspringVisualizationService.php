<?php

namespace App\Services\Breeding;

use App\Services\ChickImageService;

/**
 * Collects AGAPORA egg outcomes first, then composes an image prompt and generates a picture.
 * AI never recalculates genetics and never regenerates stored images.
 */
class OffspringVisualizationService
{
    public function __construct(
        private readonly PhenotypeTranslator $translator,
        private readonly OffspringImagePromptComposer $prompts,
        private readonly OffspringVisualizationPayloadBuilder $payloads,
        private readonly AiPhenotypeVisualDescriptionService $visualDescriptions,
        private readonly AiOffspringImageGenerator $images,
        private readonly ChickImageService $chickImages,
    ) {}

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $validation
     * @return list<array<string, mixed>>
     */
    public function collectEggOutcomes(array $prediction, array $validation): array
    {
        $eggs = $this->translator->translate($prediction, $validation);

        return array_map(function (array $egg) {
            $composed = $this->prompts->compose($egg);
            $egg['collected_traits'] = $composed['collected'];
            $egg['image_prompt'] = $composed;
            $payload = $this->payloads->build($egg);

            return [
                ...$egg,
                'inherited_traits' => array_values(array_unique(array_filter([
                    $egg['trait_name'] ?? null,
                    ...($egg['visual_mutations'] ?? []),
                    ...($egg['split_hidden_genes'] ?? []),
                ]))),
                'title' => 'Predicted Offspring Visualization',
                'disclaimer' => 'AI Visual Representation — Based on Computed Phenotype. This visualization is an AI-generated representation and is not an exact biological guarantee.',
                'ai_visual_description' => null,
                'ai_interpretation' => null,
                'visualization_payload' => $payload,
                'composed_image_prompt' => $composed['prompt'],
                'image_prompt_short' => $composed['prompt_short'],
                'image_prompt_source' => $composed['source'],
                'image' => [
                    'status' => 'pending',
                    'image_url' => null,
                    'image_path' => null,
                    'provider' => null,
                    'message' => 'Pending — egg fields collected. Image starts after all eggs are stored.',
                    'updated_at' => now()->toIso8601String(),
                ],
            ];
        }, $eggs);
    }

    /**
     * @param  list<array<string, mixed>>  $eggs
     * @return list<array<string, mixed>>
     */
    public function attachVisualDescriptions(array $eggs): array
    {
        return array_map(function (array $egg) {
            if ($this->isNonLivingEgg($egg)) {
                return $egg;
            }
            if (($egg['ai_visual_description']['status'] ?? null) === 'generated') {
                $egg['visualization_payload'] = $this->payloads->build($egg);

                return $egg;
            }

            $description = $this->visualDescriptions->describe($egg);
            $egg['ai_visual_description'] = $description;
            if (! isset($egg['image_prompt']['prompt'])) {
                $egg['image_prompt'] = $this->prompts->compose($egg);
                $egg['composed_image_prompt'] = $egg['image_prompt']['prompt'];
                $egg['image_prompt_short'] = $egg['image_prompt']['prompt_short'];
            }
            $egg['visualization_payload'] = $this->payloads->build($egg);

            return $egg;
        }, $eggs);
    }

    /**
     * Automatically generate one image per stored egg after collection completes.
     * Skips eggs that already have a generated image reference.
     *
     * @param  list<array<string, mixed>>  $eggs
     * @return list<array<string, mixed>>
     */
    public function attachImagesToStoredEggs(array $eggs, int $computationResultId): array
    {
        $provider = strtolower((string) config('services.offspring_image.provider', 'openrouter'));
        $enabled = (bool) config('services.offspring_image.enabled', false);
        $auto = (bool) config('services.offspring_image.auto', false);
        $providerReady = $this->chickImages->isReady();
        $automatic = $enabled && $auto && $providerReady;
        $delayMs = (int) config('services.offspring_image.request_delay_ms', 0);

        $index = 0;

        return array_map(function (array $egg) use ($computationResultId, $automatic, $providerReady, $provider, &$index, $delayMs) {
            if ($this->isNonLivingEgg($egg)) {
                return $egg;
            }
            if (! isset($egg['image_prompt']['prompt'])) {
                $composed = $this->prompts->compose($egg);
                $egg['image_prompt'] = $composed;
                $egg['collected_traits'] = $composed['collected'];
                $egg['composed_image_prompt'] = $composed['prompt'];
                $egg['image_prompt_short'] = $composed['prompt_short'];
            }

            $payload = $egg['visualization_payload'] ?? $this->payloads->build($egg);
            $egg['visualization_payload'] = $payload;

            $egg['hf_image_prompt'] = $this->chickImages->buildPrompt([
                'egg_number' => $egg['egg_number'] ?? null,
                'species' => $egg['collected_traits']['species'] ?? $egg['species'] ?? 'lovebird',
                'scientific_name' => $egg['collected_traits']['scientific_name'] ?? null,
                'sex' => $egg['collected_traits']['sex'] ?? $egg['sex_label'] ?? $egg['sex'] ?? 'Not recorded',
                'base_color' => $egg['collected_traits']['base_color'] ?? $egg['base_color'] ?? 'Not recorded',
                'visual_mutations' => $egg['collected_traits']['visual_mutations'] ?? $egg['visual_mutations'] ?? [],
                'split_genes' => $egg['collected_traits']['split_hidden_genes'] ?? $egg['split_hidden_genes'] ?? [],
            ]);

            if (($egg['image']['status'] ?? null) === 'generated' && ! empty($egg['image']['image_url'])) {
                return $egg;
            }

            if (! $automatic) {
                $egg['image'] = [
                    'status' => $providerReady ? 'pending' : 'failed',
                    'image_url' => null,
                    'image_path' => null,
                    'provider' => null,
                    'message' => $providerReady
                        ? 'Pending — use Generate Chick Image on each egg card, or enable OFFSPRING_IMAGE_AUTO=true.'
                        : 'Failed — set OPENROUTER_API_KEY in backend/.env for image generation. Genetic egg results remain unchanged.',
                    'updated_at' => now()->toIso8601String(),
                ];

                return $egg;
            }

            if (! $providerReady) {
                $egg['image'] = [
                    'status' => 'failed',
                    'image_url' => null,
                    'image_path' => null,
                    'provider' => $provider,
                    'message' => 'Failed — image provider credentials are missing. Genetic egg results remain unchanged.',
                    'updated_at' => now()->toIso8601String(),
                ];

                return $egg;
            }

            if ($index > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }
            $index++;

            $egg['image'] = [
                'status' => 'generating',
                'image_url' => null,
                'image_path' => null,
                'provider' => $provider,
                'message' => 'Generating — sending the composed egg prompt to the image API.',
                'updated_at' => now()->toIso8601String(),
            ];

            $image = $this->images->generate($payload, $computationResultId);
            $status = $image['status'] ?? 'failed';
            if ($status === 'generated') {
                $image['status'] = 'generated';
            } elseif ($status === 'pending_configuration') {
                $image['status'] = 'pending';
            } else {
                $image['status'] = 'failed';
            }
            $image['updated_at'] = now()->toIso8601String();
            $egg['image'] = $image;

            return $egg;
        }, $eggs);
    }

    /**
     * @param  array<string, mixed>  $egg
     */
    private function isNonLivingEgg(array $egg): bool
    {
        return isset($egg['egg_status']) && $egg['egg_status'] !== ClutchSimulationService::STATUS_LIVING_CHICK;
    }
}
