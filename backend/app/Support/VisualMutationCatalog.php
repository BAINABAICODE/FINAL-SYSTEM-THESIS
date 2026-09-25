<?php

namespace App\Support;

use App\Models\VisualMutation;
use Illuminate\Support\Collection;

/**
 * Visual-mutation records for species-aware inheritance calculations.
 * Source fields are returned exactly as stored. Compatibility keys are
 * extracted from allele and phenotype wording already present in the dataset.
 */
class VisualMutationCatalog
{
    /**
     * @return Collection<int, VisualMutation>
     */
    public static function forSpecies(int $speciesId): Collection
    {
        return VisualMutation::query()
            ->where('lovebird_species_id', $speciesId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Dosage pair symbol from allele text that begins "one X" or "two X".
     * Rows without that wording have no dosage key.
     */
    public static function dosageKey(?string $allele): ?string
    {
        if ($allele === null || $allele === '') {
            return null;
        }

        if (preg_match('/^(one|two)\s+([A-Za-z0-9*]+)/', $allele, $match) === 1) {
            return $match[2];
        }

        return null;
    }

    /**
     * Shared locus only when the source series or allele text names one.
     */
    public static function locusKey(?string $series, ?string $allele): ?string
    {
        if ($series !== null && preg_match('/locus$/i', $series) === 1) {
            return 'series:'.$series;
        }

        if ($allele !== null && preg_match('/Allele of ([^.]+)/i', $allele, $match) === 1) {
            return 'allele-of:'.strtolower(trim($match[1]));
        }

        return null;
    }

    /**
     * @param  Collection<int, VisualMutation>  $mutations
     * @return array<int, list<string>>
     */
    public static function combinationNamesById(Collection $mutations): array
    {
        $byName = [];
        foreach ($mutations as $mutation) {
            $byName[self::nameKey($mutation->name)] = $mutation;
        }

        $partners = [];
        foreach ($mutations as $mutation) {
            $partners[$mutation->id] = [];
        }

        foreach ($mutations as $mutation) {
            foreach (self::partnerNamesFromPhenotype($mutation->phenotype) as $partnerName) {
                $partner = $byName[self::nameKey($partnerName)] ?? null;
                if (! $partner || $partner->id === $mutation->id) {
                    continue;
                }

                $partners[$mutation->id][] = $partner->name;
                $partners[$partner->id][] = $mutation->name;
            }
        }

        foreach ($partners as $id => $names) {
            $partners[$id] = array_values(array_unique($names));
        }

        return $partners;
    }

    /**
     * @param  list<int>  $ids
     */
    public static function incompatibleMessage(array $ids, int $speciesId): ?string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return null;
        }

        $mutations = self::forSpecies($speciesId)->whereIn('id', $ids)->values();
        if ($mutations->count() !== count($ids)) {
            return 'Visual mutations must belong to the selected species.';
        }

        $combinations = self::combinationNamesById(self::forSpecies($speciesId));

        $dosageSeen = [];
        foreach ($mutations as $mutation) {
            $dosageKey = self::dosageKey($mutation->allele);
            if ($dosageKey === null) {
                continue;
            }

            if (isset($dosageSeen[$dosageKey])) {
                return $mutation->name.' cannot be combined with '.$dosageSeen[$dosageKey].'. The dataset records these as doses of the same mutation.';
            }

            $dosageSeen[$dosageKey] = $mutation->name;
        }

        $locusGroups = [];
        foreach ($mutations as $mutation) {
            $locusKey = self::locusKey($mutation->series, $mutation->allele);
            if ($locusKey === null) {
                continue;
            }

            $locusGroups[$locusKey][] = $mutation;
        }

        foreach ($locusGroups as $group) {
            $count = count($group);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (! self::isDocumentedCombination($group[$i], $group[$j], $combinations)) {
                        return $group[$i]->name.' cannot be combined with '.$group[$j]->name.'. The dataset records these as alleles of the same locus, not a valid combination.';
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function geneticPayload(?VisualMutation $mutation, ?Collection $speciesMutations = null): ?array
    {
        if (! $mutation) {
            return null;
        }

        $speciesMutations ??= $mutation->lovebird_species_id
            ? self::forSpecies($mutation->lovebird_species_id)
            : collect([$mutation]);

        $combinations = self::combinationNamesById($speciesMutations);

        return [
            'id' => $mutation->id,
            'name' => $mutation->name,
            'species_id' => $mutation->lovebird_species_id,
            'species_name' => $mutation->species_name,
            'scientific_name' => $mutation->scientific_name,
            'series' => $mutation->series,
            'sf' => $mutation->sf,
            'df' => $mutation->df,
            'allele' => $mutation->allele,
            'genotype' => $mutation->genotype,
            'genetic_code' => $mutation->genetic_code,
            'inheritance_type' => $mutation->inheritance_type,
            'phenotype' => $mutation->phenotype,
            'verification_status' => $mutation->verification_status,
            'scientific_source' => $mutation->scientific_source,
            'computable' => $mutation->computable,
            'dosage_key' => self::dosageKey($mutation->allele),
            'locus_key' => self::locusKey($mutation->series, $mutation->allele),
            'combination_names' => $combinations[$mutation->id] ?? [],
        ];
    }

    /**
     * Partner names taken only from combination sentences in the source phenotype.
     *
     * @return list<string>
     */
    private static function partnerNamesFromPhenotype(?string $phenotype): array
    {
        if ($phenotype === null || $phenotype === '') {
            return [];
        }

        $names = [];

        if (preg_match('/NSL Ino plus this allele, a combination/i', $phenotype) === 1) {
            $names[] = 'NSL Ino';
        }

        if (preg_match('/this allele with NSL Ino/i', $phenotype) === 1) {
            $names[] = 'NSL Ino';
        }

        if (preg_match('/Opaline NSL ino is a combination/i', $phenotype) === 1) {
            $names[] = 'NSL Ino';
        }

        return $names;
    }

    /**
     * @param  array<int, list<string>>  $combinations
     */
    private static function isDocumentedCombination(
        VisualMutation $left,
        VisualMutation $right,
        array $combinations,
    ): bool {
        $leftNames = $combinations[$left->id] ?? [];
        $rightNames = $combinations[$right->id] ?? [];

        return in_array($right->name, $leftNames, true) || in_array($left->name, $rightNames, true);
    }

    private static function nameKey(?string $name): string
    {
        return strtolower(trim((string) $name));
    }
}
