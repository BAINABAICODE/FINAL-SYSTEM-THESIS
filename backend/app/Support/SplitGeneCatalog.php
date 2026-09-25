<?php

namespace App\Support;

use App\Models\Bird;
use App\Models\SplitGene;
use Illuminate\Support\Collection;

/**
 * Split/hidden-gene records for species-aware inheritance calculations.
 * Genetic codes and allele symbols are returned exactly as stored.
 */
class SplitGeneCatalog
{
    /**
     * @return Collection<int, SplitGene>
     */
    public static function forSpecies(int $speciesId): Collection
    {
        return SplitGene::query()
            ->where('lovebird_species_id', $speciesId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Shared carrier locus only when the source category names a locus.
     * A split stores one hidden mutant allele with the wild-type allele.
     */
    public static function carrierLocus(?string $geneticCategory): ?string
    {
        if ($geneticCategory !== null && preg_match('/locus/i', $geneticCategory) === 1) {
            return $geneticCategory;
        }

        return null;
    }

    public static function sexCanCarry(SplitGene $gene, ?string $sex): bool
    {
        if ($sex === Bird::SEX_HEN) {
            return $gene->hen_can_split === true;
        }

        if ($sex === Bird::SEX_COCK) {
            return $gene->cock_can_split === true;
        }

        return true;
    }

    /**
     * @param  list<int>  $ids
     */
    public static function incompatibleMessage(array $ids, int $speciesId, ?string $sex = null): ?string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return null;
        }

        $genes = self::forSpecies($speciesId)->whereIn('id', $ids)->values();
        if ($genes->count() !== count($ids)) {
            return 'Split/hidden genes must belong to the selected species.';
        }

        $locusSeen = [];
        foreach ($genes as $gene) {
            if (! self::sexCanCarry($gene, $sex)) {
                if ($sex === Bird::SEX_HEN) {
                    return $gene->name.' cannot be stored as a hidden split for a hen. The dataset records this as a sex-linked gene that is visual on the single Z.';
                }

                return $gene->name.' cannot be stored as a hidden split for a cock.';
            }

            $locus = self::carrierLocus($gene->genetic_category);
            if ($locus === null) {
                continue;
            }

            if (isset($locusSeen[$locus])) {
                return $gene->name.' cannot be combined with '.$locusSeen[$locus].'. The dataset records these as alleles of the same locus, not two splits.';
            }

            $locusSeen[$locus] = $gene->name;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function geneticPayload(?SplitGene $gene): ?array
    {
        if (! $gene) {
            return null;
        }

        return [
            'id' => $gene->id,
            'name' => $gene->name,
            'species_id' => $gene->lovebird_species_id,
            'species_name' => $gene->species_name,
            'scientific_name' => $gene->scientific_name,
            'genetic_symbol' => $gene->genetic_symbol,
            'allele' => $gene->genetic_symbol,
            'wild_type_allele' => $gene->wild_type_allele,
            'mutant_allele' => $gene->mutant_allele,
            'inheritance_type' => $gene->inheritance_type,
            'genetic_category' => $gene->genetic_category,
            'cock_can_split' => $gene->cock_can_split,
            'hen_can_split' => $gene->hen_can_split,
            'heterozygous_genotype' => $gene->heterozygous_genotype,
            'homozygous_genotype' => $gene->homozygous_genotype,
            'genotype' => $gene->heterozygous_genotype,
            'genetic_code' => $gene->genetic_code,
            'phenotype_when_visual' => $gene->phenotype_when_visual,
            'description' => $gene->description,
            'phenotype' => $gene->description,
            'verification_status' => $gene->verification_status,
            'scientific_source' => $gene->scientific_source,
            'computable' => $gene->computable,
            'carrier_locus' => self::carrierLocus($gene->genetic_category),
        ];
    }
}
