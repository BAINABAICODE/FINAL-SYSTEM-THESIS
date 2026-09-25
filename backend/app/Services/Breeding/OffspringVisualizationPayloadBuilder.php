<?php

namespace App\Services\Breeding;

/**
 * Builds AI visualization payloads from already-stored AGAPORA egg outcomes.
 * Never calculates genetics.
 */
class OffspringVisualizationPayloadBuilder
{
    public function __construct(
        private readonly OffspringImagePromptComposer $prompts,
    ) {}

    /**
     * @param  array<string, mixed>  $egg
     * @return array<string, mixed>
     */
    public function build(array $egg): array
    {
        $composed = is_array($egg['image_prompt'] ?? null) ? $egg['image_prompt'] : $this->prompts->compose($egg);
        $collected = $composed['collected'] ?? $this->prompts->collect($egg);
        $prompt = (string) ($composed['prompt'] ?? $this->prompts->templatePrompt($collected));
        $short = (string) ($composed['prompt_short'] ?? $this->prompts->shortPrompt($collected));

        return [
            'source_of_truth' => 'AGAPORA-RBGIA',
            'ai_role' => 'visual_representation_only',
            'ai_must_not' => [
                'calculate_genetics',
                'determine_genotype',
                'determine_phenotype',
                'determine_inheritance',
                'determine_probability',
                'select_mutations',
                'randomly_add_mutations',
                'randomly_change_colors',
                'override_calculated_traits',
                'modify_rbgia_results',
                'generate_a_different_genetic_outcome',
                'generate_random_eggs',
                'draw_split_or_hidden_genes',
            ],
            'egg_number' => $egg['egg_number'] ?? null,
            'egg_outcome_id' => $egg['egg_outcome_id'] ?? null,
            'outcome_key' => $egg['outcome_key'] ?? null,
            'collected_traits' => [
                'species' => $collected['species'] ?? null,
                'base_color' => $collected['base_color'] ?? null,
                'visual_mutations' => $collected['visual_mutations'] ?? [],
                'split_hidden_genes' => $collected['split_hidden_genes'] ?? [],
                'sex' => $collected['sex'] ?? null,
            ],
            'species' => $collected['species'] ?? null,
            'scientific_name' => $collected['scientific_name'] ?? null,
            'sex' => $egg['sex'] ?? null,
            'sex_label' => $collected['sex'] ?? null,
            'base_color' => $collected['base_color'] ?? null,
            'visual_mutations' => $collected['visual_mutations'] ?? [],
            'split_hidden_genes' => $collected['split_hidden_genes'] ?? [],
            'genotype' => $egg['genotype'] ?? null,
            'phenotype' => $egg['phenotype'] ?? null,
            'probability' => $egg['probability'] ?? null,
            'prompt' => $prompt,
            'image_prompt_short' => $short,
            'prompt_source' => $composed['source'] ?? 'template',
            'negative_prompt' => 'Do not invent extra mutations. Do not draw split or hidden genes. No text, labels, charts, DNA, or extra birds.',
            'disclaimer' => 'AI Visual Representation — Based on Computed Phenotype. This visualization is an AI-generated representation and is not an exact biological guarantee.',
            'title' => 'Predicted Offspring Visualization',
        ];
    }
}
