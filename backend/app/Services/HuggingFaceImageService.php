<?php

namespace App\Services;

use App\Models\ChickOutcomeImage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Visualizes an already-calculated AGAPORA egg/chick outcome via Hugging Face.
 * Never calculates genetics, probabilities, or inheritance.
 *
 * Token is read only from config('services.huggingface.token') / HF_TOKEN.
 */
class HuggingFaceImageService
{
    /** Bump when buildPrompt() wording changes so cached images from the old prompt are not reused. */
    public const PROMPT_VERSION = 'v2-full-body-scientific';

    /**
     * @param  array{
     *     egg_number?: int|string|null,
     *     species?: mixed,
     *     sex?: mixed,
     *     base_color?: mixed,
     *     visual_mutations?: mixed,
     *     split_genes?: mixed,
     *     split_hidden_genes?: mixed,
     *     scientific_name?: mixed,
     *     force?: bool
     * }  $outcome
     * @return array{
     *     success: bool,
     *     egg_number: int|string|null,
     *     image_url: ?string,
     *     image_path: ?string,
     *     signature: string,
     *     prompt: string,
     *     provider: string,
     *     model: string,
     *     cached: bool,
     *     status: string,
     *     error: ?string,
     *     genetic_result: array<string, mixed>
     * }
     */
    public function generateChickImage(array $outcome): array
    {
        $genetic = $this->normalizeOutcome($outcome);
        $prompt = $this->buildPrompt($genetic);
        $force = (bool) ($outcome['force'] ?? false);

        $token = trim((string) config('services.huggingface.token', ''));
        $model = (string) config('services.huggingface.model', 'black-forest-labs/FLUX.1-dev');
        $provider = (string) config('services.huggingface.provider', 'auto');

        // Signature = genetic outcome + model, so switching HF_IMAGE_MODEL (e.g. FLUX → Krea 2) regenerates.
        $signature = $this->signature($genetic, $model);

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
                    'provider' => 'huggingface',
                    'model' => $model,
                    'cached' => true,
                    'status' => 'generated',
                    'error' => null,
                    'genetic_result' => $genetic,
                ];
            }
        }

        if ($token === '' || $token === 'YOUR_NEW_HUGGING_FACE_TOKEN') {
            return $this->failure(
                $genetic,
                $prompt,
                $signature,
                $model,
                'Hugging Face is not configured. Set HF_TOKEN in the Laravel backend .env file.',
                'pending_configuration',
            );
        }

        try {
            $binary = $this->requestImage($prompt, $token, $model, $provider);
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
                    'provider' => 'huggingface',
                    'model' => $model,
                    'hf_provider' => $provider,
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
                'provider' => 'huggingface',
                'model' => $model,
                'cached' => false,
                'status' => 'generated',
                'error' => null,
                'genetic_result' => $genetic,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Hugging Face chick image generation failed', [
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
                'failed',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $genetic
     */
    public function buildPrompt(array $genetic): string
    {
        $species = $genetic['species'];
        $scientific = $genetic['scientific_name'] ?? null;
        $speciesLine = $scientific && $scientific !== $species
            ? $species.' ('.$scientific.')'
            : $species;
        $visual = $this->joinList($genetic['visual_mutations']);
        $split = $this->joinList($genetic['split_genes']);

        return implode("\n", [
            'Generate a highly realistic, scientifically accurate lovebird image based strictly on the following genetic and identification data:',
            '',
            'Input Data',
            '',
            '- Species: '.$speciesLine,
            '- Sex: '.$genetic['sex'],
            '- Base Color: '.$genetic['base_color'],
            '- Visual Mutation: '.$visual,
            '- Split/Hidden Genes: '.$split,
            '',
            'Generation Instructions',
            '',
            'Create a realistic full-body image of the specified lovebird species. The bird\'s appearance must reflect the provided species, sex, base color, and visual mutations as accurately as possible.',
            '',
            'Use the Split/Hidden Genes only as genetic information that may influence possible inherited traits. Do not visually display a hidden or split gene unless it produces a visible phenotype according to the applicable inheritance rules.',
            '',
            'The generated bird should have:',
            '',
            '- Correct species-specific body structure and proportions',
            '- Accurate natural head, face, eye, beak, wing, chest, back, tail, and leg characteristics',
            '- Correct base coloration',
            '- Visible visual mutations applied to the appropriate body areas',
            '- Realistic feather texture and patterns',
            '- Natural bird anatomy',
            '- Accurate eye and beak appearance for the specified mutation',
            '- Clean, detailed feathers',
            '- Natural lighting',
            '- Neutral, uncluttered background',
            '- Full body visible from head to tail',
            '- One bird only',
            '- No cage, accessories, text, labels, watermark, or extra birds',
            '',
            'Important Genetic Rule',
            '',
            'Do not invent mutations or colors that are not supported by the provided data. If a mutation or genetic combination cannot be visually determined from the available information, preserve the natural appearance rather than guessing.',
            '',
            'The image is a visual representation of the predicted lovebird characteristics, not proof of the bird\'s actual genotype.',
            '',
            'Return only the generated lovebird image.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $genetic
     */
    public function signature(array $genetic, string $model = ''): string
    {
        $payload = [
            'prompt_version' => self::PROMPT_VERSION,
            'model' => mb_strtolower(trim($model)),
            'species' => mb_strtolower(trim((string) $genetic['species'])),
            'sex' => mb_strtolower(trim((string) $genetic['sex'])),
            'base_color' => mb_strtolower(trim((string) $genetic['base_color'])),
            'visual_mutations' => array_map(
                fn ($n) => mb_strtolower(trim((string) $n)),
                $genetic['visual_mutations'],
            ),
            'split_genes' => array_map(
                fn ($n) => mb_strtolower(trim((string) $n)),
                $genetic['split_genes'],
            ),
        ];
        sort($payload['visual_mutations']);
        sort($payload['split_genes']);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array{
     *     egg_number: int|string|null,
     *     species: string,
     *     scientific_name: ?string,
     *     sex: string,
     *     base_color: string,
     *     visual_mutations: list<string>,
     *     split_genes: list<string>
     * }
     */
    public function normalizeOutcome(array $outcome): array
    {
        $speciesRaw = $outcome['species'] ?? null;
        $species = $this->plain($speciesRaw);
        $scientific = null;
        if (is_array($speciesRaw)) {
            $scientific = $this->plain($speciesRaw['scientific_name'] ?? null) ?: null;
            $species = $this->plain($speciesRaw['common_name'] ?? $speciesRaw['name'] ?? $speciesRaw['scientific_name'] ?? null);
        }
        if (! empty($outcome['scientific_name'])) {
            $scientific = $this->plain($outcome['scientific_name']) ?: $scientific;
        }

        $visual = $this->nameList($outcome['visual_mutations'] ?? []);
        $split = $this->nameList(
            $outcome['split_genes']
            ?? $outcome['split_hidden_genes']
            ?? []
        );

        return [
            'egg_number' => $outcome['egg_number'] ?? null,
            'species' => $species !== '' ? $species : 'lovebird',
            'scientific_name' => $scientific,
            'sex' => $this->plain($outcome['sex'] ?? $outcome['sex_label'] ?? null) ?: 'Not recorded',
            'base_color' => $this->plain($outcome['base_color'] ?? null) ?: 'Not recorded',
            'visual_mutations' => $visual,
            'split_genes' => $split,
        ];
    }

    /**
     * Provider preference when HF_IMAGE_PROVIDER=auto.
     *
     * @var list<string>
     */
    private const PROVIDER_PRIORITY = ['fal-ai', 'replicate', 'wavespeed', 'together', 'nebius', 'hf-inference'];

    private function requestImage(string $prompt, string $token, string $model, string $provider): string
    {
        $base = rtrim((string) config('services.huggingface.endpoint', 'https://router.huggingface.co'), '/');
        $timeout = (int) config('services.huggingface.timeout', 180);
        $provider = strtolower(trim($provider)) ?: 'auto';

        $mapping = $this->providerMapping($model, $token);
        $attempts = $this->buildAttempts($base, $model, $prompt, $provider, $mapping);

        if ($attempts === []) {
            throw new \RuntimeException(
                $provider === 'auto'
                    ? "No live Inference Provider serves {$model} for text-to-image. If the model is gated, accept its license on huggingface.co with the same account as HF_TOKEN."
                    : "Provider '{$provider}' does not serve {$model}. Available: ".(implode(', ', array_keys($mapping)) ?: 'none').'.'
            );
        }

        $lastError = 'Hugging Face returned no image.';

        foreach ($attempts as $attempt) {
            $request = $this->http()
                ->withToken($token)
                ->timeout($timeout)
                ->accept('*/*')
                ->withHeaders(array_merge(['Content-Type' => 'application/json'], $attempt['headers'] ?? []));

            $response = $request->post($attempt['url'], $attempt['body']);

            if ($response->status() === 401) {
                throw new \RuntimeException('Hugging Face rejected the API token. Check HF_TOKEN permissions for Inference Providers.');
            }

            if ($response->status() === 403) {
                $detail = strtolower((string) ($this->extractApiError($response->body()) ?? ''));
                if (str_contains($detail, 'permission') || str_contains($detail, 'inference providers')) {
                    throw new \RuntimeException('HF_TOKEN lacks the "Make calls to Inference Providers" permission. Edit the token at huggingface.co/settings/tokens and enable it.');
                }

                throw new \RuntimeException("Access denied for {$model}. If the model is gated, accept its license on huggingface.co with the same account as HF_TOKEN.");
            }

            if ($response->status() === 429) {
                throw new \RuntimeException('Hugging Face rate limit or credit limit reached. Please wait and try again.');
            }

            if (! $response->successful()) {
                $lastError = '['.$attempt['provider'].'] '.($this->extractApiError($response->body()) ?: ('HTTP '.$response->status()));

                continue;
            }

            $binary = $this->extractBinary($response->body(), (string) $response->header('Content-Type'), $timeout);
            if ($binary !== null) {
                return $binary;
            }

            $lastError = '['.$attempt['provider'].'] Response did not contain image bytes.';
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * Resolve HF model → provider model IDs via the Hub API (cached).
     *
     * @return array<string, array{providerId: string, status: string, task: string}>
     */
    private function providerMapping(string $model, string $token): array
    {
        $cacheKey = 'hf_provider_mapping:'.sha1($model);
        $cached = cache()->get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $mapping = (function () use ($model, $token): array {
            try {
                $response = $this->http()
                    ->withToken($token)
                    ->timeout(20)
                    ->acceptJson()
                    ->get('https://huggingface.co/api/models/'.$model, [
                        'expand[]' => 'inferenceProviderMapping',
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $raw = $response->json('inferenceProviderMapping');
                if (! is_array($raw)) {
                    return [];
                }

                $mapping = [];
                foreach ($raw as $name => $entry) {
                    if (! is_array($entry) || ($entry['task'] ?? null) !== 'text-to-image') {
                        continue;
                    }
                    if (($entry['status'] ?? 'live') !== 'live' || empty($entry['providerId'])) {
                        continue;
                    }
                    $mapping[strtolower((string) $name)] = [
                        'providerId' => (string) $entry['providerId'],
                        'status' => (string) ($entry['status'] ?? 'live'),
                        'task' => 'text-to-image',
                    ];
                }

                return $mapping;
            } catch (\Throwable) {
                return [];
            }
        })();

        // Only cache successful lookups so a transient network/SSL failure is retried next time.
        if ($mapping !== []) {
            cache()->put($cacheKey, $mapping, now()->addHour());
        }

        return $mapping;
    }

    /**
     * @param  array<string, array{providerId: string, status: string, task: string}>  $mapping
     * @return list<array{provider: string, url: string, body: array<string, mixed>, headers?: array<string, string>}>
     */
    private function buildAttempts(string $base, string $model, string $prompt, string $provider, array $mapping): array
    {
        $order = [];
        if ($provider === 'auto') {
            foreach (self::PROVIDER_PRIORITY as $name) {
                if (isset($mapping[$name])) {
                    $order[] = $name;
                }
            }
            foreach (array_keys($mapping) as $name) {
                if (! in_array($name, $order, true)) {
                    $order[] = $name;
                }
            }
            if ($order === []) {
                // Mapping unavailable (offline / private): try hf-inference directly.
                $order[] = 'hf-inference';
            }
        } else {
            $alias = $provider === 'fal' ? 'fal-ai' : $provider;
            if (isset($mapping[$alias]) || $alias === 'hf-inference' || $mapping === []) {
                $order[] = $alias;
            }
        }

        $attempts = [];
        foreach ($order as $name) {
            $providerId = $mapping[$name]['providerId'] ?? $model;

            $attempts[] = match ($name) {
                'fal-ai' => [
                    'provider' => $name,
                    'url' => $base.'/fal-ai/'.$providerId,
                    'body' => [
                        'prompt' => $prompt,
                        'sync_mode' => true,
                        'num_images' => 1,
                    ],
                ],
                'replicate' => [
                    'provider' => $name,
                    'url' => $base.'/replicate/v1/models/'.$providerId.'/predictions',
                    'body' => [
                        'input' => ['prompt' => $prompt],
                    ],
                    'headers' => ['Prefer' => 'wait'],
                ],
                'wavespeed' => [
                    'provider' => $name,
                    'url' => $base.'/wavespeed/api/v3/'.$providerId,
                    'body' => [
                        'prompt' => $prompt,
                        'enable_sync_mode' => true,
                    ],
                ],
                'hf-inference' => [
                    'provider' => $name,
                    'url' => $base.'/hf-inference/models/'.$model,
                    'body' => [
                        'inputs' => $prompt,
                    ],
                ],
                default => [
                    'provider' => $name,
                    'url' => $base.'/'.$name.'/v1/images/generations',
                    'body' => [
                        'model' => $providerId,
                        'prompt' => $prompt,
                        'n' => 1,
                        'response_format' => 'b64_json',
                    ],
                ],
            };
        }

        return $attempts;
    }

    private function extractBinary(string $body, string $contentType, int $timeout = 120): ?string
    {
        $contentType = strtolower($contentType);
        if (str_contains($contentType, 'image/') || str_contains($contentType, 'octet-stream')) {
            return $body !== '' ? $body : null;
        }

        $json = json_decode($body, true);
        if (! is_array($json)) {
            // Some providers return raw PNG/JPEG without a correct content-type.
            if (str_starts_with($body, "\x89PNG") || str_starts_with($body, "\xFF\xD8\xFF")) {
                return $body;
            }

            return null;
        }

        $candidates = [
            $json['image'] ?? null,                       // hf-inference JSON
            $json['b64_json'] ?? null,
            $json['data'][0]['b64_json'] ?? null,         // OpenAI-compatible
            $json['data'][0]['url'] ?? null,
            $json['images'][0]['url'] ?? null,            // fal-ai: images[{url}] (data URI or https)
            $json['images'][0] ?? null,                   // plain string variant
            $json['output'][0] ?? null,                   // replicate: output[url]
            $json['output'] ?? null,                      // replicate: output url string
            $json['data']['outputs'][0] ?? null,          // wavespeed
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

    /**
     * Accepts a data URI, https URL, or raw base64 and returns image bytes.
     */
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

    /**
     * HTTP client with an explicit CA bundle so HTTPS verification works even when
     * php.ini has no curl.cainfo (common on Windows → "cURL error 60").
     */
    private function http(): PendingRequest
    {
        $bundle = $this->caBundlePath();

        return $bundle ? Http::withOptions(['verify' => $bundle]) : Http::withOptions([]);
    }

    private function caBundlePath(): ?string
    {
        $candidates = [
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

    private function extractApiError(string $body): ?string
    {
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return Str::limit(trim(strip_tags($body)), 240, '…') ?: null;
        }

        foreach (['error', 'message', 'detail'] as $key) {
            if (! empty($json[$key]) && is_string($json[$key])) {
                return $json[$key];
            }
        }

        return null;
    }

    private function storeImage(string $binary, int|string|null $eggNumber): string
    {
        $safeEgg = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($eggNumber ?? 'x')) ?: 'x';
        $owner = auth()->id() ?? 'account';
        $path = 'chicks/'.$owner.'/chick_'.$safeEgg.'_'.Str::lower(Str::random(12)).'.png';
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

        if (! $row) {
            return null;
        }

        if (! Storage::disk('public')->exists($row->image_path)) {
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
            'provider' => 'huggingface',
            'model' => $model,
            'cached' => false,
            'status' => $status,
            'error' => $error,
            'genetic_result' => $genetic,
        ];
    }

    private function publicErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $message = preg_replace('/hf_[A-Za-z0-9._-]+/', '[redacted]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;

        if (str_contains(strtolower($message), 'timed out') || str_contains(strtolower($message), 'timeout')) {
            return 'Hugging Face request timed out. Please try again.';
        }

        return Str::limit($message, 280, '…');
    }

    /**
     * @return list<string>
     */
    private function nameList(mixed $items): array
    {
        if (is_string($items)) {
            $parts = array_map('trim', explode(',', $items));

            return array_values(array_filter($parts, fn ($p) => $p !== ''));
        }

        if (! is_array($items)) {
            return [];
        }

        $names = [];
        foreach ($items as $item) {
            $name = is_array($item)
                ? $this->plain($item['name'] ?? $item['label'] ?? null)
                : $this->plain($item);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>  $names
     */
    private function joinList(array $names): string
    {
        return $names === [] ? 'None' : implode(', ', $names);
    }

    private function plain(mixed $value): string
    {
        if (is_array($value)) {
            return $this->plain($value['name'] ?? $value['common_name'] ?? $value['label'] ?? null);
        }

        return trim((string) $value);
    }
}
