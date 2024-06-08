<?php

namespace App\Services\Database;

use Exception;
use Symfony\Component\Yaml\Yaml;

class SchemaManager
{
    protected string $prefix;
    protected array  $schema;

    public function __construct(string $prefix, string $schemaFile)
    {
        $this->prefix = $prefix;
        $this->schema = Yaml::parseFile($schemaFile);
    }

    public function hasTable($tableName)
    {
        return $this->schema['tables'][$tableName] ?? false;
    }

    public function getTable($tableName)
    {
        return $this->schema['tables'][$tableName] ?? [];
    }

    public function query(string $tableName): DatabaseTableQuery
    {
        $tableSchema = $this->getTable($tableName);
        if (!$tableSchema) {
            throw new Exception("Table not found: " . $tableName);
        }

        return app(DatabaseTableQuery::class)->setTable($this->prefix . '__', $tableName, $tableSchema);
    }

    public function createRecord(string $tableName, array $record)
    {
        $table = $this->getTable($tableName);
        if (!$table) {
            throw new Exception("Table not found: " . $tableName);
        }

        $fields  = $table['fields'];
        $autoRef = $table['auto_ref'] ?? null;

        // Assign auto ref fields
        if ($autoRef) {
            $record['ref'] = implode(':', array_map(fn($field) => $record[$field] ?? '', $autoRef));
        }

        if (empty($record['ref'])) {
            throw new Exception("Missing ref field in record: " . json_encode($record));
        }

        foreach($fields as $fieldName => $fieldDefinition) {
            if ($fieldName === 'timestamps' && $fieldDefinition === true) {
                $record['created_at'] = $record['created_at'] ?? now()->toDateTimeString();
                $record['updated_at'] = $record['updated_at'] ?? now()->toDateTimeString();
            }
        }

        foreach($record as $field => $value) {
            if (is_array($value) || is_object($value)) {
                $record[$field] = json_encode($value);
            }
        }

        $this->query($tableName)->updateOrInsert(['ref' => $record['ref']], $record);
    }
}
