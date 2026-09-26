<?php

namespace App\Services;

use App\Models\ChickOutcomeImage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Visualizes an already-calculated AGAPORA egg/chick outcome via OpenRouter.
 * Never calculates genetics, probabilities, or inheritance.
 *
 * Key is read only from config('services.openrouter.api_key') / OPENROUTER_API_KEY.
 */
class OpenRouterImageService
{
    public const EXHAUSTED_CACHE_KEY = 'openrouter_image_exhausted';

    public function __construct(
        private readonly HuggingFaceImageService $prompts,
    ) {}

    public function isConfigured(): bool
    {
        $key = $this->apiKey();

        return $key !== '' && $key !== 'YOUR_OPENROUTER_API_KEY';
    }

    public function isExhausted(): bool
    {
        return (bool) cache()->get(self::EXHAUSTED_CACHE_KEY, false);
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    public function generateChickImage(array $outcome): array
    {
        $genetic = $this->prompts->normalizeOutcome($outcome);
        $prompt = $this->prompts->buildPrompt($genetic);
        $force = (bool) ($outcome['force'] ?? false);
        $model = (string) config('services.openrouter.image_model', 'google/gemini-3-pro-image');
        $signature = $this->prompts->signature($genetic, 'openrouter:'.$model);

        if (! $force) {
            $cached = $this->findCached($signature);
            if ($cached !== null) {
                return [
                    'success' => true,
                    'egg_number' => $genetic['egg_number'],
                    'image_url' => $cached['image_url'],
                    'image_path' => $cached['image_path'],
                    'signature' => $signature,
                    'prompt' => $prompt,
                    'provider' => 'openrouter',
                    'model' => $model,
                    'cached' => true,
                    'status' => 'generated',
                    'error' => null,
                    'genetic_result' => $genetic,
                ];
            }
        }

        if (! $this->isConfigured()) {
            return $this->failure(
                $genetic,
                $prompt,
                $signature,
                $model,
                'OpenRouter is not configured. Set OPENROUTER_API_KEY in the Laravel backend .env file.',
                'pending_configuration',
            );
        }

        try {
            $binary = $this->requestImage($prompt, $model);
            $path = $this->storeImage($binary, $genetic['egg_number']);
            $url = Storage::disk('public')->url($path);

            ChickOutcomeImage::query()->updateOrCreate(
                ['signature' => $signature, 'user_id' => auth()->id()],
                [
                    'egg_number' => is_numeric($genetic['egg_number']) ? (int) $genetic['egg_number'] : null,
                    'species' => $genetic['species'],
                    'sex' => $genetic['sex'],
                    'base_color' => $genetic['base_color'],
                    'visual_mutations' => $genetic['visual_mutations'],
                    'split_genes' => $genetic['split_genes'],
                    'prompt' => $prompt,
                    'image_path' => $path,
                    'image_url' => $url,
                    'provider' => 'openrouter',
                    'model' => $model,
                    'hf_provider' => null,
                    'status' => 'generated',
                    'error' => null,
                    'generated_at' => now(),
                ],
            );

            return [
                'success' => true,
                'egg_number' => $genetic['egg_number'],
                'image_url' => $url,
                'image_path' => $path,
                'signature' => $signature,
                'prompt' => $prompt,
                'provider' => 'openrouter',
                'model' => $model,
                'cached' => false,
                'status' => 'generated',
                'error' => null,
                'genetic_result' => $genetic,
            ];
        } catch (\Throwable $exception) {
            if ($this->isCreditError($exception)) {
                $this->markExhausted();
            }

            Log::warning('OpenRouter chick image generation failed', [
                'egg_number' => $genetic['egg_number'],
                'signature' => $signature,
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);

            return $this->failure(
                $genetic,
                $prompt,
                $signature,
                $model,
                $this->publicErrorMessage($exception),
                $this->isCreditError($exception) ? 'exhausted' : 'failed',
            );
        }
    }

    private function requestImage(string $prompt, string $model): string
    {
        $base = rtrim((string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
        $timeout = (int) config('services.openrouter.timeout', 180);
        $aspect = (string) config('services.openrouter.aspect_ratio', '4:3');

        $response = $this->http()
            ->timeout($timeout)
            ->acceptJson()
            ->post($base.'/images', [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'aspect_ratio' => $aspect,
                'output_format' => 'png',
            ]);

        if ($response->status() === 401) {
            throw new \RuntimeException('OpenRouter rejected the API key. Check OPENROUTER_API_KEY.');
        }

        if ($response->status() === 402 || $this->bodyLooksLikeCreditError($response->body(), $response->status())) {
            throw new \RuntimeException('OpenRouter credits or quota are exhausted.');
        }

        if ($response->status() === 429) {
            throw new \RuntimeException('OpenRouter rate limit reached. Please wait and try again.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractApiError($response->body()) ?: ('OpenRouter HTTP '.$response->status()));
        }

        $binary = $this->extractBinary($response->body(), $timeout);
        if ($binary === null) {
            throw new \RuntimeException('OpenRouter returned no image data.');
        }

        return $binary;
    }

    private function extractBinary(string $body, int $timeout): ?string
    {
        $json = json_decode($body, true);
        if (! is_array($json)) {
            if (str_starts_with($body, "\x89PNG") || str_starts_with($body, "\xFF\xD8\xFF")) {
                return $body;
            }

            return null;
        }

        $candidates = [
            $json['data'][0]['b64_json'] ?? null,
            $json['data'][0]['url'] ?? null,
            $json['choices'][0]['message']['images'][0]['image_url']['url'] ?? null,
            $json['choices'][0]['message']['content'][0]['image_url']['url'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $binary = $this->resolveImageValue($value, $timeout);
            if ($binary !== null) {
                return $binary;
            }
        }

        return null;
    }

    private function resolveImageValue(string $value, int $timeout): ?string
    {
        if (str_starts_with($value, 'data:image')) {
            $parts = explode(',', $value, 2);
            $decoded = base64_decode($parts[1] ?? '', true);

            return ($decoded !== false && $decoded !== '') ? $decoded : null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $download = $this->http()->timeout($timeout)->get($value);
            if ($download->successful() && $download->body() !== '') {
                return $download->body();
            }

            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded !== false && (str_starts_with($decoded, "\x89PNG") || str_starts_with($decoded, "\xFF\xD8\xFF") || strlen($decoded) > 1024)) {
            return $decoded;
        }

        return null;
    }

    private function http(): PendingRequest
    {
        $bundle = $this->caBundlePath();
        $request = $bundle ? Http::withOptions(['verify' => $bundle]) : Http::withOptions([]);

        return $request
            ->withToken($this->apiKey())
            ->withHeaders([
                'HTTP-Referer' => (string) config('services.openrouter.http_referer', 'http://localhost'),
                'X-Title' => (string) config('services.openrouter.app_title', 'AGAPORA'),
                'Content-Type' => 'application/json',
            ]);
    }

    private function caBundlePath(): ?string
    {
        $candidates = [
            (string) config('services.openrouter.ca_bundle', ''),
            (string) config('services.huggingface.ca_bundle', ''),
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            storage_path('certs/cacert.pem'),
        ];

        foreach ($candidates as $path) {
            $path = trim($path, " \t\"'");
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function storeImage(string $binary, int|string|null $eggNumber): string
    {
        $safeEgg = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($eggNumber ?? 'x')) ?: 'x';
        $ext = str_starts_with($binary, "\xFF\xD8\xFF") ? 'jpg' : 'png';
        $owner = auth()->id() ?? 'account';
        $path = 'chicks/'.$owner.'/chick_'.$safeEgg.'_'.Str::lower(Str::random(12)).'.'.$ext;
        if (! Storage::disk('public')->put($path, $binary)) {
            throw new \RuntimeException('Failed to save the generated chick image to storage.');
        }

        return $path;
    }

    /**
     * @return array{image_url: string, image_path: string}|null
     */
    private function findCached(string $signature): ?array
    {
        $row = ChickOutcomeImage::query()
            ->where('signature', $signature)
            ->where('status', 'generated')
            ->whereNotNull('image_path')
            ->first();

        if (! $row || ! Storage::disk('public')->exists($row->image_path)) {
            return null;
        }

        return [
            'image_url' => $row->image_url ?: Storage::disk('public')->url($row->image_path),
            'image_path' => $row->image_path,
        ];
    }

    /**
     * @param  array<string, mixed>  $genetic
     * @return array<string, mixed>
     */
    private function failure(
        array $genetic,
        string $prompt,
        string $signature,
        string $model,
        string $error,
        string $status,
    ): array {
        return [
            'success' => false,
            'egg_number' => $genetic['egg_number'],
            'image_url' => null,
            'image_path' => null,
            'signature' => $signature,
            'prompt' => $prompt,
            'provider' => 'openrouter',
            'model' => $model,
            'cached' => false,
            'status' => $status,
            'error' => $error,
            'genetic_result' => $genetic,
        ];
    }

    private function markExhausted(): void
    {
        $ttl = (int) config('services.offspring_image.openrouter_exhausted_ttl', 21600);
        cache()->put(self::EXHAUSTED_CACHE_KEY, true, now()->addSeconds(max(60, $ttl)));
    }

    private function isCreditError(\Throwable $exception): bool
    {
        return $this->bodyLooksLikeCreditError($exception->getMessage(), 0);
    }

    private function bodyLooksLikeCreditError(string $body, int $status): bool
    {
        if ($status === 402) {
            return true;
        }

        $haystack = strtolower($body);

        return str_contains($haystack, 'insufficient credit')
            || str_contains($haystack, 'credits')
            || str_contains($haystack, 'quota')
            || str_contains($haystack, 'payment required')
            || str_contains($haystack, 'afford')
            || str_contains($haystack, 'exhausted');
    }

    private function extractApiError(string $body): ?string
    {
        $json = json_decode($body, true);
        if (is_array($json)) {
            $nested = $json['error']['message'] ?? $json['error'] ?? $json['message'] ?? null;
            if (is_string($nested) && $nested !== '') {
                return $nested;
            }
        }

        return Str::limit(trim(strip_tags($body)), 240, '…') ?: null;
    }

    private function publicErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $message = preg_replace('/sk-or-v1-[A-Za-z0-9]+/', '[redacted]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;

        if (str_contains(strtolower($message), 'timed out') || str_contains(strtolower($message), 'timeout')) {
            return 'OpenRouter request timed out. Please try again.';
        }

        return Str::limit($message, 280, '…');
    }

    private function apiKey(): string
    {
        return trim((string) config('services.openrouter.api_key', ''));
    }
}
