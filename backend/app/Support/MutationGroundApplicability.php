<?php

namespace App\Support;

use App\Models\BaseColor;

/**
 * A visual mutation is blocked when the selected ground removes the pigment
 * that mutation changes, or when a selected allele covers it. Blue has no
 * facial psittacin, so Orange Face cannot show. Ino covers Violet. The same
 * face allele on green, aqua, or turquoise stays available.
 */
class MutationGroundApplicability
{
    public static function message(
        ?string $series,
        ?string $baseName,
        ?string $mutationSeries,
        ?string $mutationName,
        ?string $phenotype,
    ): ?string {
        if (! self::isBlueGround($series) || ! self::changesFacialPsittacin($mutationSeries, $phenotype)) {
            return null;
        }

        $mutationName = $mutationName !== null && $mutationName !== '' ? $mutationName : 'This mutation';
        $baseName = $baseName !== null && $baseName !== '' ? $baseName : 'Blue';

        return $mutationName.' is not a visible mutation on '.$baseName.'. A blue bird has no facial pigment for this mutation to change.';
    }

    /**
     * @param  iterable<int, object>  $mutations
     */
    public static function firstForMutations(?BaseColor $color, iterable $mutations): ?string
    {
        if ($color !== null) {
            foreach ($mutations as $mutation) {
                $message = self::message(
                    $color->series,
                    $color->name,
                    $mutation->series ?? null,
                    $mutation->name ?? null,
                    $mutation->phenotype ?? null,
                );

                if ($message !== null) {
                    return $message;
                }
            }
        }

        return self::epistasisMessage($mutations);
    }

    /**
     * Ino covers a violet wash. Pallid and Pale do not.
     *
     * @param  iterable<int, object>  $mutations
     */
    private static function epistasisMessage(iterable $mutations): ?string
    {
        $inoName = null;
        $violetName = null;

        foreach ($mutations as $mutation) {
            $name = (string) ($mutation->name ?? '');
            if (self::isInoAllele($name)) {
                $inoName = $name;
            }
            if (self::isVioletDose($name)) {
                $violetName = $name;
            }
        }

        if ($inoName === null || $violetName === null) {
            return null;
        }

        return $violetName.' is not a visible mutation with '.$inoName.'. Ino covers the violet wash, so the bird shows '.$inoName.'.';
    }

    private static function isInoAllele(string $name): bool
    {
        $key = strtolower(trim($name));

        return $key === 'sl ino' || $key === 'nsl ino';
    }

    private static function isVioletDose(string $name): bool
    {
        $key = strtolower(trim($name));

        return $key === 'violet' || $key === 'double violet';
    }

    private static function isBlueGround(?string $series): bool
    {
        return strtolower(trim((string) $series)) === 'blue';
    }

    private static function changesFacialPsittacin(?string $series, ?string $phenotype): bool
    {
        if (strtolower(trim((string) $series)) === 'psittacin') {
            return true;
        }

        return preg_match('/facial psittacin|red mask|facial effect/i', (string) $phenotype) === 1;
    }
}
