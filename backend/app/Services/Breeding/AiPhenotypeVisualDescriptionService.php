<?php

namespace App\Services\Breeding;

use App\Services\Ai\OpenAiCompatibleChatClient;

/**
 * Derives visualization-oriented descriptions from already-computed phenotype text.
 * Never invents mutations or overrides AGAPORA genetics.
 */
class AiPhenotypeVisualDescriptionService
{
    public function __construct(
        private readonly OpenAiCompatibleChatClient $chat,
    ) {}

    /**
     * @param  array<string, mixed>  $egg
     * @return array<string, mixed>
     */
    public function describe(array $egg): array
    {
        $enabled = (bool) config('services.agapora_ai.visual_description_enabled', true);
        $apiKey = config('services.agapora_ai.api_key');

        if (! $enabled || ! $apiKey) {
            return [
                'status' => $apiKey ? 'skipped' : 'not_configured',
                'source' => 'ai_visual_interpretation',
                'disclaimer' => 'AI visual interpretation — not a new genetic calculation.',
                'pattern' => null,
                'markings' => null,
                'eyes' => null,
                'head' => null,
                'body' => null,
                'wings' => null,
                'rump' => null,
                'tail' => null,
                'other' => null,
                'summary' => null,
                'message' => $apiKey
                    ? 'Visual description skipped by configuration.'
                    : 'AI visual description unavailable — configure AGAPORA_AI_API_KEY / GROQ_API_KEY.',
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $reference = [
            'species' => is_array($egg['species'] ?? null)
                ? ($egg['species']['common_name'] ?? $egg['species']['scientific_name'] ?? null)
                : ($egg['species'] ?? null),
            'scientific_name' => is_array($egg['species'] ?? null) ? ($egg['species']['scientific_name'] ?? null) : null,
            'sex' => $egg['sex'] ?? null,
            'base_color' => $egg['base_color'] ?? null,
            'visual_mutations' => $egg['visual_mutations'] ?? [],
            'split_hidden_genes' => $egg['split_hidden_genes'] ?? [],
            'genotype' => $egg['genotype'] ?? null,
            'phenotype' => $egg['phenotype'] ?? null,
            'genetic_explanation' => $egg['genetic_explanation'] ?? null,
            'stored_visual_fields' => [
                'pattern' => $egg['pattern'] ?? null,
                'markings' => $egg['markings'] ?? null,
                'eyes' => $egg['eyes'] ?? null,
                'head' => $egg['head'] ?? null,
                'body' => $egg['body'] ?? null,
                'wings' => $egg['wings'] ?? null,
                'rump' => $egg['rump'] ?? null,
                'tail' => $egg['tail'] ?? null,
                'other' => $egg['other_visual_characteristics'] ?? null,
            ],
            'verification_status' => $egg['verification_status'] ?? null,
            'scientific_source' => $egg['scientific_source'] ?? null,
        ];

        $system = <<<'PROMPT'
You help visualize an already-computed AGAPORA lovebird phenotype.
Return ONLY valid JSON with keys:
pattern, markings, eyes, head, body, wings, rump, tail, other, summary
Rules:
- Use only the supplied computed phenotype, genotype labels, species, and reference notes.
- If a detail cannot be inferred, set the value to "uncertain / not specified from computed phenotype".
- Do not invent mutations, colors, sexes, or alleles that are not supported by the payload.
- Split/hidden genes must not create extra visible markings unless the phenotype text already implies them.
- This is visual interpretation only, not genetics.
PROMPT;

        $user = "Create a visualization-oriented description for this AGAPORA egg/chick result:\n"
            .json_encode($reference, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $result = $this->chat->chat($system, $user);
        if (! ($result['ok'] ?? false)) {
            return [
                'status' => $result['status'] ?? 'failed',
                'source' => 'ai_visual_interpretation',
                'disclaimer' => 'AI visual interpretation — not a new genetic calculation.',
                'pattern' => null,
                'markings' => null,
                'eyes' => null,
                'head' => null,
                'body' => null,
                'wings' => null,
                'rump' => null,
                'tail' => null,
                'other' => null,
                'summary' => null,
                'message' => $result['message'] ?? 'AI visual description failed.',
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'] ?? null,
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $parsed = $this->decodeJsonObject((string) $result['content']);

        return [
            'status' => 'generated',
            'source' => 'ai_visual_interpretation',
            'disclaimer' => 'AI visual interpretation based on the computed phenotype — not a new genetic calculation.',
            'pattern' => $parsed['pattern'] ?? 'uncertain / not specified from computed phenotype',
            'markings' => $parsed['markings'] ?? 'uncertain / not specified from computed phenotype',
            'eyes' => $parsed['eyes'] ?? 'uncertain / not specified from computed phenotype',
            'head' => $parsed['head'] ?? 'uncertain / not specified from computed phenotype',
            'body' => $parsed['body'] ?? 'uncertain / not specified from computed phenotype',
            'wings' => $parsed['wings'] ?? 'uncertain / not specified from computed phenotype',
            'rump' => $parsed['rump'] ?? 'uncertain / not specified from computed phenotype',
            'tail' => $parsed['tail'] ?? 'uncertain / not specified from computed phenotype',
            'other' => $parsed['other'] ?? null,
            'summary' => $parsed['summary'] ?? null,
            'message' => 'AI visual description derived from the completed AGAPORA phenotype result.',
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $content): array
    {
        $trimmed = trim($content);
        if (preg_match('/\{.*\}/s', $trimmed, $matches)) {
            $trimmed = $matches[0];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }
}
