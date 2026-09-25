<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LovebirdSpeciesSeeder::class,
            InheritanceModeSeeder::class,
            BaseColorSeeder::class,
            VisualMutationSeeder::class,
            SplitGeneSeeder::class,
            SpeciesBreedingCompatibilitySeeder::class,
            BreedingSafetyRuleSeeder::class,
            GoldStandardInheritanceCaseSeeder::class,
        ]);
    }
}
