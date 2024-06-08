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

    public function realTableName($tableName): string
    {
        return $this->prefix . '__' . $tableName;
    }

    public function hasTable($tableName)
    {
        return $this->schema['tables'][$tableName] ?? false;
    }

    public function getTable($tableName)
    {
        return $this->schema['tables'][$tableName] ?? null;
    }

    public function getTables()
    {
        return $this->schema['tables'] ?? [];
    }

    public function getColumn($tableName, $columnName)
    {
        return $this->schema['tables'][$tableName]['fields'][$columnName] ?? null;
    }

    public function query(string $tableName): DatabaseTableQuery
    {
        $tableSchema = $this->getTable($tableName);
        if (!$tableSchema) {
            throw new Exception("Table not found: " . $tableName);
        }

        return app(DatabaseTableQuery::class)->setTable($this->prefix . '__', $tableName, $tableSchema);
    }
}
