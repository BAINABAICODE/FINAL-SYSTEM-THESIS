<?php

namespace App\Services\Breeding;

/**
 * Keeps stored analysis JSON under MySQL max_allowed_packet without changing genetics math.
 */
class AnalysisPayloadCompressor
{
    /**
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    public function compressPrediction(array $prediction): array
    {
        $copy = $prediction;
        $copy['outcomes'] = array_map(fn ($outcome) => $this->compressOutcome(is_array($outcome) ? $outcome : []), $prediction['outcomes'] ?? []);
        $copy['theoretical_distribution'] = array_map(
            fn ($row) => $this->compressJointRow(is_array($row) ? $row : []),
            $prediction['theoretical_distribution'] ?? [],
        );
        $copy['punnett_squares'] = array_map(function ($square) {
            if (! is_array($square)) {
                return $square;
            }
            $square['results'] = array_map(fn ($row) => $this->compressLocusResult(is_array($row) ? $row : []), $square['results'] ?? []);

            return $square;
        }, $prediction['punnett_squares'] ?? []);

        return $copy;
    }

    /**
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>
     */
    public function compressPresentation(array $presentation): array
    {
        $copy = $presentation;
        unset(
            $copy['ai_interpretation'],
            $copy['computation_status'],
            $copy['theoretical_distribution'],
            $copy['punnett_square'],
            $copy['parent_profiles'],
        );

        // Eggs are stored separately on offspring_visualizations.
        unset($copy['egg_chick_examples']);

        if (isset($copy['probabilities']) && is_array($copy['probabilities'])) {
            unset(
                $copy['probabilities']['base_colors'],
                $copy['probabilities']['mutations'],
                $copy['probabilities']['split_genes'],
            );
        }

        if (isset($copy['probabilities']['complete_offspring']) && is_array($copy['probabilities']['complete_offspring'])) {
            $copy['probabilities']['complete_offspring'] = array_map(
                fn ($row) => $this->compressCompleteRow(is_array($row) ? $row : []),
                $copy['probabilities']['complete_offspring'],
            );
        }

        if (isset($copy['egg_chick_examples']) && is_array($copy['egg_chick_examples'])) {
            $copy['egg_chick_examples'] = array_map(
                fn ($egg) => $this->compressEgg(is_array($egg) ? $egg : []),
                $copy['egg_chick_examples'],
            );
        }

        if (isset($copy['punnett_square']) && is_array($copy['punnett_square'])) {
            $copy['punnett_square'] = array_map(function ($square) {
                if (! is_array($square)) {
                    return $square;
                }
                $square['results'] = array_map(fn ($row) => $this->compressLocusResult(is_array($row) ? $row : []), $square['results'] ?? []);

                return $square;
            }, $copy['punnett_square']);
        }

        return $copy;
    }

    /**
     * @param  list<array<string, mixed>>  $eggs
     * @return list<array<string, mixed>>
     */
    public function compressEggs(array $eggs): array
    {
        return array_map(fn ($egg) => $this->compressEgg(is_array($egg) ? $egg : []), $eggs);
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    private function compressOutcome(array $outcome): array
    {
        if (isset($outcome['results']) && is_array($outcome['results'])) {
            $outcome['results'] = array_map(fn ($row) => $this->compressLocusResult(is_array($row) ? $row : []), $outcome['results']);
        }
        if (isset($outcome['punnett']['results']) && is_array($outcome['punnett']['results'])) {
            // Avoid duplicating the full result matrix under punnett.
            unset($outcome['punnett']['results']);
        }

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function compressLocusResult(array $row): array
    {
        unset($row['gamete_combinations'], $row['inheritance_path']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function compressJointRow(array $row): array
    {
        unset(
            $row['genotype_parts'],
            $row['expression_parts'],
            $row['categories'],
            $row['inherited_from'],
        );

        if (isset($row['loci']) && is_array($row['loci'])) {
            $row['loci'] = array_map(function ($locus) {
                if (! is_array($locus)) {
                    return $locus;
                }

                return [
                    'category' => $locus['category'] ?? null,
                    'name' => $locus['name'] ?? null,
                    'locus_key' => $locus['locus_key'] ?? null,
                    'inheritance_type' => $locus['inheritance_type'] ?? null,
                    'genotype' => $locus['genotype'] ?? null,
                    'expression' => $locus['expression'] ?? null,
                    'fraction' => $locus['fraction'] ?? null,
                    'probability' => $locus['probability'] ?? null,
                ];
            }, $row['loci']);
        }

        if (isset($row['inheritance_paths']) && is_array($row['inheritance_paths'])) {
            $row['inheritance_paths'] = array_map(function ($path) {
                if (! is_array($path)) {
                    return $path;
                }

                return [
                    'locus' => $path['locus'] ?? null,
                    'parent_1_code' => $path['parent_1_code'] ?? null,
                    'parent_2_code' => $path['parent_2_code'] ?? null,
                ];
            }, $row['inheritance_paths']);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function compressCompleteRow(array $row): array
    {
        unset($row['loci'], $row['inheritance_paths']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $egg
     * @return array<string, mixed>
     */
    private function compressEgg(array $egg): array
    {
        unset(
            $egg['loci'],
            $egg['inheritance_paths'],
            $egg['visualization_payload'],
            $egg['pattern'],
            $egg['markings'],
            $egg['eyes'],
            $egg['head'],
            $egg['body'],
            $egg['wings'],
            $egg['rump'],
            $egg['tail'],
            $egg['other_visual_characteristics'],
        );

        if (isset($egg['image_prompt']) && is_array($egg['image_prompt'])) {
            $egg['composed_image_prompt'] = $egg['composed_image_prompt']
                ?? ($egg['image_prompt']['prompt'] ?? null);
            $egg['image_prompt_short'] = $egg['image_prompt_short']
                ?? ($egg['image_prompt']['prompt_short'] ?? $egg['image_prompt']['prompt_short'] ?? null);
            $egg['collected_traits'] = $egg['collected_traits']
                ?? ($egg['image_prompt']['collected'] ?? null);
            unset($egg['image_prompt']);
        }

        if (isset($egg['genetic_explanation']) && is_string($egg['genetic_explanation']) && strlen($egg['genetic_explanation']) > 400) {
            $egg['genetic_explanation'] = substr($egg['genetic_explanation'], 0, 397).'...';
        }

        return $egg;
    }
}
