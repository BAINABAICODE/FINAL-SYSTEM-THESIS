<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Replaceable OpenAI-compatible chat client (Groq / OpenRouter / OpenAI / custom base URL).
 * Used only for post-computation interpretation and visual description — never for genetics.
 */
class OpenAiCompatibleChatClient
{
    /**
     * @param  array{provider?: string, api_key?: ?string, base_url?: ?string, model?: ?string, timeout?: int}  $overrides
     */
    public function chat(string $systemPrompt, string $userPrompt, array $overrides = []): array
    {
        $provider = $overrides['provider'] ?? config('services.agapora_ai.provider', 'groq');
        $apiKey = $overrides['api_key'] ?? config('services.agapora_ai.api_key');
        $baseUrl = rtrim((string) ($overrides['base_url'] ?? config('services.agapora_ai.base_url')), '/');
        $model = $overrides['model'] ?? config('services.agapora_ai.model');
        $timeout = (int) ($overrides['timeout'] ?? config('services.agapora_ai.timeout', 90));

        if (! $apiKey || ! $baseUrl || ! $model) {
            return [
                'ok' => false,
                'status' => 'pending_configuration',
                'content' => null,
                'provider' => $provider,
                'model' => $model,
                'message' => 'AI text provider is not configured. Set AGAPORA_AI_API_KEY (or GROQ_API_KEY) and related env vars.',
            ];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->acceptJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('AI chat API returned HTTP '.$response->status());
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
            if ($content === '') {
                throw new \RuntimeException('AI chat API returned an empty response.');
            }

            return [
                'ok' => true,
                'status' => 'generated',
                'content' => $content,
                'provider' => $provider,
                'model' => $model,
                'message' => 'AI response generated from the supplied AGAPORA computed payload only.',
            ];
        } catch (\Throwable $exception) {
            Log::warning('AGAPORA AI chat request failed', [
                'provider' => $provider,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 'failed',
                'content' => null,
                'provider' => $provider,
                'model' => $model,
                'message' => 'AI text request failed. AGAPORA genetic results were not changed.',
            ];
        }
    }
}
