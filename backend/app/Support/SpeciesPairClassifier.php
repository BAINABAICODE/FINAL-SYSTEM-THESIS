<?php

namespace App\Support;

use App\Models\SpeciesBreedingCompatibility;

class SpeciesPairClassifier
{
    public const EYE_RING = [2, 3, 4, 5];

    public const ROSEICOLLIS = 1;

    public const SWINDERNIANUS = 9;

    /**
     * @return array<string, string>
     */
    public function classify(int $left, int $right): array
    {
        $eyeLeft = in_array($left, self::EYE_RING, true);
        $eyeRight = in_array($right, self::EYE_RING, true);

        if ($left === self::SWINDERNIANUS || $right === self::SWINDERNIANUS) {
            return [
                'status' => SpeciesBreedingCompatibility::UNSUPPORTED,
                'breeding_type' => 'unsupported_forest_species',
                'fertility_status' => 'Not established in captivity',
                'risk_level' => 'HIGH',
                'warning' => 'Unsupported pairing. Agapornis swindernianus is almost never bred in captivity; no reliable interspecific program is stored.',
                'basis' => 'Black-collared lovebird remains a forest species with no documented aviary hybridization program in this dataset.',
                'source' => 'BirdLife / IUCN species account for A. swindernianus; Forshaw, Parrots of the World',
                'verification' => 'Verified — pairing unsupported',
            ];
        }

        if ($eyeLeft && $eyeRight) {
            return [
                'status' => SpeciesBreedingCompatibility::DOCUMENTED_HYBRID,
                'breeding_type' => 'eye_ring_hybrid',
                'fertility_status' => 'Fertile hybrids reported — not a purebred pairing',
                'risk_level' => 'HIGH',
                'warning' => 'Documented hybrid pairing among white eye-ring species. Offspring must not be treated as purebred, and RBGIA will not invent a shared allele set.',
                'basis' => 'A. fischeri, A. personatus, A. nigrigenis, and A. lilianae hybridize readily in captivity. This is a purity and conservation risk, not a Mendelian same-species cross.',
                'source' => 'Forshaw, Parrots of the World; avicultural reviews of the white eye-ring complex',
                'verification' => 'Verified — hybrid documented',
            ];
        }

        if ($this->isRoseicollisEyeRing($left, $right)) {
            $other = $left === self::ROSEICOLLIS ? $right : $left;
            $common = in_array($other, [2, 3], true);

            return [
                'status' => $common
                    ? SpeciesBreedingCompatibility::DOCUMENTED_HYBRID
                    : SpeciesBreedingCompatibility::LIMITED_OR_UNCERTAIN,
                'breeding_type' => $common ? 'roseicollis_eye_ring_hybrid' : 'roseicollis_eye_ring_uncertain',
                'fertility_status' => $common ? 'Hybrids reported in captivity' : 'Insufficient pairing evidence',
                'risk_level' => 'HIGH',
                'warning' => $common
                    ? 'Documented peach-faced × eye-ring hybrid reports exist. Do not treat offspring as a pure species, and do not run same-species RBGIA codes across this pair.'
                    : 'Limited evidence for peach-faced pairing with this eye-ring species. A definitive breeding prediction is not stored.',
                'basis' => 'Peach-faced lovebirds belong to the non-eye-ring group. Captive hybrids with Fischer’s or masked lovebirds are reported; other eye-ring crosses are less documented.',
                'source' => 'Avicultural hybrid reports; Forshaw species accounts. Not a genomic compatibility index.',
                'verification' => $common ? 'Verified — hybrid documented' : 'Limited evidence',
            ];
        }

        return [
            'status' => SpeciesBreedingCompatibility::LIMITED_OR_UNCERTAIN,
            'breeding_type' => 'limited_or_uncertain',
            'fertility_status' => 'Not sufficiently documented',
            'risk_level' => 'MODERATE',
            'warning' => 'Limited evidence. This species combination is not stored as a supported same-species pairing or as a well-documented hybrid program.',
            'basis' => 'Absence of a published, species-specific hybridization series for this pair. The gap is stored instead of inventing fertility.',
            'source' => 'No pair-specific source in the AGAPORA catalog; classified by exclusion from documented eye-ring and peach-faced hybrid reports',
            'verification' => 'Limited evidence',
        ];
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    public function uniquePairs(): array
    {
        $pairs = [];
        for ($left = 1; $left <= 9; $left++) {
            for ($right = $left + 1; $right <= 9; $right++) {
                $pairs[] = [$left, $right];
            }
        }

        return $pairs;
    }

    private function isRoseicollisEyeRing(int $left, int $right): bool
    {
        $ids = [$left, $right];

        return in_array(self::ROSEICOLLIS, $ids, true)
            && (in_array($ids[0], self::EYE_RING, true) || in_array($ids[1], self::EYE_RING, true));
    }
}
