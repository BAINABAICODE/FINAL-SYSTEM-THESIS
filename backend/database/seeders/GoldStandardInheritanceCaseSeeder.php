<?php

namespace Database\Seeders;

use App\Models\GoldStandardInheritanceCase;
use Illuminate\Database\Seeder;

/**
 * Hand-computed Punnett answers for SOP 3.2.
 * Codes are taken from the A. roseicollis catalog. Do not invent alleles.
 */
class GoldStandardInheritanceCaseSeeder extends Seeder
{
    public function run(): void
    {
        $cases = [
            [
                'case_key' => 'roseicollis-green-x-green',
                'lovebird_species_id' => 1,
                'locus_name' => 'Ground color (bl)',
                'inheritance_type' => 'Wild type',
                'cock_code' => 'bl+/bl+',
                'hen_code' => 'bl+/bl+',
                'sex_linked' => false,
                'expected_outcomes' => [
                    ['genotype' => 'bl+/bl+', 'fraction' => '1/1', 'probability' => 1.0, 'sex' => 'both'],
                ],
                'hand_computation' => 'Both parents homozygous wild type. All four Punnett cells are bl+/bl+.',
                'scientific_source' => 'Mendelian wild-type cross; MUTAVI bl+ in A. roseicollis',
                'verification_status' => 'Verified',
                'notes' => 'Use the Green catalog row genetic_code first segment only.',
            ],
            [
                'case_key' => 'roseicollis-aqua-x-green',
                'lovebird_species_id' => 1,
                'locus_name' => 'Ground color (bl)',
                'inheritance_type' => 'Autosomal recessive',
                'cock_code' => 'blaq/blaq',
                'hen_code' => 'bl+/bl+',
                'sex_linked' => false,
                'expected_outcomes' => [
                    ['genotype' => 'bl+/blaq', 'fraction' => '1/1', 'probability' => 1.0, 'sex' => 'both'],
                ],
                'hand_computation' => 'Aqua (blaq/blaq) × Green (bl+/bl+). Every gamete pair is bl+ from green and blaq from aqua. All offspring are split aqua, not visual aqua.',
                'scientific_source' => 'MUTAVI aqua autosomal recessive; symbol bl*aq',
                'verification_status' => 'Verified',
                'notes' => 'Phenotype of bl+/blaq is green (carrier). Visual aqua requires blaq/blaq.',
            ],
            [
                'case_key' => 'roseicollis-aqua-x-aqua',
                'lovebird_species_id' => 1,
                'locus_name' => 'Ground color (bl)',
                'inheritance_type' => 'Autosomal recessive',
                'cock_code' => 'blaq/blaq',
                'hen_code' => 'blaq/blaq',
                'sex_linked' => false,
                'expected_outcomes' => [
                    ['genotype' => 'blaq/blaq', 'fraction' => '1/1', 'probability' => 1.0, 'sex' => 'both'],
                ],
                'hand_computation' => 'Both parents homozygous recessive. All four cells are visual aqua.',
                'scientific_source' => 'MUTAVI aqua autosomal recessive; symbol bl*aq',
                'verification_status' => 'Verified',
                'notes' => null,
            ],
            [
                'case_key' => 'roseicollis-dark-green-x-green',
                'lovebird_species_id' => 1,
                'locus_name' => 'Dark factor (D)',
                'inheritance_type' => 'Incomplete dominant',
                'cock_code' => 'D+/D',
                'hen_code' => 'D+/D+',
                'sex_linked' => false,
                'expected_outcomes' => [
                    ['genotype' => 'D+/D+', 'fraction' => '1/2', 'probability' => 0.5, 'sex' => 'both'],
                    ['genotype' => 'D+/D', 'fraction' => '1/2', 'probability' => 0.5, 'sex' => 'both'],
                ],
                'hand_computation' => 'Single dark factor × no dark factor. Two cells D+/D+ (green) and two cells D+/D (dark green). No olive (D/D).',
                'scientific_source' => 'MUTAVI incomplete-dominant dark factor D',
                'verification_status' => 'Verified',
                'notes' => 'Dark Green catalog row is bl+/bl+|D+/D. Use the D segment.',
            ],
            [
                'case_key' => 'roseicollis-bronze-fallow-visual-x-noncarrier',
                'lovebird_species_id' => 1,
                'locus_name' => 'Bronze Fallow',
                'inheritance_type' => 'Autosomal recessive',
                'cock_code' => 'a*bz/a*bz',
                'hen_code' => 'a+/a+',
                'sex_linked' => false,
                'expected_outcomes' => [
                    ['genotype' => 'a+/a*bz', 'fraction' => '1/1', 'probability' => 1.0, 'sex' => 'both'],
                ],
                'hand_computation' => 'Visual fallow × documented non-carrier. All offspring are heterozygous carriers (split bronze fallow). None are visual.',
                'scientific_source' => 'MUTAVI bronze fallow; symbol a*bz',
                'verification_status' => 'Verified',
                'notes' => 'Non-carrier code is the documented wild-type pair, not an invented filler.',
            ],
            [
                'case_key' => 'roseicollis-sl-ino-split-cock-x-wild-hen',
                'lovebird_species_id' => 1,
                'locus_name' => 'SL Ino',
                'inheritance_type' => 'Sex-linked recessive',
                'cock_code' => 'ino+/ino',
                'hen_code' => 'ino+/W',
                'sex_linked' => true,
                'expected_outcomes' => [
                    ['genotype' => 'ino+/ino+', 'fraction' => '1/4', 'probability' => 0.25, 'sex' => 'cock'],
                    ['genotype' => 'ino+/ino', 'fraction' => '1/4', 'probability' => 0.25, 'sex' => 'cock'],
                    ['genotype' => 'ino+/W', 'fraction' => '1/4', 'probability' => 0.25, 'sex' => 'hen'],
                    ['genotype' => 'ino/W', 'fraction' => '1/4', 'probability' => 0.25, 'sex' => 'hen'],
                ],
                'hand_computation' => 'Split cock (ino+/ino) × wild hen (ino+/W). Sons: half wild, half split. Daughters: half wild (ino+/W), half visual ino (ino/W).',
                'scientific_source' => 'Avian ZW; MUTAVI SL ino; symbol ino',
                'verification_status' => 'Verified',
                'notes' => 'Classic sex-linked check case for SOP 2.3.',
            ],
            [
                'case_key' => 'roseicollis-sl-ino-visual-cock-x-wild-hen',
                'lovebird_species_id' => 1,
                'locus_name' => 'SL Ino',
                'inheritance_type' => 'Sex-linked recessive',
                'cock_code' => 'ino/ino',
                'hen_code' => 'ino+/W',
                'sex_linked' => true,
                'expected_outcomes' => [
                    ['genotype' => 'ino+/ino', 'fraction' => '1/2', 'probability' => 0.5, 'sex' => 'cock'],
                    ['genotype' => 'ino/W', 'fraction' => '1/2', 'probability' => 0.5, 'sex' => 'hen'],
                ],
                'hand_computation' => 'Visual ino cock × wild hen. Every son is split (ino+/ino). Every daughter is visual hemizygous (ino/W).',
                'scientific_source' => 'Avian ZW; MUTAVI SL ino; symbol ino',
                'verification_status' => 'Verified',
                'notes' => 'Sons cannot be visual from this pairing because they receive the hen wild-type Z.',
            ],
        ];

        $kept = [];

        foreach ($cases as $case) {
            $record = GoldStandardInheritanceCase::query()->updateOrCreate(
                ['case_key' => $case['case_key']],
                $case,
            );
            $kept[] = $record->id;
        }

        GoldStandardInheritanceCase::query()
            ->whereNotIn('id', $kept)
            ->delete();
    }
}
