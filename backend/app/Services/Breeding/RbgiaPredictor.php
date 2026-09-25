<?php

namespace App\Services\Breeding;

use App\Models\Bird;
use App\Services\Breeding\Rbgia\GeneticCodeParser;
use App\Services\Breeding\Rbgia\JointOffspringAssembler;
use App\Services\Breeding\Rbgia\LocusPunnett;
use App\Services\Breeding\Rbgia\PhenotypeFromGenotype;

/**
 * AGAPORA RBGIA — deterministic two-parent inheritance from stored genetic records.
 *
 * Pipeline:
 * Parent records → species-aware locus resolution → gametes → Punnett →
 * joint offspring distribution → phenotype mapping.
 *
 * Never invents alleles or wild-type fillers for missing parental genotypes.
 */
class RbgiaPredictor
{
    public function __construct(
        private readonly GeneticCodeParser $parser,
        private readonly LocusPunnett $punnett,
        private readonly JointOffspringAssembler $assembler,
        private readonly PhenotypeFromGenotype $phenotypes,
    ) {}

    /**
     * @param  array<string, mixed>  $validation
     * @return array<string, mixed>
     */
    public function predict(Bird $parentOne, Bird $parentTwo, array $validation): array
    {
        if (! ($validation['can_predict'] ?? false)) {
            return [
                'status' => 'blocked',
                'message' => 'RBGIA did not run because the pairing still has a blocking validation error.',
                'confidence' => 'INSUFFICIENT_DATA',
                'parent_profiles' => [
                    'parent_1' => $this->parentProfile($parentOne, 'Parent 1'),
                    'parent_2' => $this->parentProfile($parentTwo, 'Parent 2'),
                ],
                'outcomes' => [],
                'theoretical_distribution' => [],
                'punnett_squares' => [],
            ];
        }

        [$cock, $hen] = $this->resolveCockAndHen($parentOne, $parentTwo);
        $speciesId = (int) (($cock?->species_id) ?: ($hen?->species_id) ?: 0);

        $parentProfiles = [
            'parent_1' => $this->parentProfile($parentOne, 'Parent 1'),
            'parent_2' => $this->parentProfile($parentTwo, 'Parent 2'),
            'cock' => $cock ? $this->parentProfile($cock, 'Cock') : null,
            'hen' => $hen ? $this->parentProfile($hen, 'Hen') : null,
        ];

        if (! $cock || ! $hen) {
            return [
                'status' => 'insufficient_data',
                'message' => 'RBGIA requires one cock and one hen with known sexes for avian ZW inheritance.',
                'confidence' => 'INSUFFICIENT_DATA',
                'parent_profiles' => $parentProfiles,
                'outcomes' => [[
                    'category' => 'chromosomal_sex',
                    'name' => 'Chromosomal sex (ZW)',
                    'status' => 'not_calculated',
                    'reason' => 'Calculation unavailable. Reason: Required parental sex information is missing or parents are not a cock×hen pair.',
                    'results' => [],
                ]],
                'theoretical_distribution' => [],
                'punnett_squares' => [],
            ];
        }

        $outcomes = [];
        $outcomes = array_merge($outcomes, $this->baseColorLoci($cock, $hen, $speciesId));
        $outcomes = array_merge($outcomes, $this->visualMutationOutcomes($cock, $hen, $speciesId));
        $outcomes = array_merge($outcomes, $this->splitGeneOutcomes($cock, $hen, $speciesId));
        $outcomes = array_merge($outcomes, $this->chromosomalSexOutcome($cock, $hen, $outcomes));

        $joint = $this->assembler->assemble($outcomes);
        $theoretical = $this->decorateJoint($joint['joint_outcomes'], $speciesId);
        $punnettSquares = $this->punnettSquares($outcomes);

        $confidence = $joint['confidence'];
        if ($this->anyNeedsVerification($parentOne) || $this->anyNeedsVerification($parentTwo)) {
            $confidence = $confidence === 'INSUFFICIENT_DATA' ? $confidence : 'PARTIALLY_DETERMINED';
        }

        return [
            'status' => $joint['status'] === 'insufficient_data' ? 'insufficient_data' : 'completed',
            'message' => 'Predicted from the available genetic records and documented inheritance rules. Missing parental genotypes were not invented. Same parent inputs always yield the same deterministic result.',
            'provisional' => $confidence !== 'CONFIRMED',
            'confidence' => $confidence,
            'confidence_notes' => $joint['notes'],
            'parent_profiles' => $parentProfiles,
            'outcomes' => $outcomes,
            'theoretical_distribution' => $theoretical,
            'punnett_squares' => $punnettSquares,
            'methodology' => [
                'name' => 'AGAPORA-RBGIA-v2',
                'steps' => [
                    'parent_records',
                    'species_validation',
                    'genetic_data_resolution',
                    'parental_genotype_construction',
                    'allele_locus_analysis',
                    'gamete_generation',
                    'parent_cross',
                    'offspring_genotype_distribution',
                    'phenotype_distribution',
                ],
                'sex_chromosome_model' => 'avian_ZW',
                'randomness' => false,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baseColorLoci(Bird $cock, Bird $hen, int $speciesId): array
    {
        $left = $cock->baseColor;
        $right = $hen->baseColor;

        if (! $left || ! $right) {
            return [[
                'category' => 'base_color',
                'name' => 'Base Color',
                'locus_key' => 'base_color',
                'status' => 'not_calculated',
                'reason' => 'One or both parents have no stored base color. A wild-type genotype was not assumed.',
                'confidence' => 'unknown',
                'results' => [],
            ]];
        }

        if ((int) $left->lovebird_species_id !== (int) $right->lovebird_species_id) {
            return [[
                'category' => 'base_color',
                'name' => 'Base Color',
                'status' => 'not_calculated',
                'reason' => 'Parents reference base-color records from different species datasets. Cross-species allele transfer was not assumed.',
                'results' => [],
            ]];
        }

        $cockSegments = $this->parser->parseSegments($left->genetic_code);
        $henSegments = $this->parser->parseSegments($right->genetic_code);

        if ($cockSegments === [] || $henSegments === []) {
            return [[
                'category' => 'base_color',
                'name' => $left->name,
                'inheritance_type' => $left->inheritance_type,
                'genetic_code_cock' => $left->genetic_code,
                'genetic_code_hen' => $right->genetic_code,
                'status' => 'not_calculated',
                'reason' => 'Stored base-color genetic_code could not be parsed into diploid allele pairs (or is UNVERIFIED). No genotype was invented from the display name.',
                'results' => [],
            ]];
        }

        $outcomes = [];
        $pairCount = min(count($cockSegments), count($henSegments));
        for ($index = 0; $index < $pairCount; $index++) {
            $cockSeg = $cockSegments[$index];
            $henSeg = $henSegments[$index];
            $hint = $cockSeg['locus_hint'] !== 'locus' ? $cockSeg['locus_hint'] : $henSeg['locus_hint'];
            $name = match ($hint) {
                'dark_factor' => 'Dark factor (D)',
                'ground_color' => 'Ground color ('.$left->series.')',
                default => $left->name.($pairCount > 1 ? ' locus '.($index + 1) : ''),
            };

            $outcomes[] = $this->locusOutcome(
                category: 'base_color',
                name: $name,
                locusKey: $hint,
                inheritanceType: $hint === 'dark_factor' ? 'Intermediate dominant' : ($left->inheritance_type ?: $right->inheritance_type),
                cockCode: $cockSeg['segment'],
                henCode: $henSeg['segment'],
                cockAlleles: $cockSeg['alleles'],
                henAlleles: $henSeg['alleles'],
                verificationStatus: $left->verification_status,
                sexLinked: false,
                speciesId: $speciesId,
                parentContribution: [
                    'parent_cock' => ['record' => $left->name, 'code' => $cockSeg['segment'], 'confidence' => $this->recordConfidence($left->verification_status)],
                    'parent_hen' => ['record' => $right->name, 'code' => $henSeg['segment'], 'confidence' => $this->recordConfidence($right->verification_status)],
                ],
            );
        }

        if (count($cockSegments) !== count($henSegments)) {
            $outcomes[] = [
                'category' => 'base_color',
                'name' => 'Base-color locus alignment',
                'status' => 'not_calculated',
                'reason' => 'Parents have a different number of parseable genetic_code segments. Unaligned segments were not invented or force-matched.',
                'results' => [],
            ];
        }

        return $outcomes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visualMutationOutcomes(Bird $cock, Bird $hen, int $speciesId): array
    {
        $left = $this->indexedById($cock->visualMutations);
        $right = $this->indexedById($hen->visualMutations);
        $keys = array_values(array_unique(array_merge(array_keys($left), array_keys($right))));
        $outcomes = [];

        foreach ($keys as $key) {
            $record = $left[$key] ?? $right[$key];
            $leftCode = isset($left[$key]) ? $left[$key]->genetic_code : null;
            $rightCode = isset($right[$key]) ? $right[$key]->genetic_code : null;
            $sexLinked = $this->isSexLinked($record->inheritance_type);
            $assumedCock = false;
            $assumedHen = false;

            if ($leftCode === null) {
                $leftCode = $this->documentedNonCarrierCode($record, Bird::SEX_COCK);
                $assumedCock = $leftCode !== null;
            }
            if ($rightCode === null) {
                $rightCode = $this->documentedNonCarrierCode($record, Bird::SEX_HEN);
                $assumedHen = $rightCode !== null;
            }

            if ($leftCode === null || $rightCode === null) {
                $outcomes[] = [
                    'category' => 'visual_mutation',
                    'name' => $record->name,
                    'inheritance_type' => $record->inheritance_type,
                    'status' => 'not_calculated',
                    'reason' => 'Calculation unavailable. Missing parental genotype for this locus, and no documented wild-type/non-carrier allele is stored for the unselected parent.',
                    'confidence' => 'unknown',
                    'results' => [],
                ];
                continue;
            }

            if ((int) $record->lovebird_species_id !== $speciesId && $speciesId > 0) {
                $outcomes[] = [
                    'category' => 'visual_mutation',
                    'name' => $record->name,
                    'status' => 'not_calculated',
                    'reason' => 'Calculation unavailable. Mutation record is not aligned to the pair species dataset.',
                    'results' => [],
                ];
                continue;
            }

            $cockSegment = $sexLinked
                ? $this->parser->sexSpecificSegment($leftCode, Bird::SEX_COCK)
                : ($this->parser->parseSegments($leftCode)[0]['segment'] ?? $leftCode);
            $henSegment = $sexLinked
                ? $this->parser->sexSpecificSegment($rightCode, Bird::SEX_HEN)
                : ($this->parser->parseSegments($rightCode)[0]['segment'] ?? $rightCode);

            $cockAlleles = $this->parser->allelePair($cockSegment);
            $henAlleles = $this->parser->allelePair($henSegment);

            $outcome = $this->locusOutcome(
                category: 'visual_mutation',
                name: $record->name,
                locusKey: 'visual:'.$record->id,
                inheritanceType: $record->inheritance_type,
                cockCode: $cockSegment,
                henCode: $henSegment,
                cockAlleles: $cockAlleles,
                henAlleles: $henAlleles,
                verificationStatus: $record->verification_status,
                sexLinked: $sexLinked,
                speciesId: $speciesId,
                parentContribution: [
                    'parent_cock' => [
                        'record' => isset($left[$key]) ? $record->name : 'Documented non-carrier (not selected)',
                        'code' => $cockSegment,
                        'confidence' => $assumedCock ? 'probable' : $this->recordConfidence($record->verification_status),
                        'assumed_non_carrier' => $assumedCock,
                    ],
                    'parent_hen' => [
                        'record' => isset($right[$key]) ? $record->name : 'Documented non-carrier (not selected)',
                        'code' => $henSegment,
                        'confidence' => $assumedHen ? 'probable' : $this->recordConfidence($record->verification_status),
                        'assumed_non_carrier' => $assumedHen,
                    ],
                ],
            );
            if ($assumedCock || $assumedHen) {
                $outcome['provisional'] = true;
                $outcome['confidence'] = 'probable';
                $outcome['assumption_note'] = 'Unselected parent treated as documented non-carrier using the stored wild-type allele for this mutation/locus.';
            }
            $outcomes[] = $outcome;
        }

        return $outcomes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function splitGeneOutcomes(Bird $cock, Bird $hen, int $speciesId): array
    {
        $left = $this->indexedBySymbol($cock->splitGenes);
        $right = $this->indexedBySymbol($hen->splitGenes);
        $keys = array_values(array_unique(array_merge(array_keys($left), array_keys($right))));
        $outcomes = [];

        foreach ($keys as $key) {
            $record = $left[$key] ?? $right[$key];
            $sexLinked = $this->isSexLinked($record->inheritance_type);

            if ($sexLinked && $hen->splitGenes->contains(fn ($gene) => ($gene->mutant_allele ?: $gene->genetic_symbol) === $key)) {
                $outcomes[] = [
                    'category' => 'split_gene',
                    'name' => $record->name,
                    'inheritance_type' => $record->inheritance_type,
                    'status' => 'blocked',
                    'reason' => 'Calculation unavailable. A hen cannot be represented as a conventional hidden split for a Z-linked recessive gene under AGAPORA split-gene rules.',
                    'confidence' => 'confirmed_rule_block',
                    'results' => [],
                ];
                continue;
            }

            $leftCode = isset($left[$key]) ? $left[$key]->genetic_code : null;
            $rightCode = isset($right[$key]) ? $right[$key]->genetic_code : null;
            $assumedCock = false;
            $assumedHen = false;

            if ($leftCode === null) {
                $leftCode = $this->documentedNonCarrierCode($record, Bird::SEX_COCK);
                $assumedCock = $leftCode !== null;
            }
            if ($rightCode === null) {
                $rightCode = $this->documentedNonCarrierCode($record, Bird::SEX_HEN);
                $assumedHen = $rightCode !== null;
            }

            if ($leftCode === null || $rightCode === null) {
                $outcomes[] = [
                    'category' => 'split_gene',
                    'name' => $record->name,
                    'inheritance_type' => $record->inheritance_type,
                    'status' => 'not_calculated',
                    'reason' => 'Calculation unavailable. Missing parental genotype for this locus, and no documented wild-type allele is stored for the unselected parent.',
                    'confidence' => 'unknown',
                    'results' => [],
                ];
                continue;
            }

            $cockSegment = $sexLinked
                ? $this->parser->sexSpecificSegment($leftCode, Bird::SEX_COCK)
                : ($this->parser->parseSegments($leftCode)[0]['segment'] ?? $leftCode);
            $henSegment = $sexLinked
                ? $this->parser->sexSpecificSegment($rightCode, Bird::SEX_HEN)
                : ($this->parser->parseSegments($rightCode)[0]['segment'] ?? $rightCode);

            $cockAlleles = $this->parser->allelePair($cockSegment);
            $henAlleles = $this->parser->allelePair($henSegment);

            $outcome = $this->locusOutcome(
                category: 'split_gene',
                name: $record->name,
                locusKey: 'split:'.$key,
                inheritanceType: $record->inheritance_type,
                cockCode: $cockSegment,
                henCode: $henSegment,
                cockAlleles: $cockAlleles,
                henAlleles: $henAlleles,
                verificationStatus: $record->verification_status,
                sexLinked: $sexLinked,
                speciesId: $speciesId,
                parentContribution: [
                    'parent_cock' => [
                        'record' => isset($left[$key]) ? $record->name : 'Documented non-carrier (not selected)',
                        'code' => $cockSegment,
                        'confidence' => $assumedCock ? 'probable' : $this->recordConfidence($record->verification_status),
                        'assumed_non_carrier' => $assumedCock,
                    ],
                    'parent_hen' => [
                        'record' => isset($right[$key]) ? $record->name : 'Documented non-carrier (not selected)',
                        'code' => $henSegment,
                        'confidence' => $assumedHen ? 'probable' : $this->recordConfidence($record->verification_status),
                        'assumed_non_carrier' => $assumedHen,
                    ],
                ],
            );
            if ($assumedCock || $assumedHen) {
                $outcome['provisional'] = true;
                $outcome['confidence'] = 'probable';
                $outcome['assumption_note'] = 'Unselected parent treated as documented non-carrier using the stored wild-type allele for this gene/locus.';
            }
            $outcomes[] = $outcome;
        }

        return $outcomes;
    }

    /**
     * Avian chromosomal sex segregation (ZZ cock / ZW hen) when both parents have known sexes
     * and no sex-linked locus already tags offspring sex.
     *
     * @param  list<array<string, mixed>>  $outcomes
     * @return list<array<string, mixed>>
     */
    private function chromosomalSexOutcome(Bird $cock, Bird $hen, array $outcomes): array
    {
        if ($cock->sex !== Bird::SEX_COCK || $hen->sex !== Bird::SEX_HEN) {
            return [[
                'category' => 'chromosomal_sex',
                'name' => 'Chromosomal sex (ZW)',
                'locus_key' => 'chromosomal_sex',
                'status' => 'not_calculated',
                'reason' => 'Calculation unavailable. Required parental sex information is missing or not a cock×hen pair.',
                'results' => [],
            ]];
        }

        $sexLinkedCalculated = collect($outcomes)->contains(function ($outcome) {
            return is_array($outcome)
                && ($outcome['status'] ?? null) === 'calculated'
                && $this->isSexLinked($outcome['inheritance_type'] ?? null);
        });

        if ($sexLinkedCalculated) {
            // Sex already determined jointly with sex-linked mutation outcomes.
            return [];
        }

        return [[
            'category' => 'chromosomal_sex',
            'name' => 'Chromosomal sex (ZW)',
            'locus_key' => 'chromosomal_sex',
            'inheritance_type' => 'Avian ZW segregation',
            'genetic_code_cock' => 'Z/Z',
            'genetic_code_hen' => 'Z/W',
            'status' => 'calculated',
            'confidence' => 'confirmed',
            'parent_contribution' => [
                'parent_cock' => ['record' => 'Cock ZZ', 'code' => 'Z/Z', 'confidence' => 'confirmed'],
                'parent_hen' => ['record' => 'Hen ZW', 'code' => 'Z/W', 'confidence' => 'confirmed'],
            ],
            'punnett' => [
                'parent_1_alleles' => ['Z', 'Z'],
                'parent_2_alleles' => ['Z', 'W'],
                'sex_linked' => true,
            ],
            'results' => [
                [
                    'genotype' => 'ZZ',
                    'sex' => 'cock',
                    'count' => 1,
                    'total' => 2,
                    'fraction' => '1/2',
                    'probability' => 0.5,
                    'expression' => 'male',
                    'phenotype' => 'Male / Cock',
                    'inheritance_path' => [
                        'parent_1_gamete_pool' => 'Z from cock',
                        'parent_2_gamete_pool' => 'Z from hen',
                        'combination' => 'ZZ',
                    ],
                    'gamete_combinations' => [['from_cock_Z' => 'Z', 'from_hen_Z' => 'Z', 'sex' => 'cock']],
                ],
                [
                    'genotype' => 'ZW',
                    'sex' => 'hen',
                    'count' => 1,
                    'total' => 2,
                    'fraction' => '1/2',
                    'probability' => 0.5,
                    'expression' => 'female',
                    'phenotype' => 'Female / Hen',
                    'inheritance_path' => [
                        'parent_1_gamete_pool' => 'Z from cock',
                        'parent_2_gamete_pool' => 'W from hen',
                        'combination' => 'ZW',
                    ],
                    'gamete_combinations' => [['from_cock_Z' => 'Z', 'from_hen' => 'W', 'sex' => 'hen']],
                ],
            ],
        ]];
    }

    /**
     * Build a documented non-carrier genotype for an unselected parent using stored alleles only.
     * Never invents from prose allele descriptions.
     */
    private function documentedNonCarrierCode(object $record, string $sex): ?string
    {
        $sexLinked = $this->isSexLinked($record->inheritance_type ?? null);
        $wild = null;

        if (isset($record->wild_type_allele) && is_string($record->wild_type_allele) && trim($record->wild_type_allele) !== '') {
            $wild = trim($record->wild_type_allele);
        }

        if ($wild === null) {
            $wild = $this->wildAlleleFromGeneticCode($record->genetic_code ?? null);
        }

        if ($wild === null) {
            $mutant = $record->mutant_allele ?? $record->genetic_symbol ?? null;
            if (is_string($mutant) && preg_match('/^[A-Za-z][A-Za-z0-9*_+.-]*$/', trim($mutant)) === 1) {
                $mutant = trim($mutant);
                if (! str_ends_with($mutant, '+') && strcasecmp($mutant, 'UNVERIFIED') !== 0) {
                    $wild = $mutant.'+';
                }
            }
        }

        if (! is_string($wild) || trim($wild) === '') {
            return null;
        }

        $wild = trim($wild);
        if ($sexLinked) {
            return $sex === Bird::SEX_HEN ? $wild.'/W' : $wild.'/'.$wild;
        }

        return $wild.'/'.$wild;
    }

    /**
     * Extract a wild-type allele symbol from a stored genetic_code (e.g. dil+/dil, Pi+/Pi, op/op|op/W).
     */
    private function wildAlleleFromGeneticCode(?string $code): ?string
    {
        $segments = $this->parser->parseSegments($code);
        if ($segments === []) {
            return null;
        }

        foreach ($segments as $segment) {
            foreach ($segment['alleles'] as $allele) {
                if (strcasecmp($allele, 'W') === 0) {
                    continue;
                }
                if (str_ends_with($allele, '+')) {
                    return $allele;
                }
            }
        }

        foreach ($segments as $segment) {
            foreach ($segment['alleles'] as $allele) {
                if (strcasecmp($allele, 'W') === 0 || $allele === '') {
                    continue;
                }
                if (! str_ends_with($allele, '+') && preg_match('/^[A-Za-z][A-Za-z0-9*_+.-]*$/', $allele) === 1) {
                    return $allele.'+';
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: ?Bird, 1: ?Bird}
     */
    private function resolveCockAndHen(Bird $parentOne, Bird $parentTwo): array
    {
        $cock = null;
        $hen = null;

        if ($parentOne->sex === Bird::SEX_COCK) {
            $cock = $parentOne;
        } elseif ($parentTwo->sex === Bird::SEX_COCK) {
            $cock = $parentTwo;
        }

        if ($parentOne->sex === Bird::SEX_HEN) {
            $hen = $parentOne;
        } elseif ($parentTwo->sex === Bird::SEX_HEN) {
            $hen = $parentTwo;
        }

        if ($cock && $hen && $cock->id !== $hen->id) {
            return [$cock, $hen];
        }

        return [null, null];
    }

    /**
     * @param  array{0: string, 1: string}|null  $cockAlleles
     * @param  array{0: string, 1: string}|null  $henAlleles
     * @param  array<string, mixed>  $parentContribution
     * @return array<string, mixed>
     */
    private function locusOutcome(
        string $category,
        string $name,
        string $locusKey,
        ?string $inheritanceType,
        ?string $cockCode,
        ?string $henCode,
        ?array $cockAlleles,
        ?array $henAlleles,
        ?string $verificationStatus,
        bool $sexLinked,
        int $speciesId,
        array $parentContribution,
    ): array {
        if ($cockAlleles === null || $henAlleles === null) {
            return [
                'category' => $category,
                'name' => $name,
                'locus_key' => $locusKey,
                'inheritance_type' => $inheritanceType,
                'genetic_code_cock' => $cockCode,
                'genetic_code_hen' => $henCode,
                'status' => 'not_calculated',
                'reason' => 'The stored genetic code could not be read as a genotype pair. No genotype was generated from the gene name.',
                'confidence' => 'unknown',
                'parent_contribution' => $parentContribution,
                'results' => [],
            ];
        }

        $results = $this->punnett->cross($cockAlleles, $henAlleles, $sexLinked, (string) $inheritanceType);
        foreach ($results as &$row) {
            $mapped = $this->phenotypes->forLocus([
                'category' => $category,
                'name' => $name,
                'locus_key' => $locusKey,
                'genotype' => $row['genotype'],
                'expression' => $row['expression'] ?? null,
            ], $speciesId);
            $row['phenotype'] = $mapped['phenotype'];
            $row['base_color'] = $mapped['base_color'];
            $row['visual_mutations'] = $mapped['visual_mutations'];
            $row['split_hidden'] = $mapped['split_hidden'];
            $row['phenotype_note'] = $mapped['note'];
        }
        unset($row);

        return [
            'category' => $category,
            'name' => $name,
            'locus_key' => $locusKey,
            'inheritance_type' => $inheritanceType,
            'genetic_code_cock' => $cockCode,
            'genetic_code_hen' => $henCode,
            'verification_status' => $verificationStatus,
            'status' => 'calculated',
            'confidence' => $this->recordConfidence($verificationStatus),
            'provisional' => $this->needsVerification($verificationStatus),
            'parent_contribution' => $parentContribution,
            'punnett' => [
                'parent_1_alleles' => $cockAlleles,
                'parent_2_alleles' => $henAlleles,
                'sex_linked' => $sexLinked,
                'results' => $results,
            ],
            'results' => $results,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $joint
     * @return list<array<string, mixed>>
     */
    private function decorateJoint(array $joint, int $speciesId): array
    {
        return array_map(function (array $row) use ($speciesId) {
            $mapped = $this->phenotypes->forJoint($row['loci'] ?? [], $speciesId);
            $row['phenotype'] = $mapped['phenotype'];
            $row['base_color'] = $mapped['base_color'];
            $row['base_color_genotype'] = $mapped['base_color_genotype'] ?? null;
            $row['dark_factor'] = $mapped['dark_factor'] ?? null;
            $row['visual_mutations'] = $mapped['visual_mutations'];
            $row['split_hidden_genes'] = $mapped['split_hidden'];
            $row['sex_label'] = match ($row['sex'] ?? null) {
                'cock', 'male' => 'Male / Cock',
                'hen', 'female' => 'Female / Hen',
                default => null,
            };
            $row['inherited_from'] = [
                'paths' => $row['inheritance_paths'] ?? [],
            ];

            return $row;
        }, $joint);
    }

    /**
     * @param  list<array<string, mixed>>  $outcomes
     * @return list<array<string, mixed>>
     */
    private function punnettSquares(array $outcomes): array
    {
        $squares = [];
        foreach ($outcomes as $outcome) {
            if (($outcome['status'] ?? null) !== 'calculated') {
                continue;
            }
            $squares[] = [
                'locus' => $outcome['name'] ?? null,
                'category' => $outcome['category'] ?? null,
                'inheritance_type' => $outcome['inheritance_type'] ?? null,
                'parent_1_alleles' => $outcome['punnett']['parent_1_alleles'] ?? null,
                'parent_2_alleles' => $outcome['punnett']['parent_2_alleles'] ?? null,
                'sex_linked' => $outcome['punnett']['sex_linked'] ?? false,
                'results' => $outcome['results'] ?? [],
            ];
        }

        return $squares;
    }

    /**
     * @return array<string, mixed>
     */
    private function parentProfile(Bird $bird, string $label): array
    {
        $grandparents = [];
        foreach ($bird->grandparents ?? [] as $grandparent) {
            $grandparents[] = [
                'relation' => $grandparent->relation ?? $grandparent->side ?? null,
                'bird_id' => $grandparent->bird_id ?? null,
                'notes' => $grandparent->notes ?? null,
                'confidence' => 'evidence_only',
                'usage' => 'Grandparent rows are retained as evidence only. They do not invent a parental genotype.',
            ];
        }

        return [
            'label' => $label,
            'bird_id' => $bird->bird_id,
            'sex' => $bird->sex,
            'species_id' => $bird->species_id,
            'species' => $bird->species?->common_name,
            'base_color' => $bird->baseColor?->name,
            'base_color_code' => $bird->baseColor?->genetic_code,
            'visual_mutations' => $bird->visualMutations?->pluck('name')->values()->all() ?? [],
            'split_genes' => $bird->splitGenes?->pluck('name')->values()->all() ?? [],
            'grandparents' => $grandparents,
            'confidence' => [
                'base_color' => $this->recordConfidence($bird->baseColor?->verification_status),
                'genotypes_present' => (bool) $bird->baseColor?->genetic_code,
            ],
        ];
    }

    private function recordConfidence(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'unknown';
        }
        if (stripos($status, 'Needs Verification') !== false) {
            return 'probable';
        }
        if (stripos($status, 'Verified') !== false) {
            return 'confirmed';
        }

        return 'probable';
    }

    private function isSexLinked(?string $inheritanceType): bool
    {
        return $inheritanceType !== null && stripos($inheritanceType, 'Sex-linked') !== false;
    }

    private function needsVerification(?string $status): bool
    {
        return $status !== null && stripos($status, 'Needs Verification') !== false;
    }

    private function anyNeedsVerification(Bird $bird): bool
    {
        $records = collect([$bird->baseColor])
            ->merge($bird->visualMutations ?? [])
            ->merge($bird->splitGenes ?? []);

        return $records->contains(fn ($record) => $record && $this->needsVerification($record->verification_status));
    }

    /**
     * @param  mixed  $records
     * @return array<string, mixed>
     */
    private function indexedById($records): array
    {
        $indexed = [];
        foreach ($records ?? [] as $record) {
            $indexed['id:'.$record->id] = $record;
        }

        return $indexed;
    }

    /**
     * @param  mixed  $records
     * @return array<string, mixed>
     */
    private function indexedBySymbol($records): array
    {
        $indexed = [];
        foreach ($records ?? [] as $record) {
            $key = $record->mutant_allele ?: $record->genetic_symbol ?: ('id:'.$record->id);
            $indexed[$key] = $record;
        }

        return $indexed;
    }
}
