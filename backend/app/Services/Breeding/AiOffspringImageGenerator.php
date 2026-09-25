<?php

namespace App\Services\Breeding;

use App\Services\ChickImageService;
use Illuminate\Support\Facades\Log;

/**
 * Sends the composed egg/chick image prompt to the configured image API.
 * Never recalculates genetics; only renders the supplied prompt.
 */
class AiOffspringImageGenerator
{
    public function __construct(
        private readonly ChickImageService $images,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generate(array $payload, int $computationResultId): array
    {
        try {
            $traits = is_array($payload['collected_traits'] ?? null) ? $payload['collected_traits'] : [];
            $result = $this->images->generateChickImage([
                'egg_number' => $payload['egg_number'] ?? null,
                'species' => $traits['species'] ?? $payload['species'] ?? null,
                'scientific_name' => $traits['scientific_name'] ?? $payload['scientific_name'] ?? null,
                'sex' => $traits['sex'] ?? $payload['sex_label'] ?? $payload['sex'] ?? null,
                'base_color' => $traits['base_color'] ?? $payload['base_color'] ?? null,
                'visual_mutations' => $traits['visual_mutations'] ?? $payload['visual_mutations'] ?? [],
                'split_genes' => $traits['split_hidden_genes'] ?? $payload['split_hidden_genes'] ?? [],
            ]);

            $provider = (string) ($result['provider'] ?? 'openrouter');

            return [
                'status' => $result['success'] ? 'generated' : ($result['status'] ?? 'failed'),
                'image_url' => $result['image_url'],
                'image_path' => $result['image_path'],
                'provider' => $provider,
                'model' => $result['model'] ?? null,
                'exact_prompt_sent' => $result['prompt'] ?? null,
                'generation_error' => $result['error'] ?? null,
                'cached' => (bool) ($result['cached'] ?? false),
                'message' => $result['success']
                    ? 'Image generated via '.$provider.' from the composed egg/chick prompt.'
                    : ($result['error'] ?? 'Image generation failed.'),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Offspring image generation failed', [
                'outcome_key' => $payload['outcome_key'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'image_url' => null,
                'image_path' => null,
                'provider' => (string) config('services.offspring_image.provider', 'openrouter'),
                'model' => (string) config('services.openrouter.image_model', 'google/gemini-3-pro-image'),
                'exact_prompt_sent' => $this->preferredPrompt($payload),
                'generation_error' => $exception->getMessage(),
                'message' => 'The AI image request failed. AGAPORA genetic results were not changed. '.$exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function preferredPrompt(array $payload): string
    {
        return trim((string) (
            $payload['prompt']
            ?? $payload['prompt_descriptive']
            ?? $payload['image_prompt_short']
            ?? ''
        ));
    }
}
