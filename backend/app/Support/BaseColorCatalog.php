<?php

namespace App\Support;

use App\Models\BaseColor;
use Illuminate\Support\Collection;

/**
 * Genetic base-color records for species-aware calculations.
 * Verification status and scientific source are returned exactly as stored.
 */
class BaseColorCatalog
{
    /**
     * @return Collection<int, BaseColor>
     */
    public static function forSpecies(int $speciesId, bool $computableOnly = false): Collection
    {
        $query = BaseColor::query()
            ->where('lovebird_species_id', $speciesId)
            ->orderBy('sort_order');

        if ($computableOnly) {
            $query->where('computable', true);
        }

        return $query->get();
    }

    /**
     * Fields the breeding algorithm may read. Needs Verification records stay marked as such.
     *
     * @return array<string, mixed>|null
     */
    public static function geneticPayload(?BaseColor $color): ?array
    {
        if (! $color) {
            return null;
        }

        return [
            'id' => $color->id,
            'name' => $color->name,
            'species_id' => $color->lovebird_species_id,
            'species_name' => $color->species_name,
            'scientific_name' => $color->scientific_name,
            'series' => $color->series,
            'sf' => $color->sf,
            'df' => $color->df,
            'allele' => $color->allele,
            'genotype' => $color->genotype,
            'genetic_code' => $color->genetic_code,
            'inheritance_type' => $color->inheritance_type,
            'phenotype' => $color->phenotype,
            'verification_status' => $color->verification_status,
            'scientific_source' => $color->scientific_source,
            'computable' => $color->computable,
        ];
    }
}
