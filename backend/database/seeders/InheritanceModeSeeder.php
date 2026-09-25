<?php

namespace Database\Seeders;

use App\Models\InheritanceMode;
use Illuminate\Database\Seeder;

/**
 * Rule book for RBGIA. Each row is a Punnett mode, not a species trait.
 * Codes must stay aligned with stored inheritance_type strings in the catalogs.
 */
class InheritanceModeSeeder extends Seeder
{
    public function run(): void
    {
        $modes = [
            [
                'code' => 'WILD_TYPE',
                'name' => 'Wild type',
                'chromosome_model' => 'autosomal',
                'sex_linked' => false,
                'punnett_rule' => 'Diploid 2×2. If both parents are homozygous wild type, every cell is the wild-type pair.',
                'expression_rule' => 'No mutant allele is present. Phenotype is the species ancestral look.',
                'scientific_basis' => 'Mendelian wild-type baseline before a mutant allele is introduced.',
                'scientific_source' => 'Mendel, Experiments on Plant Hybridization; applied as the unmarked allele in avian locus notation',
                'verification_status' => 'Verified',
                'sort_order' => 1,
            ],
            [
                'code' => 'AUTOSOMAL_RECESSIVE',
                'name' => 'Autosomal recessive',
                'chromosome_model' => 'autosomal',
                'sex_linked' => false,
                'punnett_rule' => 'Diploid 2×2. P(genotype) = n/4. Visual only when both alleles are mutant.',
                'expression_rule' => 'm/m visual; m+/m carrier (split); m+/m+ non-carrier.',
                'scientific_basis' => 'Law of Segregation. Heterozygote does not show the recessive phenotype.',
                'scientific_source' => 'Mendelian segregation; MUTAVI autosomal-recessive lovebird loci',
                'verification_status' => 'Verified',
                'sort_order' => 2,
            ],
            [
                'code' => 'AUTOSOMAL_DOMINANT',
                'name' => 'Autosomal dominant',
                'chromosome_model' => 'autosomal',
                'sex_linked' => false,
                'punnett_rule' => 'Diploid 2×2. One mutant allele is sufficient for a visual class.',
                'expression_rule' => 'Any cell with a mutant allele is visual. Homozygous mutant is visual_homozygous.',
                'scientific_basis' => 'Dominant allele masks the wild-type allele in the heterozygote.',
                'scientific_source' => 'Mendelian dominance; MUTAVI dominant pied (Pi) in A. roseicollis',
                'verification_status' => 'Verified',
                'sort_order' => 3,
            ],
            [
                'code' => 'INCOMPLETE_DOMINANT',
                'name' => 'Incomplete / intermediate dominant',
                'chromosome_model' => 'autosomal',
                'sex_linked' => false,
                'punnett_rule' => 'Diploid 2×2. Heterozygote and homozygote are different visual classes (single vs double factor).',
                'expression_rule' => 'Two mutant alleles: visual_double. One mutant + wild type: visual_single_factor. None: non-carrier. Cannot be stored as a split.',
                'scientific_basis' => 'Incomplete dominance. Dosage of D, V, Ph, or Gf is visible.',
                'scientific_source' => 'MUTAVI incomplete-dominant dark factor, violet, pale headed, grey factor',
                'verification_status' => 'Verified',
                'sort_order' => 4,
            ],
            [
                'code' => 'ALLELIC_COMPOUND',
                'name' => 'Allelic compound',
                'chromosome_model' => 'autosomal',
                'sex_linked' => false,
                'punnett_rule' => 'Diploid 2×2 at one locus with two different mutant alleles (for example blaq/bltq).',
                'expression_rule' => 'Compound heterozygote is a documented visual (Seagreen / AquaTurquoise), not a split.',
                'scientific_basis' => 'Two alleles of the same locus combine. Not an independent second locus.',
                'scientific_source' => 'MUTAVI bl locus; aqua + turquoise compound in A. roseicollis',
                'verification_status' => 'Verified',
                'sort_order' => 5,
            ],
            [
                'code' => 'SEX_LINKED_RECESSIVE',
                'name' => 'Sex-linked recessive (avian ZW)',
                'chromosome_model' => 'avian_ZW',
                'sex_linked' => true,
                'punnett_rule' => 'Cock ZZ contributes one Z per gamete. Hen ZW: sons get hen Z, daughters get W. Each sex-linked row is half of the locus total.',
                'expression_rule' => 'Hen with mutant Z is visual_hemizygous. Cock needs two mutant Z alleles to be visual; one mutant Z is a split cock. A hen cannot be stored as a hidden Z-linked split.',
                'scientific_basis' => 'Chromosome theory of inheritance on avian ZW. Traits on Z follow the sex of the chick.',
                'scientific_source' => 'Sutton–Boveri chromosome theory; avian ZW; MUTAVI SL ino, cinnamon, opaline, pallid',
                'verification_status' => 'Verified',
                'sort_order' => 6,
            ],
            [
                'code' => 'CHROMOSOMAL_SEX',
                'name' => 'Chromosomal sex',
                'chromosome_model' => 'avian_ZW',
                'sex_linked' => true,
                'punnett_rule' => 'Cock ZZ × hen ZW gives 1/2 ZZ sons and 1/2 ZW daughters when parental sexes are known.',
                'expression_rule' => 'Sex is a chromosome outcome, not a color mutation.',
                'scientific_basis' => 'Birds are female-heterogametic (ZW). RBGIA requires one cock and one hen.',
                'scientific_source' => 'Avian sex-chromosome system ZW (standard ornithology)',
                'verification_status' => 'Verified',
                'sort_order' => 7,
            ],
        ];

        foreach ($modes as $mode) {
            InheritanceMode::query()->updateOrCreate(
                ['code' => $mode['code']],
                $mode,
            );
        }
    }
}
