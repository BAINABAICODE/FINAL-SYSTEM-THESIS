<?php

namespace App\Support;

use RuntimeException;

class BaseColorDataset
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function records(string $directory): array
    {
        $files = glob($directory.DIRECTORY_SEPARATOR.'*.txt') ?: [];
        sort($files, SORT_STRING);

        $records = [];

        foreach ($files as $file) {
            $parsed = self::parseFile($file);
            foreach ($parsed as $record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseFile(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Unable to read base color dataset: {$path}");
        }

        if (! preg_match("/species VARCHAR\(\d+\) NOT NULL DEFAULT '([^']+)'/", $sql, $speciesMatch)) {
            throw new RuntimeException("Dataset file is missing a species default: {$path}");
        }

        if (! preg_match('/INSERT INTO \w+\s*\(([^)]+)\)\s*VALUES\s*(.+);/s', $sql, $insertMatch)) {
            throw new RuntimeException("Dataset file is missing an INSERT statement: {$path}");
        }

        $columns = array_map(
            static fn (string $column) => trim($column),
            explode(',', $insertMatch[1]),
        );

        $scientificName = $speciesMatch[1];
        $rows = self::splitTuples($insertMatch[2]);
        $records = [];

        foreach ($rows as $index => $tuple) {
            $values = self::splitQuotedValues($tuple);
            if (count($values) !== count($columns)) {
                throw new RuntimeException(
                    'Column count mismatch in '.basename($path).' row '.($index + 1)
                );
            }

            $row = array_combine($columns, $values);
            if ($row === false) {
                throw new RuntimeException("Unable to map dataset row in {$path}");
            }

            $records[] = [
                'scientific_name' => $scientificName,
                'name' => self::nullable($row['base_color'] ?? null),
                'series' => self::nullable($row['series'] ?? null),
                'sf' => self::nullable($row['sf'] ?? null),
                'df' => self::nullable($row['df'] ?? null),
                'allele' => self::nullable($row['allele'] ?? null),
                'genotype' => self::nullable($row['genotype'] ?? null),
                'genetic_code' => self::nullable($row['genetic_code'] ?? null),
                'inheritance_type' => self::nullable($row['inheritance_type'] ?? null),
                'phenotype' => self::nullable($row['phenotype'] ?? null),
                'verification_status' => self::nullable($row['verification_status'] ?? null),
                'scientific_source' => array_key_exists('scientific_source', $row)
                    ? self::nullable($row['scientific_source'])
                    : null,
                'computable' => array_key_exists('computable', $row)
                    ? ((int) $row['computable'] === 1)
                    : null,
                'sort_order' => $index + 1,
            ];
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    private static function splitTuples(string $valuesSql): array
    {
        $tuples = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
        $length = strlen($valuesSql);

        for ($i = 0; $i < $length; $i++) {
            $char = $valuesSql[$i];

            if ($char === "'" && $inString && ($valuesSql[$i + 1] ?? '') === "'") {
                $buffer .= "''";
                $i++;
                continue;
            }

            if ($char === "'") {
                $inString = ! $inString;
                $buffer .= $char;
                continue;
            }

            if (! $inString && $char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if (! $inString && $char === ')') {
                $depth--;
                $buffer .= $char;
                if ($depth === 0) {
                    $tuples[] = $buffer;
                    $buffer = '';
                }
                continue;
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $tuples;
    }

    /**
     * @return list<string|null>
     */
    private static function splitQuotedValues(string $tuple): array
    {
        $inner = trim($tuple);
        $inner = trim($inner, '()');
        $values = [];
        $buffer = '';
        $inString = false;
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];

            if ($char === "'" && $inString && ($inner[$i + 1] ?? '') === "'") {
                $buffer .= "'";
                $i++;
                continue;
            }

            if ($char === "'") {
                $inString = ! $inString;
                continue;
            }

            if (! $inString && $char === ',') {
                $values[] = self::castValue(trim($buffer));
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '' || $inString) {
            $values[] = self::castValue(trim($buffer));
        }

        return $values;
    }

    private static function castValue(string $value): ?string
    {
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        return $value;
    }

    private static function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
