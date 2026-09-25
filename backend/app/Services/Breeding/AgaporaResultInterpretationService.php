<?php

namespace App\Services\Breeding;

use App\Services\Ai\OpenAiCompatibleChatClient;

/**
 * Explains an already-finished AGAPORA computation.
 * Must never invent genetics or override RBGIA / GICA values.
 */
class AgaporaResultInterpretationService
{
    public function __construct(
        private readonly OpenAiCompatibleChatClient $chat,
    ) {}

    /**
     * @param  array<string, mixed>  $presentation
     * @param  list<array<string, mixed>>  $eggOutcomes
     * @return array<string, mixed>
     */
    public function interpret(array $presentation, array $eggOutcomes): array
    {
        $enabled = (bool) config('services.agapora_ai.enabled', false);
        $auto = (bool) config('services.agapora_ai.auto', true);
        $apiKey = config('services.agapora_ai.api_key');

        if (! $enabled && ! ($auto && $apiKey)) {
            return $this->skeleton(
                $apiKey ? 'pending' : 'not_configured',
                $apiKey
                    ? 'AI interpretation is available when AGAPORA_AI_ENABLED=true (or auto mode with a configured key).'
                    : 'AI interpretation is not configured. Genetic results remain authoritative without AI explanation.',
            );
        }

        if (! $apiKey) {
            return $this->skeleton('not_configured', 'AGAPORA_AI_API_KEY / GROQ_API_KEY is missing.');
        }

        $payload = $this->buildPayload($presentation, $eggOutcomes);
        $result = $this->chat->chat($this->systemPrompt(), $this->userPrompt($payload));

        if (! ($result['ok'] ?? false)) {
            return [
                ...$this->skeleton($result['status'] ?? 'failed', $result['message'] ?? 'AI interpretation failed.'),
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'] ?? null,
            ];
        }

        $sections = $this->parseSections((string) $result['content']);

        return [
            'status' => 'generated',
            'provider' => $result['provider'] ?? config('services.agapora_ai.provider'),
            'model' => $result['model'] ?? config('services.agapora_ai.model'),
            'message' => 'AI interpretation of the already-computed AGAPORA result.',
            'disclaimer' => 'AI explanation only. AGAPORA RBGIA/GICA remains the authoritative genetic computation. The AI must not invent missing genetics.',
            'raw_text' => $result['content'],
            'sections' => $sections,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $presentation
     * @param  list<array<string, mixed>>  $eggOutcomes
     * @return array<string, mixed>
     */
    private function buildPayload(array $presentation, array $eggOutcomes): array
    {
        $eggs = array_map(function (array $egg) {
            return [
                'egg_number' => $egg['egg_number'] ?? null,
                'sex' => $egg['sex'] ?? null,
                'base_color' => $egg['base_color'] ?? null,
                'visual_mutations' => $egg['visual_mutations'] ?? [],
                'split_hidden_genes' => $egg['split_hidden_genes'] ?? [],
                'genotype' => $egg['genotype'] ?? null,
                'phenotype' => $egg['phenotype'] ?? null,
                'probability' => $egg['probability'] ?? null,
                'inherited_traits' => $egg['inherited_traits'] ?? [],
                'parent_1_inheritance' => $egg['parent_1_inheritance'] ?? null,
                'parent_2_inheritance' => $egg['parent_2_inheritance'] ?? null,
                'genetic_explanation' => $egg['genetic_explanation'] ?? null,
            ];
        }, $eggOutcomes);

        return [
            'species_compatibility' => $presentation['species_compatibility'] ?? null,
            'gica' => $presentation['gica'] ?? null,
            'probabilities' => $presentation['probabilities'] ?? null,
            'reproductive_forecast' => $presentation['reproductive_forecast'] ?? null,
            'inherited_traits' => $presentation['inherited_traits'] ?? null,
            'verification' => $presentation['verification'] ?? null,
            'egg_chick_examples' => $eggs,
            'warnings' => $presentation['species_compatibility']['warnings'] ?? [],
            'errors' => $presentation['species_compatibility']['errors'] ?? [],
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an assistant for AGAPORA, a deterministic lovebird genetics thesis system.
You receive ONLY already-computed AGAPORA results (RBGIA + GICA + stored datasets).
You MUST:
- Explain the supplied result in clear language for breeders, IT reviewers, and thesis panelists.
- Say "not available in the computed AGAPORA result" when a field is missing.
- Never invent alleles, mutations, probabilities, clutch sizes, hatch rates, or phenotypes.
- Never recalculate genetics or contradict the supplied numbers/labels.
- Treat Visual Mutations, Base Colors, and Split/Hidden Genes as separate categories.
Output markdown with exactly these headings:
## What the result means
## Why the pairing received this result
## Expected inheritance
## Expected offspring characteristics
## Important genetic considerations
## Breeding warnings
## Short technical explanation for verification
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function userPrompt(array $payload): string
    {
        return "Explain this AGAPORA computed breeding result. Do not invent genetics.\n\n"
            .json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, string>
     */
    private function parseSections(string $markdown): array
    {
        $titles = [
            'what_the_result_means' => 'What the result means',
            'why_the_pairing_received_this_result' => 'Why the pairing received this result',
            'expected_inheritance' => 'Expected inheritance',
            'expected_offspring_characteristics' => 'Expected offspring characteristics',
            'important_genetic_considerations' => 'Important genetic considerations',
            'breeding_warnings' => 'Breeding warnings',
            'short_technical_explanation' => 'Short technical explanation for verification',
        ];

        $sections = [];
        foreach ($titles as $key => $title) {
            $pattern = '/##\s*'.preg_quote($title, '/').'\s*(.*?)(?=\n##\s|$)/is';
            if (preg_match($pattern, $markdown, $matches)) {
                $sections[$key] = trim($matches[1]);
            } else {
                $sections[$key] = '';
            }
        }

        if (collect($sections)->filter()->isEmpty()) {
            $sections['what_the_result_means'] = trim($markdown);
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    private function skeleton(string $status, string $message): array
    {
        return [
            'status' => $status,
            'provider' => config('services.agapora_ai.provider'),
            'model' => config('services.agapora_ai.model'),
            'message' => $message,
            'disclaimer' => 'AI explanation only. AGAPORA RBGIA/GICA remains the authoritative genetic computation.',
            'raw_text' => null,
            'sections' => [],
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
