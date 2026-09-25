<?php

namespace App\Services\Breeding\Rbgia;

/**
 * Parses AGAPORA stored genetic_code strings into diploid allele pairs.
 * Supports pipe-separated multi-locus codes (e.g. bl+/bl+|D+/D+).
 * Never invents alleles.
 */
class GeneticCodeParser
{
    /**
     * @return list<array{segment: string, alleles: array{0: string, 1: string}, locus_hint: string}>
     */
    public function parseSegments(?string $code): array
    {
        if ($code === null || trim($code) === '' || strcasecmp(trim($code), 'UNVERIFIED') === 0) {
            return [];
        }

        $segments = [];
        foreach (array_map('trim', explode('|', $code)) as $segment) {
            if ($segment === '') {
                continue;
            }
            $alleles = $this->allelePair($segment);
            if ($alleles === null) {
                continue;
            }
            $segments[] = [
                'segment' => $segment,
                'alleles' => $alleles,
                'locus_hint' => $this->locusHint($alleles),
            ];
        }

        return $segments;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function allelePair(?string $segment): ?array
    {
        if ($segment === null || trim($segment) === '') {
            return null;
        }

        $parts = array_map('trim', explode('/', $segment));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Prefer sex-appropriate segment from a multi-part sex-linked code.
     */
    public function sexSpecificSegment(?string $code, string $sex): ?string
    {
        $segments = $this->parseSegments($code);
        if ($segments === []) {
            return null;
        }

        if (strcasecmp($sex, 'hen') === 0 || strcasecmp($sex, 'female') === 0) {
            foreach ($segments as $segment) {
                if ($this->containsW($segment['alleles'])) {
                    return $segment['segment'];
                }
            }
        }

        foreach ($segments as $segment) {
            if (! $this->containsW($segment['alleles'])) {
                return $segment['segment'];
            }
        }

        return $segments[0]['segment'];
    }

    /**
     * @param  array{0: string, 1: string}  $alleles
     */
    public function containsW(array $alleles): bool
    {
        return strcasecmp($alleles[0], 'W') === 0 || strcasecmp($alleles[1], 'W') === 0;
    }

    /**
     * @param  array{0: string, 1: string}  $alleles
     */
    public function zAllele(array $alleles): string
    {
        foreach ($alleles as $allele) {
            if (strcasecmp($allele, 'W') !== 0) {
                return $allele;
            }
        }

        return $alleles[0];
    }

    public function pairLabel(string $left, string $right): string
    {
        $pair = [$left, $right];
        usort($pair, function (string $a, string $b) {
            $rankA = str_ends_with($a, '+') ? 0 : (strcasecmp($a, 'W') === 0 ? 2 : 1);
            $rankB = str_ends_with($b, '+') ? 0 : (strcasecmp($b, 'W') === 0 ? 2 : 1);

            return $rankA <=> $rankB ?: strcmp($a, $b);
        });

        return $pair[0].'/'.$pair[1];
    }

    /**
     * @param  array{0: string, 1: string}  $alleles
     */
    private function locusHint(array $alleles): string
    {
        foreach ($alleles as $allele) {
            if (strcasecmp($allele, 'W') === 0) {
                continue;
            }
            if (preg_match('/^D\\+?$/i', $allele)) {
                return 'dark_factor';
            }
            if (preg_match('/^bl/i', $allele) || preg_match('/^aq/i', $allele) || preg_match('/^tq/i', $allele)) {
                return 'ground_color';
            }
        }

        return 'locus';
    }
}
