<?php

namespace Database\Seeders;

use App\Models\LovebirdSpecies;
use Illuminate\Database\Seeder;

class LovebirdSpeciesSeeder extends Seeder
{
    public function run(): void
    {
        $species = [
            [
                'id' => 1,
                'common_name' => 'Rosy-faced lovebird',
                'alternate_names' => 'Peach-faced lovebird',
                'scientific_name' => 'Agapornis roseicollis',
                'species_group' => 'Non-eye ring',
                'has_eye_ring' => false,
                'description' => 'Peach-faced favorite with strong pair bonds and the widest color-mutation range among lovebirds.',
                'scientific_source' => 'Forshaw, Parrots of the World; MUTAVI A. roseicollis mutation catalog',
                'verification_status' => 'Verified',
            ],
            [
                'id' => 2,
                'common_name' => "Fischer's lovebird",
                'alternate_names' => null,
                'scientific_name' => 'Agapornis fischeri',
                'species_group' => 'Eye ring',
                'has_eye_ring' => true,
                'description' => 'Bright green with an orange face and white eye ring—often used in inheritance studies.',
                'scientific_source' => 'Forshaw, Parrots of the World; AGASSCOM / MUTAVI A. fischeri catalogs',
                'verification_status' => 'Verified',
            ],
            [
                'id' => 3,
                'common_name' => 'Yellow-collared lovebird',
                'alternate_names' => 'Masked lovebird',
                'scientific_name' => 'Agapornis personatus',
                'species_group' => 'Eye ring',
                'has_eye_ring' => true,
                'description' => 'Dark mask and yellow collar define this classic eye-ring breeding species.',
                'scientific_source' => 'Forshaw, Parrots of the World; AGASSCOM / MUTAVI A. personatus catalogs',
                'verification_status' => 'Verified',
            ],
            [
                'id' => 4,
                'common_name' => 'Black-cheeked lovebird',
                'alternate_names' => null,
                'scientific_name' => 'Agapornis nigrigenis',
                'species_group' => 'Eye ring',
                'has_eye_ring' => true,
                'description' => 'Rare eye-ring bird with bold black cheeks; valued in conservation-minded breeding.',
                'scientific_source' => 'Forshaw, Parrots of the World; IUCN/BirdLife A. nigrigenis',
                'verification_status' => 'Verified',
            ],
            [
                'id' => 5,
                'common_name' => "Lilian's lovebird",
                'alternate_names' => 'Nyasa lovebird',
                'scientific_name' => 'Agapornis lilianae',
                'species_group' => 'Eye ring',
                'has_eye_ring' => true,
                'description' => 'Smaller peach-toned eye-ring species, closely tracked for lineage and origin.',
                'scientific_source' => 'Forshaw, Parrots of the World; IUCN/BirdLife A. lilianae',
                'verification_status' => 'Verified',
            ],
            [
                'id' => 6,
                'common_name' => 'Black-winged lovebird',
                'alternate_names' => 'Abyssinian lovebird',
                'scientific_name' => 'Agapornis taranta',
                'species_group' => 'Non-eye ring',
                'has_eye_ring' => false,
                'description' => 'Highland species; males show a red forehead useful for visual sexing in records.',
                'scientific_source' => 'Forshaw, Parrots of the World; AGASSCOM A. taranta',
                'verification_status' => 'Verified',
            ],
            [
                'id' => 7,
                'common_name' => 'Red-headed lovebird',
                'alternate_names' => 'Red-faced lovebird',
                'scientific_name' => 'Agapornis pullarius',
                'species_group' => 'Non-eye ring',
                'has_eye_ring' => false,
                'description' => 'Vivid red face on a slender non-eye-ring bird, uncommon in aviaries.',
                'scientific_source' => 'Forshaw, Parrots of the World; AGASSCOM A. pullarius',
                'verification_status' => 'Verified',
            ],
            [
                'id' => 8,
                'common_name' => 'Grey-headed lovebird',
                'alternate_names' => 'Madagascar lovebird',
                'scientific_name' => 'Agapornis canus',
                'species_group' => 'Non-eye ring',
                'has_eye_ring' => false,
                'description' => 'Madagascar native; males wear a grey head that highlights sexual dimorphism.',
                'scientific_source' => 'Forshaw, Parrots of the World; AGASSCOM A. canus',
                'verification_status' => 'Verified',
            ],
            [
                'id' => 9,
                'common_name' => 'Black-collared lovebird',
                'alternate_names' => "Swindern's lovebird",
                'scientific_name' => 'Agapornis swindernianus',
                'species_group' => 'Non-eye ring',
                'has_eye_ring' => false,
                'description' => 'Forest species with a black collar band; rarely kept, completing the Agapornis set.',
                'scientific_source' => 'Forshaw, Parrots of the World; IUCN/BirdLife A. swindernianus',
                'verification_status' => 'Verified',
            ],
        ];

        foreach ($species as $item) {
            LovebirdSpecies::query()->updateOrCreate(
                ['id' => $item['id']],
                $item,
            );
        }
    }
}
