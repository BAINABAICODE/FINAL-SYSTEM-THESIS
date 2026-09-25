<?php

namespace Database\Seeders;

use App\Models\LovebirdSpecies;
use App\Models\SplitGene;
use App\Support\SplitGeneDataset;
use Illuminate\Database\Seeder;
use RuntimeException;

class SplitGeneSeeder extends Seeder
{
    public function run(): void
    {
        $directory = database_path('data/split-genes');
        $records = SplitGeneDataset::records($directory);

        if ($records === []) {
            throw new RuntimeException('No split gene dataset records were found.');
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

            $gene = SplitGene::query()->updateOrCreate(
                [
                    'lovebird_species_id' => $species->id,
                    'name' => $record['name'],
                ],
                [
                    'species_name' => $species->common_name,
                    'scientific_name' => $record['scientific_name'],
                    'genetic_symbol' => $record['genetic_symbol'],
                    'wild_type_allele' => $record['wild_type_allele'],
                    'mutant_allele' => $record['mutant_allele'],
                    'inheritance_type' => $record['inheritance_type'],
                    'genetic_category' => $record['genetic_category'],
                    'cock_can_split' => $record['cock_can_split'],
                    'hen_can_split' => $record['hen_can_split'],
                    'heterozygous_genotype' => $record['heterozygous_genotype'],
                    'homozygous_genotype' => $record['homozygous_genotype'],
                    'genetic_code' => $record['genetic_code'],
                    'phenotype_when_visual' => $record['phenotype_when_visual'],
                    'description' => $record['description'],
                    'scientific_source' => $record['scientific_source'],
                    'verification_status' => $record['verification_status'],
                    'computable' => $record['computable'],
                    'sort_order' => $record['sort_order'],
                ],
            );

            $kept[] = $gene->id;
        }

        SplitGene::query()
            ->whereNotIn('id', $kept)
            ->delete();
    }
}
