<?php

namespace Database\Seeders;

use App\Models\BaseColor;
use App\Models\LovebirdSpecies;
use App\Support\BaseColorDataset;
use Illuminate\Database\Seeder;
use RuntimeException;

class BaseColorSeeder extends Seeder
{
    public function run(): void
    {
        $directory = database_path('data/base-colors');
        $records = BaseColorDataset::records($directory);

        if ($records === []) {
            throw new RuntimeException('No base color dataset records were found.');
        }

        $kept = [];

        foreach ($records as $record) {
            $species = LovebirdSpecies::query()
                ->where('scientific_name', $record['scientific_name'])
                ->first();

            if (! $species) {
                throw new RuntimeException(
                    "No lovebird species matches scientific name {$record['scientific_name']}."
                );
            }

            if ($record['name'] === null) {
                throw new RuntimeException('Dataset row is missing a base color name.');
            }

            $color = BaseColor::query()->updateOrCreate(
                [
                    'lovebird_species_id' => $species->id,
                    'name' => $record['name'],
                ],
                [
                    'species_name' => $species->common_name,
                    'scientific_name' => $record['scientific_name'],
                    'series' => $record['series'],
                    'sf' => $record['sf'],
                    'df' => $record['df'],
                    'allele' => $record['allele'],
                    'genotype' => $record['genotype'],
                    'genetic_code' => $record['genetic_code'],
                    'inheritance_type' => $record['inheritance_type'],
                    'phenotype' => $record['phenotype'],
                    'verification_status' => $record['verification_status'],
                    'scientific_source' => $record['scientific_source'],
                    'computable' => $record['computable'],
                    'sort_order' => $record['sort_order'],
                ],
            );

            $kept[] = $color->id;
        }

        BaseColor::query()
            ->whereNotIn('id', $kept)
            ->delete();
    }
}
