<?php

namespace App\Services\Breeding;

use App\Models\BaseColor;
use App\Models\SplitGene;
use App\Models\VisualMutation;
use App\Support\BaseColorCatalog;
use App\Support\SplitGeneCatalog;
use App\Support\VisualMutationCatalog;
use Illuminate\Support\Collection;

/**
 * Collects deterministic RBGIA egg/offspring outcomes.
 * Does not call image generation and does not invent genetics.
 */
class PhenotypeTranslator
{
    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $validation
     * @return list<array<string, mixed>>
     */
    public function translate(array $prediction, array $validation): array
    {
        $parentOne = $validation['parents']['parent_1'] ?? [];
        $parentTwo = $validation['parents']['parent_2'] ?? [];
        $species = $parentOne['species'] ?? $parentTwo['species'] ?? null;

        $theoretical = $prediction['theoretical_distribution'] ?? [];
        if (is_array($theoretical) && $theoretical !== []) {
            return $this->fromTheoreticalDistribution($theoretical, $species, $prediction);
        }

        $eggs = [];
        $eggNumber = 0;

        foreach ($prediction['outcomes'] ?? [] as $outcomeIndex => $outcome) {
            if (! is_array($outcome) || ($outcome['status'] ?? null) !== 'calculated') {
                continue;
            }

            foreach ($outcome['results'] ?? [] as $resultIndex => $row) {
                if (! is_array($row) || empty($row['genotype'])) {
                    continue;
                }

                $eggNumber++;
                $record = $this->lookupStoredRecord($outcome, (string) $row['genotype']);
                $phenotypeText = $row['phenotype'] ?? $this->phenotypeForGenotype($record, (string) $row['genotype'], $outcome);
                $visual = $this->visualCharacteristicsFromStoredText($phenotypeText, $record);
                $alleles = $this->allelesFromGenotype((string) $row['genotype']);

                $eggs[] = [
                    'egg_number' => $eggNumber,
                    'egg_outcome_id' => 'egg-'.$eggNumber,
                    'outcome_key' => $this->outcomeKey($outcomeIndex, $resultIndex, $outcome, $row),
                    'source' => 'RBGIA_locus_example',
                    'representation' => 'example_offspring_outcome',
                    'category' => $outcome['category'] ?? null,
                    'trait_name' => $outcome['name'] ?? null,
                    'inheritance_type' => $outcome['inheritance_type'] ?? null,
                    'species' => $species,
                    'sex' => $row['sex'] ?? null,
                    'base_color' => $row['base_color'] ?? $this->baseColorForOutcome($outcome, $record, $parentOne, $parentTwo),
                    'alleles' => $alleles,
                    'dark_factor' => $this->darkFactorFromRecord($record, (string) $row['genotype']),
                    'visual_mutations' => $row['visual_mutations'] ?? $this->mutationNames($parentOne, $parentTwo, $outcome),
                    'split_hidden_genes' => $row['split_hidden'] ?? $this->splitNames($parentOne, $parentTwo, $outcome),
                    'genotype' => $row['genotype'],
                    'phenotype' => $phenotypeText ?: 'Not specified in stored phenotype record.',
                    'pattern' => $visual['pattern'],
                    'markings' => $visual['markings'],
                    'eyes' => $visual['eyes'],
                    'head' => $visual['head'],
                    'body' => $visual['body'],
                    'wings' => $visual['wings'],
                    'rump' => $visual['rump'],
                    'tail' => $visual['tail'],
                    'other_visual_characteristics' => $visual['other'],
                    'probability' => [
                        'fraction' => $row['fraction'] ?? null,
                        'probability' => $row['probability'] ?? null,
                        'count' => $row['count'] ?? null,
                        'total' => $row['total'] ?? null,
                    ],
                    'parent_1_inheritance' => $outcome['genetic_code_cock'] ?? null,
                    'parent_2_inheritance' => $outcome['genetic_code_hen'] ?? null,
                    'inherited_traits' => array_values(array_filter([
                        $outcome['name'] ?? null,
                        ...($row['visual_mutations'] ?? []),
                        ...($row['split_hidden'] ?? []),
                    ])),
                    'genetic_explanation' => $this->geneticExplanation($outcome, $row, (string) $phenotypeText, $eggNumber),
                    'hatch_forecast_status' => 'Example outcome from a single locus expansion — not a guaranteed clutch sequence.',
                    'verification_status' => $outcome['verification_status'] ?? ($record['verification_status'] ?? null),
                    'scientific_source' => $record['scientific_source'] ?? null,
                    'stored_record_id' => $record['id'] ?? null,
                    'image' => null,
                    'visualization_payload' => null,
                ];
            }
        }

        return $eggs;
    }

    /**
     * @param  list<array<string, mixed>>  $theoretical
     * @param  mixed  $species
     * @param  array<string, mixed>  $prediction
     * @return list<array<string, mixed>>
     */
    private function fromTheoreticalDistribution(array $theoretical, mixed $species, array $prediction): array
    {
        $eggs = [];
        foreach (array_values($theoretical) as $index => $row) {
            if (! is_array($row) || empty($row['genotype'])) {
                continue;
            }
            $number = $index + 1;
            $phenotypeText = $row['phenotype'] ?? 'Not specified in stored phenotype record.';
            $visual = $this->visualCharacteristicsFromStoredText($phenotypeText, null);
            $paths = $row['inheritance_paths'] ?? $row['inherited_from']['paths'] ?? [];

            $eggs[] = [
                'egg_number' => $number,
                'egg_outcome_id' => 'egg-'.$number,
                'outcome_key' => $row['outcome_key'] ?? ('joint-'.$number),
                'source' => 'RBGIA_theoretical_distribution',
                'representation' => 'example_offspring_outcome',
                'disclaimer_note' => 'Derived from the theoretical joint genetic distribution. Not a guaranteed egg order.',
                'category' => 'joint_offspring',
                'trait_name' => 'Joint offspring outcome',
                'inheritance_type' => null,
                'species' => $species,
                'sex' => $row['sex'] ?? null,
                'sex_label' => match ($row['sex'] ?? null) {
                    'cock', 'male' => 'Male / Cock',
                    'hen', 'female' => 'Female / Hen',
                    default => null,
                },
                'base_color' => $row['base_color'] ?? null,
                'base_color_genotype' => $row['base_color_genotype'] ?? null,
                'dark_factor' => $row['dark_factor'] ?? null,
                'alleles' => $this->allelesFromGenotype((string) $row['genotype']),
                'visual_mutations' => $row['visual_mutations'] ?? [],
                'split_hidden_genes' => $row['split_hidden_genes'] ?? [],
                'inheritance_classes' => $this->inheritanceClasses($row),
                'genotype' => $row['genotype'],
                'genotype_display' => $row['genotype'],
                'phenotype' => $phenotypeText,
                'pattern' => $visual['pattern'],
                'markings' => $visual['markings'],
                'eyes' => $visual['eyes'],
                'head' => $visual['head'],
                'body' => $visual['body'],
                'wings' => $visual['wings'],
                'rump' => $visual['rump'],
                'tail' => $visual['tail'],
                'other_visual_characteristics' => $visual['other'],
                'probability' => [
                    'fraction' => $row['fraction'] ?? null,
                    'probability' => $row['probability'] ?? null,
                ],
                'parent_1_inheritance' => collect($paths)->pluck('parent_1_code')->filter()->unique()->implode(' | ') ?: null,
                'parent_2_inheritance' => collect($paths)->pluck('parent_2_code')->filter()->unique()->implode(' | ') ?: null,
                'inherited_traits' => array_values(array_unique(array_filter([
                    $row['base_color'] ?? null,
                    ...($row['visual_mutations'] ?? []),
                    ...($row['split_hidden_genes'] ?? []),
                ]))),
                'genetic_explanation' => 'Example offspring outcome #'.$number.' from the joint AGAPORA RBGIA distribution'
                    .' (probability '.($row['fraction'] ?? round(((float) ($row['probability'] ?? 0)) * 100, 1).'%').').'
                    .' Confidence: '.($prediction['confidence'] ?? 'n/a').'.'
                    .' Predicted from available genetic records and documented inheritance rules — not a biological guarantee for a specific egg.',
                'hatch_forecast_status' => 'Theoretical distribution example — clutch sequence is not implied.',
                'verification_status' => $prediction['confidence'] ?? null,
                'scientific_source' => null,
                'stored_record_id' => null,
                'loci' => $row['loci'] ?? [],
                'inheritance_paths' => $paths,
                'image' => null,
                'visualization_payload' => null,
            ];
        }

        return $eggs;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>|null
     */
    private function lookupStoredRecord(array $outcome, string $genotype): ?array
    {
        $category = $outcome['category'] ?? null;
        $name = $outcome['name'] ?? null;

        if ($category === 'base_color') {
            $records = BaseColor::query()->when($name, fn ($query) => $query->where('name', $name))->get();
            $match = $this->matchByGenotype($records, $genotype) ?? ($name ? BaseColor::query()->where('name', $name)->first() : null);

            return BaseColorCatalog::geneticPayload($match);
        }

        if ($category === 'visual_mutation') {
            $records = VisualMutation::query()->when($name, fn ($query) => $query->where('name', $name))->get();
            $match = $this->matchByGenotype($records, $genotype) ?? ($name ? VisualMutation::query()->where('name', $name)->first() : null);

            return VisualMutationCatalog::geneticPayload($match);
        }

        if ($category === 'split_gene') {
            $records = SplitGene::query()->when($name, fn ($query) => $query->where('name', $name))->get();
            $match = $this->matchByGenotype($records, $genotype) ?? ($name ? SplitGene::query()->where('name', $name)->first() : null);

            return SplitGeneCatalog::geneticPayload($match);
        }

        return null;
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    private function matchByGenotype(Collection $records, string $genotype): mixed
    {
        $normalized = $this->normalizeGenotype($genotype);

        return $records->first(function ($record) use ($normalized, $genotype) {
            $candidates = array_filter([
                $record->genotype ?? null,
                $record->genetic_code ?? null,
                $record->heterozygous_genotype ?? null,
                $record->homozygous_genotype ?? null,
            ]);

            foreach ($candidates as $candidate) {
                foreach (explode('|', (string) $candidate) as $part) {
                    if ($this->normalizeGenotype($part) === $normalized) {
                        return true;
                    }
                    if (strcasecmp(trim($part), trim($genotype)) === 0) {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    private function normalizeGenotype(string $genotype): string
    {
        $parts = array_map('trim', explode('/', $genotype));
        sort($parts);

        return strtolower(implode('/', $parts));
    }

    /**
     * @return list<string>
     */
    private function allelesFromGenotype(string $genotype): array
    {
        return array_values(array_filter(array_map('trim', explode('/', $genotype))));
    }

    /**
     * @param  array<string, mixed>|null  $record
     */
    private function darkFactorFromRecord(?array $record, string $genotype): string
    {
        if (! $record) {
            return 'Not documented for this stored outcome.';
        }

        $alleles = $this->allelesFromGenotype($genotype);
        $mutantCount = count(array_filter($alleles, fn (string $allele) => ! str_ends_with($allele, '+') && strcasecmp($allele, 'W') !== 0));

        if ($mutantCount >= 2 && ! empty($record['df'])) {
            return (string) $record['df'];
        }

        if ($mutantCount === 1 && ! empty($record['sf'])) {
            return (string) $record['sf'];
        }

        if (! empty($record['sf']) || ! empty($record['df'])) {
            return trim(implode(' | ', array_filter([
                ! empty($record['sf']) ? 'SF: '.$record['sf'] : null,
                ! empty($record['df']) ? 'DF: '.$record['df'] : null,
            ])));
        }

        return 'Not documented for this stored outcome.';
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>|null  $record
     * @param  array<string, mixed>  $parentOne
     * @param  array<string, mixed>  $parentTwo
     */
    private function baseColorForOutcome(array $outcome, ?array $record, array $parentOne, array $parentTwo): string
    {
        if (($outcome['category'] ?? null) === 'base_color') {
            return (string) ($outcome['name'] ?? $record['name'] ?? 'Base color outcome');
        }

        $left = $parentOne['base_color']['name'] ?? null;
        $right = $parentTwo['base_color']['name'] ?? null;

        if ($left && $right && strcasecmp($left, $right) === 0) {
            return $left.' (parent base color context; this egg card is for '.($outcome['name'] ?? 'another locus').')';
        }

        if ($left || $right) {
            return 'Parent base colors: '.implode(' / ', array_filter([$left, $right]))
                .'. This egg card is a '.($outcome['category'] ?? 'locus').' result, not a separate invented base-color draw.';
        }

        return 'No stored base color was available for this outcome.';
    }

    /**
     * @param  array<string, mixed>|null  $record
     * @param  array<string, mixed>  $outcome
     */
    private function phenotypeForGenotype(?array $record, string $genotype, array $outcome): string
    {
        if ($record && ! empty($record['phenotype'])) {
            return (string) $record['phenotype'];
        }

        if ($record && ! empty($record['phenotype_when_visual'])) {
            return (string) $record['phenotype_when_visual'];
        }

        if ($record && ! empty($record['description'])) {
            return (string) $record['description'];
        }

        return 'No stored phenotype text is available for genotype '.$genotype
            .' ('.($outcome['name'] ?? 'trait').'). A phenotype was not invented.';
    }

    /**
     * @param  array<string, mixed>|null  $record
     * @return array<string, string>
     */
    private function visualCharacteristicsFromStoredText(string $phenotypeText, ?array $record): array
    {
        $regions = [
            'pattern' => ['pattern', 'pied', 'misty', 'dilute', 'ino'],
            'markings' => ['marking', 'cheek', 'collar', 'mask', 'band'],
            'eyes' => ['eye', 'iris', 'pupil'],
            'head' => ['head', 'face', 'forehead', 'crown', 'mask'],
            'body' => ['body', 'breast', 'chest', 'belly', 'abdomen', 'plumage'],
            'wings' => ['wing', 'flight', 'coverts'],
            'rump' => ['rump', 'lower back'],
            'tail' => ['tail', 'rectrix', 'rectrices'],
        ];

        $lower = strtolower($phenotypeText);
        $result = [];

        foreach ($regions as $key => $keywords) {
            $matched = false;
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $matched = true;
                    break;
                }
            }
            $result[$key] = $matched
                ? $phenotypeText
                : 'Not specified in stored phenotype record.';
        }

        $result['other'] = ! empty($record['series'])
            ? 'Series: '.$record['series'].'. '.$phenotypeText
            : $phenotypeText;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $parentOne
     * @param  array<string, mixed>  $parentTwo
     * @param  array<string, mixed>  $outcome
     * @return list<string>
     */
    private function mutationNames(array $parentOne, array $parentTwo, array $outcome): array
    {
        if (($outcome['category'] ?? null) === 'visual_mutation' && ! empty($outcome['name'])) {
            return [(string) $outcome['name']];
        }

        return collect($parentOne['visual_mutations'] ?? [])
            ->merge($parentTwo['visual_mutations'] ?? [])
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parentOne
     * @param  array<string, mixed>  $parentTwo
     * @param  array<string, mixed>  $outcome
     * @return list<string>
     */
    private function splitNames(array $parentOne, array $parentTwo, array $outcome): array
    {
        if (($outcome['category'] ?? null) === 'split_gene' && ! empty($outcome['name'])) {
            return [(string) $outcome['name']];
        }

        return collect($parentOne['split_genes'] ?? [])
            ->merge($parentTwo['split_genes'] ?? [])
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $row
     */
    private function geneticExplanation(array $outcome, array $row, string $phenotypeText, int $eggNumber): string
    {
        return implode(' ', [
            'Egg Outcome #'.$eggNumber.' was produced by RBGIA only.',
            'Calculated genotype '.$row['genotype'].' for '.($outcome['name'] ?? 'this trait').'.',
            'Inheritance type: '.($outcome['inheritance_type'] ?: 'not recorded').'.',
            'Parent 1 (cock) genetic code: '.($outcome['genetic_code_cock'] ?: 'not stored').'.',
            'Parent 2 (hen) genetic code: '.($outcome['genetic_code_hen'] ?: 'not stored').'.',
            'Probability: '.($row['fraction'] ?? 'not calculated').'.',
            'Phenotype text is taken from stored records only: '.$phenotypeText,
            'No random egg or trait assignment was used.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{dominant: bool, sex_linked: bool, recessive: bool}
     */
    private function inheritanceClasses(array $row): array
    {
        $classes = [
            'dominant' => false,
            'sex_linked' => false,
            'recessive' => false,
        ];

        foreach ($row['loci'] ?? [] as $locus) {
            if (! is_array($locus)) {
                continue;
            }
            $type = strtolower((string) ($locus['inheritance_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            if (str_contains($type, 'sex-linked')) {
                $classes['sex_linked'] = true;
            }
            if ((str_contains($type, 'dominant') && ! str_contains($type, 'recessive'))
                || str_contains($type, 'incomplete')
                || str_contains($type, 'intermediate')
                || str_contains($type, 'partial')) {
                $classes['dominant'] = true;
            }
            if (str_contains($type, 'recessive')) {
                $classes['recessive'] = true;
            }
        }

        if (($row['split_hidden_genes'] ?? []) !== []) {
            $classes['recessive'] = true;
        }

        return $classes;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $row
     */
    private function outcomeKey(int $outcomeIndex, int $resultIndex, array $outcome, array $row): string
    {
        return implode(':', [
            $outcomeIndex,
            $resultIndex,
            $outcome['category'] ?? 'unknown',
            $outcome['name'] ?? 'trait',
            $row['sex'] ?? 'both',
            $row['genotype'] ?? 'genotype',
            $row['fraction'] ?? 'na',
        ]);
    }
}
