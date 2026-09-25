<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Chooses the chick image backend. OpenRouter is primary; Hugging Face is fallback.
 * Never calculates genetics.
 */
class ChickImageService
{
    public function __construct(
        private readonly OpenRouterImageService $openRouter,
        private readonly HuggingFaceImageService $huggingFace,
    ) {}

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    public function generateChickImage(array $outcome): array
    {
        $primary = strtolower((string) config('services.offspring_image.provider', 'openrouter'));
        $fallback = strtolower((string) config('services.offspring_image.fallback_provider', 'huggingface'));

        if (in_array($primary, ['openrouter', 'or'], true)) {
            if ($this->openRouter->isConfigured() && ! $this->openRouter->isExhausted()) {
                $result = $this->openRouter->generateChickImage($outcome);
                if ($result['success'] || ! $this->shouldFallbackToHuggingFace($result, $fallback)) {
                    return $result;
                }

                Log::info('OpenRouter chick image unavailable; using Hugging Face fallback', [
                    'egg_number' => $result['egg_number'] ?? null,
                    'status' => $result['status'] ?? null,
                ]);
            } elseif ($this->openRouter->isExhausted() && $fallback === 'huggingface') {
                Log::info('OpenRouter credits previously exhausted; using Hugging Face fallback');
            } elseif (! $this->openRouter->isConfigured() && $fallback !== 'huggingface') {
                return $this->openRouter->generateChickImage($outcome);
            }

            if ($fallback === 'huggingface') {
                return $this->huggingFace->generateChickImage($outcome);
            }

            return $this->openRouter->generateChickImage($outcome);
        }

        return $this->huggingFace->generateChickImage($outcome);
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    public function buildPrompt(array $outcome): string
    {
        return $this->huggingFace->buildPrompt($this->huggingFace->normalizeOutcome($outcome));
    }

    public function isReady(): bool
    {
        $primary = strtolower((string) config('services.offspring_image.provider', 'openrouter'));
        $fallback = strtolower((string) config('services.offspring_image.fallback_provider', 'huggingface'));

        if (in_array($primary, ['openrouter', 'or'], true) && $this->openRouter->isConfigured()) {
            return true;
        }

        if ($primary === 'huggingface' || $fallback === 'huggingface') {
            $token = trim((string) config('services.huggingface.token', ''));

            return $token !== '' && $token !== 'YOUR_NEW_HUGGING_FACE_TOKEN';
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function shouldFallbackToHuggingFace(array $result, string $fallback): bool
    {
        if ($fallback !== 'huggingface') {
            return false;
        }

        $status = (string) ($result['status'] ?? '');

        return $status === 'exhausted';
    }
}
