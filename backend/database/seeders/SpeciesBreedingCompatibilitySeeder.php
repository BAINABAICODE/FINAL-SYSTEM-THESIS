<?php

namespace Database\Seeders;

use App\Models\SpeciesBreedingCompatibility;
use App\Support\SpeciesPairClassifier;
use Illuminate\Database\Seeder;

/**
 * One row per unique species pair (C(9,2) = 36).
 * Same-species pairing is classified at validation time and is not stored.
 * Cross-species prediction stays blocked; these rows only document why.
 */
class SpeciesBreedingCompatibilitySeeder extends Seeder
{
    public function run(): void
    {
        $classifier = new SpeciesPairClassifier;
        $kept = [];

        foreach ($classifier->uniquePairs() as [$left, $right]) {
            $class = $classifier->classify($left, $right);
            $record = SpeciesBreedingCompatibility::query()->updateOrCreate(
                [
                    'species_low_id' => min($left, $right),
                    'species_high_id' => max($left, $right),
                ],
                [
                    'species_1_id' => $left,
                    'species_2_id' => $right,
                    'species_low_id' => min($left, $right),
                    'species_high_id' => max($left, $right),
                    'direction_sensitive' => false,
                    'compatibility_status' => $class['status'],
                    'breeding_type' => $class['breeding_type'],
                    'fertility_status' => $class['fertility_status'],
                    'risk_level' => $class['risk_level'],
                    'warning_message' => $class['warning'],
                    'scientific_basis' => $class['basis'],
                    'scientific_source' => $class['source'],
                    'verification_status' => $class['verification'],
                    'notes' => 'RBGIA does not transfer alleles across species. This row supports GICA species scoring and pair blocking only.',
                ],
            );
            $kept[] = $record->id;
        }

        SpeciesBreedingCompatibility::query()
            ->whereNotIn('id', $kept)
            ->delete();
    }
}
