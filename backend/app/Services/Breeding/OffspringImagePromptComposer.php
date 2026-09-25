<?php

namespace App\Services\Breeding;

/**
 * Collects one egg/chick outcome and writes the AGAPORA image-prompt instruction text.
 * Never calculates genetics. Split/hidden genes are listed but not drawn.
 *
 * Follows AGAPORA Image Prompt Instructions:
 * DRAW: species + base color + visual mutations
 * DO NOT DRAW: split and hidden genes
 */
class OffspringImagePromptComposer
{
    /**
     * @param  array<string, mixed>  $egg
     * @return array{
     *     source: string,
     *     collected: array<string, mixed>,
     *     prompt: string,
     *     prompt_short: string,
     *     prompt_descriptive: string,
     *     instruction_text: string
     * }
     */
    public function compose(array $egg): array
    {
        $collected = $this->collect($egg);

        return [
            'source' => 'agapora_outcome_fields',
            'collected' => $collected,
            'prompt' => $this->templatePrompt($collected),
            'prompt_short' => $this->shortPrompt($collected),
            'prompt_descriptive' => $this->descriptivePrompt($collected),
            'instruction_text' => $this->instructionText(),
        ];
    }

    /**
     * @param  array<string, mixed>  $egg
     * @return array{
     *     egg_number: int|string|null,
     *     species: string,
     *     scientific_name: ?string,
     *     sex: string,
     *     base_color: string,
     *     visual_mutations: list<string>,
     *     split_genes: list<string>,
     *     hidden_genes: list<string>,
     *     split_hidden_genes: list<string>
     * }
     */
    public function collect(array $egg): array
    {
        $species = $this->speciesName($egg['species'] ?? null);
        $scientific = $this->scientificName($egg['species'] ?? null);
        $visual = $this->nameList(
            $egg['visual_mutations']
            ?? $egg['collected_traits']['visual_mutations']
            ?? []
        );
        $splitHidden = $this->nameList(
            $egg['split_hidden_genes']
            ?? $egg['split_genes']
            ?? $egg['collected_traits']['split_hidden_genes']
            ?? []
        );

        // Hidden genes are recorded for genetics only; same carrier list unless separately provided.
        $hidden = $this->nameList($egg['hidden_genes'] ?? $egg['collected_traits']['hidden_genes'] ?? []);
        if ($hidden === []) {
            $hidden = $splitHidden;
        }

        return [
            'egg_number' => $egg['egg_number'] ?? $egg['egg_outcome_id'] ?? null,
            'species' => $species,
            'scientific_name' => $scientific,
            'sex' => $this->sexLabel($egg),
            'base_color' => $this->plain($egg['base_color'] ?? null) ?: 'Not recorded',
            'visual_mutations' => $visual,
            'split_genes' => $splitHidden,
            'hidden_genes' => $hidden,
            'split_hidden_genes' => $splitHidden,
        ];
    }

    /**
     * Canonical AGAPORA OFFSPRING IMAGE PROMPT template (PDF + exact prompt storage).
     *
     * @param  array<string, mixed>  $collected
     */
    public function templatePrompt(array $collected): string
    {
        $visual = $this->joinNames($collected['visual_mutations'] ?? []);
        $hidden = $this->joinNames($collected['split_hidden_genes'] ?? $collected['hidden_genes'] ?? []);
        $species = (string) ($collected['species'] ?? 'lovebird');
        $scientific = $collected['scientific_name'] ?? null;
        $speciesLine = $scientific ? $species.' ('.$scientific.')' : $species;

        return implode("\n", [
            'AGAPORA OFFSPRING IMAGE PROMPT',
            'This image is a visual representation of ONE computed egg/chick outcome.',
            'Do not invent genetics. Do not add extra mutations. Do not draw hidden genes.',
            '',
            'SPECIES: '.$speciesLine,
            'SEX: '.($collected['sex'] ?? 'Not recorded'),
            'BASE COLOR: '.($collected['base_color'] ?? 'Not recorded'),
            'VISUAL MUTATIONS (DRAW THESE): '.$visual,
            'SPLIT AND HIDDEN GENES (DO NOT DRAW): '.$hidden,
            '',
            'INSTRUCTIONS:',
            '- Draw one realistic adult '.$speciesLine.' only.',
            '- Use BASE COLOR as the ground color of the plumage.',
            '- Draw VISUAL MUTATIONS as visible plumage. If none, keep wild-type markings for that species.',
            '- Split and hidden genes must stay invisible. The bird looks normal at those loci.',
            '- Dark eyes unless a visual mutation already requires otherwise.',
            '- Clean studio photograph, single bird, no text, no labels, no extra birds.',
        ]);
    }

    /**
     * Longer scientifically worded prompt for OpenAI-compatible POST bodies.
     *
     * @param  array<string, mixed>  $collected
     */
    public function descriptivePrompt(array $collected): string
    {
        $species = (string) ($collected['species'] ?? 'lovebird');
        $scientific = $collected['scientific_name'] ?? null;
        $speciesLine = $scientific ? $species.' ('.$scientific.')' : $species;
        $base = (string) ($collected['base_color'] ?? 'Not recorded');
        $visual = $this->joinNames($collected['visual_mutations'] ?? []);
        $hidden = $this->joinNames($collected['split_hidden_genes'] ?? []);
        $sex = (string) ($collected['sex'] ?? 'Not recorded');

        return implode(' ', [
            'Generate a scientifically informed visual representation of a '.$speciesLine,
            'with a '.$base.' base coloration',
            ($visual !== 'None' ? 'and visible mutation(s): '.$visual.'.' : 'with no additional visual mutations (wild-type markings for the species).'),
            'Sex presentation: '.$sex.'.',
            'Show realistic lovebird anatomy, natural feather structure, appropriate head and face coloration,',
            'realistic eyes, beak, wings, tail, legs, and proportions.',
            'Present clearly as a single lovebird with visible coloration consistent with the specified genetic outcome.',
            ($hidden !== 'None'
                ? 'The bird carries split/hidden genes ('.$hidden.'), but those must not be visually expressed unless genetically expected in the visible phenotype.'
                : 'No split/hidden genes were recorded for this outcome.'),
            'Clean studio background, full-body view, detailed feathers, scientifically plausible appearance.',
            'Do not invent genetics. Do not add extra mutations. Do not draw labels or text.',
        ]);
    }

    /**
     * Compact prompt for Pollinations GET URL length limits.
     *
     * @param  array<string, mixed>  $collected
     */
    public function shortPrompt(array $collected): string
    {
        $visual = $this->joinNames($collected['visual_mutations'] ?? []);
        $hidden = $this->joinNames($collected['split_hidden_genes'] ?? []);

        return implode(', ', array_filter([
            'realistic single adult '.($collected['species'] ?? 'lovebird').' '.($collected['sex'] ?? ''),
            'base color '.($collected['base_color'] ?? 'as recorded'),
            'visible mutations '.$visual,
            'do not draw hidden genes '.$hidden,
            'clean studio photo, no text, no extra mutations',
        ]));
    }

    public function instructionText(): string
    {
        return implode("\n", [
            'AGAPORA IMAGE PROMPT INSTRUCTIONS',
            '',
            'Use this after RBGIA has already computed each egg/chick outcome.',
            'The image API must not calculate genetics.',
            '',
            '1. Collect from EACH outcome:',
            '   - SPECIES',
            '   - BASE COLOR',
            '   - VISUAL MUTATION',
            '   - SPLIT AND HIDDEN GENES',
            '',
            '2. Insert those four fields into the prompt template.',
            '',
            '3. Send the composed prompt to the image API (Pollinations).',
            '',
            '4. Save the prompt text to a PDF. Save the returned picture on that egg card.',
            '',
            'DRAW: species + base color + visual mutations.',
            'DO NOT DRAW: split and hidden genes.',
        ]);
    }

    /**
     * @param  mixed  $species
     */
    private function speciesName(mixed $species): string
    {
        if (is_array($species)) {
            return $this->plain($species['common_name'] ?? $species['name'] ?? $species['scientific_name'] ?? null) ?: 'lovebird';
        }

        return $this->plain($species) ?: 'lovebird';
    }

    /**
     * @param  mixed  $species
     */
    private function scientificName(mixed $species): ?string
    {
        if (is_array($species)) {
            $name = $this->plain($species['scientific_name'] ?? null);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $egg
     */
    private function sexLabel(array $egg): string
    {
        if (is_string($egg['sex_label'] ?? null) && $egg['sex_label'] !== '') {
            return $egg['sex_label'];
        }

        return match ($egg['sex'] ?? null) {
            'cock', 'male' => 'Male / Cock',
            'hen', 'female' => 'Female / Hen',
            default => 'Not recorded',
        };
    }

    /**
     * @param  mixed  $items
     * @return list<string>
     */
    private function nameList(mixed $items): array
    {
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
    private function joinNames(array $names): string
    {
        return $names === [] ? 'None' : implode(', ', $names);
    }

    private function plain(mixed $value): string
    {
        if (is_array($value)) {
            return $this->plain($value['name'] ?? $value['common_name'] ?? null);
        }

        return trim((string) $value);
    }
}
