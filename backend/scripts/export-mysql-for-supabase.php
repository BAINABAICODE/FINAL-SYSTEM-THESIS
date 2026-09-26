<?php

/**
 * Copy the current MySQL database into PostgreSQL scripts for Supabase.
 *
 * Usage, from the backend directory:
 *   php scripts/export-mysql-for-supabase.php
 *
 * Writes backend/database/supabase/*.sql. Those files contain account data
 * and are gitignored. Run them in filename order in the Supabase SQL editor,
 * connected as the postgres role.
 */

ini_set('memory_limit', '512M');

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$connection = Illuminate\Support\Facades\DB::connection();

if ($connection->getDriverName() !== 'mysql') {
    fwrite(STDERR, "The default connection is not MySQL. Point .env at the local agapora database first.\n");
    exit(1);
}

$skipData = [
    'cache' => true,
    'cache_locks' => true,
    'failed_jobs' => true,
    'job_batches' => true,
    'jobs' => true,
    'password_reset_tokens' => true,
    'sessions' => true,
];

$schema = $connection->getDatabaseName();
$tables = tableNames($connection, $schema);
$columns = columnsByTable($connection, $schema);
$indexes = indexesByTable($connection, $schema);
$foreignKeys = foreignKeys($connection, $schema);

$outputDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'supabase';
if (! is_dir($outputDir) && ! mkdir($outputDir, 0777, true) && ! is_dir($outputDir)) {
    fwrite(STDERR, "Could not create {$outputDir}\n");
    exit(1);
}

foreach (glob($outputDir.DIRECTORY_SEPARATOR.'*.sql') ?: [] as $existing) {
    unlink($existing);
}

$schemaSql = "-- AGAPORA schema for Supabase. Run this file first.\n";
$schemaSql .= "BEGIN;\n";
$schemaSql .= "CREATE SCHEMA IF NOT EXISTS public;\n";
$schemaSql .= "SET search_path TO public;\n\n";

foreach (array_reverse($tables) as $table) {
    $schemaSql .= 'DROP TABLE IF EXISTS '.pgIdent($table)." CASCADE;\n";
}

$schemaSql .= "\n";

foreach ($tables as $table) {
    $schemaSql .= createTableSql($table, $columns[$table] ?? []);
    $schemaSql .= "\n";
    $constraintNames = [];
    foreach ($foreignKeys as $foreignKey) {
        $constraintNames[$foreignKey['name']] = true;
    }

    foreach ($indexes[$table] ?? [] as $index) {
        $indexName = $index['name'];
        if (isset($constraintNames[$indexName])) {
            $indexName .= '_idx';
        }
        $schemaSql .= createIndexSql($table, $indexName, $index);
    }
    $schemaSql .= "\n";
}

$schemaSql .= "COMMIT;\n";
writeSql($outputDir, '00-schema.sql', $schemaSql);

$dataFiles = [];
$part = 1;
$buffer = dataFileHeader($part);
$size = strlen($buffer);
$maxFileBytes = 600000;
$editorLimit = (int) floor(0.95 * 1024 * 1024);

$flushDataFile = function () use (&$buffer, &$part, &$dataFiles, &$size, $outputDir): void {
    if ($size <= strlen(dataFileHeader($part))) {
        return;
    }

    $buffer .= "COMMIT;\n";
    $name = sprintf('01-data-part-%02d.sql', $part);
    writeSql($outputDir, $name, $buffer);
    $dataFiles[] = $name;
    $part++;
    $buffer = dataFileHeader($part);
    $size = strlen($buffer);
};

foreach ($tables as $table) {
    if (isset($skipData[$table])) {
        echo "skip data {$table}\n";
        continue;
    }

    $tableColumns = $columns[$table] ?? [];
    $rows = $connection->table($table)->get();
    echo "export {$table} ".$rows->count()."\n";

    if ($rows->isEmpty()) {
        continue;
    }

    foreach (insertStatements($table, $tableColumns, $rows->all()) as $statement) {
        $statementBytes = strlen($statement);
        if ($statementBytes + strlen(dataFileHeader(1)) + 8 > $editorLimit) {
            fwrite(STDERR, "One {$table} row is too large for the Supabase SQL editor.\n");
            exit(1);
        }

        if ($size + $statementBytes > $maxFileBytes) {
            $flushDataFile();
        }

        $buffer .= $statement;
        $size += $statementBytes;
    }
}

if ($size > strlen(dataFileHeader($part))) {
    $buffer .= "COMMIT;\n";
    $name = sprintf('01-data-part-%02d.sql', $part);
    writeSql($outputDir, $name, $buffer);
    $dataFiles[] = $name;
}

$finish = "-- AGAPORA foreign keys, id sequences, and API lock. Run this file last.\n";
$finish .= "BEGIN;\n";

foreach ($foreignKeys as $foreignKey) {
    $columnsSql = implode(', ', array_map(pgIdent(...), $foreignKey['columns']));
    $referencedSql = implode(', ', array_map(pgIdent(...), $foreignKey['referenced_columns']));
    $finish .= 'ALTER TABLE '.pgIdent($foreignKey['table'])
        .' ADD CONSTRAINT '.pgIdent($foreignKey['name'])
        .' FOREIGN KEY ('.$columnsSql.') REFERENCES '.pgIdent($foreignKey['referenced_table'])
        .' ('.$referencedSql.')'
        .' ON UPDATE '.$foreignKey['update_rule']
        .' ON DELETE '.$foreignKey['delete_rule'].";\n";
}

$finish .= "\n";

foreach ($tables as $table) {
    $identity = null;
    foreach ($columns[$table] ?? [] as $column) {
        if (str_contains((string) $column->extra, 'auto_increment')) {
            $identity = $column->column_name;
            break;
        }
    }

    if ($identity === null) {
        continue;
    }

    $quotedTable = pgIdent($table);
    $quotedColumn = pgIdent($identity);
    $finish .= <<<SQL
DO \$\$
DECLARE
    seq text;
BEGIN
    seq := pg_get_serial_sequence('public.{$table}', '{$identity}');
    IF seq IS NOT NULL THEN
        PERFORM setval(
            seq,
            GREATEST(COALESCE((SELECT MAX({$quotedColumn}) FROM {$quotedTable}), 1), 1),
            (SELECT COUNT(*) > 0 FROM {$quotedTable})
        );
    END IF;
END \$\$;

SQL;
}

$finish .= "\n";

foreach ($tables as $table) {
    $quoted = pgIdent($table);
    $finish .= "ALTER TABLE {$quoted} ENABLE ROW LEVEL SECURITY;\n";
    $finish .= "REVOKE ALL ON TABLE {$quoted} FROM anon, authenticated;\n";
}

$finish .= "COMMIT;\n";
writeSql($outputDir, '99-finish.sql', $finish);
$dataFiles[] = '99-finish.sql';

echo "\nRun these files in the Supabase SQL editor, in order:\n";
echo "  00-schema.sql\n";
foreach ($dataFiles as $file) {
    echo "  {$file}\n";
}

function dataFileHeader(int $part): string
{
    return "-- AGAPORA data part {$part}. Run after 00-schema.sql and before 99-finish.sql.\nBEGIN;\n\n";
}

function tableNames(Illuminate\Database\Connection $connection, string $schema): array
{
    $rows = $connection->select(
        'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = ? ORDER BY table_name',
        [$schema, 'BASE TABLE']
    );

    return array_map(static fn ($row) => $row->table_name, $rows);
}

function columnsByTable(Illuminate\Database\Connection $connection, string $schema): array
{
    $rows = $connection->select(
        'SELECT table_name, column_name, column_type, data_type, is_nullable, column_default, extra, character_maximum_length, column_key
         FROM information_schema.columns
         WHERE table_schema = ?
         ORDER BY table_name, ordinal_position',
        [$schema]
    );

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row->table_name][] = $row;
    }

    return $grouped;
}

function indexesByTable(Illuminate\Database\Connection $connection, string $schema): array
{
    $rows = $connection->select(
        'SELECT table_name, index_name, non_unique, column_name
         FROM information_schema.statistics
         WHERE table_schema = ?
         ORDER BY table_name, index_name, seq_in_index',
        [$schema]
    );

    $grouped = [];
    foreach ($rows as $row) {
        if ($row->index_name === 'PRIMARY') {
            continue;
        }

        $grouped[$row->table_name][$row->index_name]['name'] = $row->index_name;
        $grouped[$row->table_name][$row->index_name]['unique'] = (int) $row->non_unique === 0;
        $grouped[$row->table_name][$row->index_name]['columns'][] = $row->column_name;
    }

    return $grouped;
}

function foreignKeys(Illuminate\Database\Connection $connection, string $schema): array
{
    $rows = $connection->select(
        'SELECT rc.constraint_name, rc.update_rule, rc.delete_rule, rc.table_name,
                kcu.column_name, kcu.referenced_table_name, kcu.referenced_column_name, kcu.ordinal_position
         FROM information_schema.referential_constraints rc
         INNER JOIN information_schema.key_column_usage kcu
            ON kcu.constraint_schema = rc.constraint_schema
           AND kcu.constraint_name = rc.constraint_name
           AND kcu.table_name = rc.table_name
         WHERE rc.constraint_schema = ?
         ORDER BY rc.table_name, rc.constraint_name, kcu.ordinal_position',
        [$schema]
    );

    $grouped = [];
    foreach ($rows as $row) {
        $key = $row->table_name.'.'.$row->constraint_name;
        $grouped[$key]['name'] = $row->constraint_name;
        $grouped[$key]['table'] = $row->table_name;
        $grouped[$key]['referenced_table'] = $row->referenced_table_name;
        $grouped[$key]['update_rule'] = $row->update_rule;
        $grouped[$key]['delete_rule'] = $row->delete_rule;
        $grouped[$key]['columns'][] = $row->column_name;
        $grouped[$key]['referenced_columns'][] = $row->referenced_column_name;
    }

    return array_values($grouped);
}

function createTableSql(string $table, array $columns): string
{
    $lines = [];
    foreach ($columns as $column) {
        $lines[] = '    '.pgIdent($column->column_name).' '.columnType($column);
    }

    return 'CREATE TABLE '.pgIdent($table)." (\n".implode(",\n", $lines)."\n);\n";
}

function columnType(object $column): string
{
    $type = match (true) {
        $column->column_type === 'tinyint(1)' => 'boolean',
        str_starts_with($column->data_type, 'bigint') => 'bigint',
        str_starts_with($column->data_type, 'int'), str_starts_with($column->data_type, 'mediumint') => str_contains($column->column_type, 'unsigned') ? 'bigint' : 'integer',
        str_starts_with($column->data_type, 'smallint'), str_starts_with($column->data_type, 'tinyint') => str_contains($column->column_type, 'unsigned') ? 'integer' : 'smallint',
        $column->data_type === 'varchar', $column->data_type === 'char' => 'varchar('.((int) $column->character_maximum_length).')',
        in_array($column->data_type, ['text', 'tinytext', 'mediumtext', 'longtext', 'json'], true) => 'text',
        in_array($column->data_type, ['datetime', 'timestamp'], true) => 'timestamp(0) without time zone',
        $column->data_type === 'date' => 'date',
        in_array($column->data_type, ['decimal', 'numeric'], true) => $column->column_type,
        default => 'text',
    };

    if (str_contains((string) $column->extra, 'auto_increment')) {
        $type .= ' GENERATED BY DEFAULT AS IDENTITY';
    }

    $type .= $column->is_nullable === 'YES' ? '' : ' NOT NULL';

    $default = columnDefault($column);
    if ($default !== null) {
        $type .= ' DEFAULT '.$default;
    }

    if (primaryKey($column)) {
        $type .= ' PRIMARY KEY';
    }

    return $type;
}

function primaryKey(object $column): bool
{
    return ($column->column_key ?? '') === 'PRI';
}

function columnDefault(object $column): ?string
{
    if (str_contains((string) $column->extra, 'auto_increment')) {
        return null;
    }

    if (str_contains((string) $column->extra, 'on update')) {
        return null;
    }

    $default = $column->column_default;
    if ($default === null) {
        return null;
    }

    $normalized = strtolower(trim((string) $default));
    if ($normalized === 'null') {
        return null;
    }

    if (in_array($normalized, ['current_timestamp()', 'current_timestamp'], true)) {
        return 'CURRENT_TIMESTAMP';
    }

    if ($column->column_type === 'tinyint(1)') {
        return in_array(trim((string) $default, "'"), ['1', 'true'], true) ? 'true' : 'false';
    }

    if (is_numeric($default)) {
        return (string) $default;
    }

    $trimmed = trim((string) $default);
    if (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'")) {
        return dollarQuote(stripcslashes(substr($trimmed, 1, -1)));
    }

    return dollarQuote((string) $default);
}

function createIndexSql(string $table, string $name, array $index): string
{
    $unique = $index['unique'] ? 'UNIQUE ' : '';
    $columns = implode(', ', array_map(pgIdent(...), $index['columns']));

    return 'CREATE '.$unique.'INDEX '.pgIdent($name).' ON '.pgIdent($table).' ('.$columns.");\n";
}

function insertStatements(string $table, array $columns, array $rows): array
{
    $names = array_map(static fn ($column) => $column->column_name, $columns);
    $columnSql = implode(', ', array_map(pgIdent(...), $names));
    $statements = [];

    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $values[] = pgValue($row->{$column->column_name} ?? null, $column);
        }
        $statements[] = 'INSERT INTO '.pgIdent($table).' ('.$columnSql.') VALUES ('.implode(', ', $values).");\n";
    }

    return $statements;
}

function pgValue(mixed $value, object $column): string
{
    if ($value === null) {
        return 'NULL';
    }

    if ($column->column_type === 'tinyint(1)') {
        return in_array((string) $value, ['1', 'true'], true) ? 'true' : 'false';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    $text = (string) $value;
    if (in_array($column->data_type, ['datetime', 'timestamp', 'date'], true) && str_starts_with($text, '0000-00-00')) {
        return 'NULL';
    }

    if (isNumericColumn($column) && is_numeric($text)) {
        return $text;
    }

    return dollarQuote($text);
}

function isNumericColumn(object $column): bool
{
    return preg_match('/int|decimal|numeric|float|double|real|bit/', $column->data_type) === 1;
}

function dollarQuote(string $value): string
{
    $tag = 'agapora';
    $suffix = 0;
    while (str_contains($value, '$'.$tag.'$')) {
        $tag = 'agapora'.$suffix;
        $suffix++;
    }

    return '$'.$tag.'$'.$value.'$'.$tag.'$';
}

function pgIdent(string $name): string
{
    if (strlen($name) > 63) {
        $name = substr($name, 0, 54).'_'.substr(md5($name), 0, 8);
    }

    return '"'.str_replace('"', '""', $name).'"';
}

function writeSql(string $directory, string $name, string $sql): void
{
    $path = $directory.DIRECTORY_SEPARATOR.$name;
    if (file_put_contents($path, $sql) === false) {
        fwrite(STDERR, "Could not write {$path}\n");
        exit(1);
    }

    echo 'wrote '.$name.' ('.number_format(strlen($sql))." bytes)\n";
}
