<?php

namespace Database\Seeders;

use App\Models\BreedingSafetyRule;
use App\Models\LovebirdSpecies;
use Illuminate\Database\Seeder;

/**
 * Age guidance only. These rows never change RBGIA fractions.
 * Rare species stay marked so the validator will not pretend a measured fertility curve exists.
 */
class BreedingSafetyRuleSeeder extends Seeder
{
    public function run(): void
    {
        $common = [1, 2, 3];
        $kept = [];

        foreach (LovebirdSpecies::query()->orderBy('id')->get() as $species) {
            $isCommon = in_array((int) $species->id, $common, true);

            $rule = BreedingSafetyRule::query()->updateOrCreate(
                ['lovebird_species_id' => $species->id],
                [
                    'minimum_age_months' => 10,
                    'recommended_min_months' => 12,
                    'recommended_max_months' => 72,
                    'warning_below_minimum' => $species->common_name.': this bird may be younger than typical first-breeding age. Confirm health and maturity before pairing.',
                    'warning_outside_range' => $species->common_name.': this bird may be outside the usual aviary breeding-age window. Confirm condition with a veterinarian or experienced breeder.',
                    'scientific_source' => $isCommon
                        ? 'Standard aviary husbandry for commonly kept Agapornis (maturity often ~10–12 months). Not a fertility experiment.'
                        : 'Genus-level aviary guidance only. No species-specific longitudinal fertility study is stored for '.$species->scientific_name.'.',
                    'verification_status' => $isCommon
                        ? 'Aviculture guidance'
                        : 'Needs Verification',
                    'notes' => 'GICA may read age warnings. RBGIA inheritance math is never adjusted by age. External factors (nutrition, health, incubation) are out of scope.',
                ],
            );

            $kept[] = $rule->id;
        }

        BreedingSafetyRule::query()
            ->whereNotIn('id', $kept)
            ->delete();
    }
}
