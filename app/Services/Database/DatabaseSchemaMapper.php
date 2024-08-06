<?php

namespace App\Services\Database;

use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DatabaseSchemaMapper
{
    protected SchemaManager $schema;

    public function map($prefix, $schemaFile)
    {
        $this->schema = new SchemaManager($prefix, $schemaFile);

        foreach($this->schema->getTables() as $tableName => $tableSchema) {
            if (Schema::hasTable($this->schema->realTableName($tableName))) {
                $this->updateTable($tableName, $tableSchema);
            } else {
                $this->createTable($tableName, $tableSchema);
            }
        }
    }

    protected function createTable($tableName, $tableSchema)
    {
        Schema::create($this->schema->realTableName($tableName), function (Blueprint $table) use ($tableSchema) {
            $fields  = $tableSchema['fields'] ?? [];
            $indexes = $tableSchema['indexes'] ?? [];

            $this->addColumns($table, $fields);
            $this->addIndexes($table, $indexes);
        });
    }

    protected function updateTable($tableName, $tableSchema)
    {
        Schema::table($this->schema->realTableName($tableName), function (Blueprint $table) use ($tableSchema) {
            $fields  = $tableSchema['fields'] ?? [];
            $indexes = $tableSchema['indexes'] ?? [];

            $this->updateColumns($table, $fields);
            $this->updateIndexes($table, $indexes);
        });
    }

    protected function getColumn(Blueprint $table, $name): ?array
    {
        $columns = Schema::getColumns($table->getTable());
        foreach($columns as $column) {
            if (strtolower($column['name']) === strtolower($name)) {
                return $column;
            }
        }

        return null;
    }

    protected function addColumns(Blueprint $table, array $fields)
    {
        foreach($fields as $fieldName => $fieldDefinition) {
            $this->addColumn($table, $fieldName, $fieldDefinition);
        }
    }

    protected function updateColumns(Blueprint $table, $fields)
    {
        $existingColumns = Schema::getColumnListing($table->getTable());
        $definedColumns  = array_keys($fields);

        if (!empty($fields['timestamps'])) {
            $definedColumns[] = 'created_at';
            $definedColumns[] = 'updated_at';
        }

        $previousFieldName = null;

        foreach($fields as $fieldName => $fieldDefinition) {
            $column = null;

            // Special case for Timestamps
            if (is_bool($fieldDefinition)) {
                $hasColumn = $fieldName === 'timestamps' ? in_array('created_at', $existingColumns) : in_array($fieldName, $existingColumns);
                if (!$hasColumn) {
                    $column = $this->addColumn($table, $fieldName, $fieldDefinition);
                }
            } else {
                if (in_array($fieldName, $existingColumns)) {
                    $this->updateColumn($table, $fieldName, $fieldDefinition);
                } else {
                    $column = $this->addColumn($table, $fieldName, $fieldDefinition);
                }
            }

            if ($column) {
                if ($previousFieldName) {
                    $column->after($previousFieldName);
                } else {
                    $column->first();
                }
            }

            $previousFieldName = $fieldName;
        }

        foreach($existingColumns as $existingColumn) {
            if (!in_array($existingColumn, $definedColumns)) {
                $table->dropColumn($existingColumn);
            }
        }
    }

    protected function addColumn(Blueprint $table, string $name, array|bool $definition)
    {
        // When the field is a boolean, it means it's a simple field without any additional definition
        if (is_bool($definition)) {
            if ($name === 'ref') {
                return $table->string('ref')->unique();
            } else {
                return $table->{$name}();
            }
        }

        $column = $table->{$definition['type']}($name);

        if (!empty($definition['precision'])) {
            $column->total($definition['precision']);
        }
        if (!empty($definition['scale'])) {
            $column->places($definition['scale']);
        }
        if (!empty($definition['length'])) {
            $column->length($definition['length']);
        }
        if (!empty($definition['nullable'])) {
            $column->nullable();
        }
        if (!empty($definition['primary'])) {
            $column->primary();
        }
        if (!empty($definition['unique'])) {
            $name = is_string($definition['unique']) ? $definition['unique'] : 'unique_' . $name;
            if (!Schema::hasIndex($table->getTable(), $name)) {
                $column->unique($name);
            }
        }
        if (!empty($definition['auto_increment'])) {
            $column->autoIncrement();
        }
        if (!empty($definition['default'])) {
            $column->default($definition['default']);
        }
        if (in_array($definition['type'], ['foreignId', 'foreignUuid'])) {
            $foreignKey    = $definition['foreign_key'] ?? null;
            $foreignPrefix = $definition['foreign_prefix'] ?? null;
            $foreignType   = $definition['foreign_type'] ?? null;

            if (!$foreignKey) {
                throw new Exception("foreign_key is required when setting foreignId type");
            }

            [$foreignTable, $foreignColumn] = explode('.', $foreignKey);
            $foreignTable = $foreignPrefix === null ? $this->schema->realTableName($foreignTable) : $foreignPrefix . $foreignTable;

            $indexName = 'fk_' . $table->getTable() . '_' . $name;

            if (!$this->hasForeignKey($table, $indexName)) {
                if ($foreignType) {
                    $column->type($foreignType);
                }
                $column->constrained($foreignTable, $foreignColumn, $indexName);
            }
        }

        return $column;
    }

    protected function updateColumn(Blueprint $table, string $name, array $definition)
    {
        $this->addColumn($table, $name, $definition)->change();
    }

    protected function addIndexes(Blueprint $table, $indexes)
    {
        foreach($indexes as $index) {
            $this->addIndex($table, $index);
        }
    }

    protected function updateIndexes(Blueprint $table, $indexes)
    {
        foreach($indexes as $index) {
            $this->updateIndex($table, $index);
        }
    }

    protected function addIndex(Blueprint $table, $index)
    {
        $columns = $index['columns'] ?? null;
        if (!$columns) {
            throw new Exception("Columns are required for index: " . json_encode($index));
        }

        $name = $index['name'] ?? 'index_' . implode('_', $columns);

        if ($index['unique']) {
            $table->unique($columns, $name);
        } else {
            $table->index($columns, $name);
        }
    }

    protected function updateIndex(Blueprint $table, $index)
    {
        $columns = $index['columns'] ?? null;
        if (!$columns) {
            throw new Exception("Columns are required for index: " . json_encode($index));
        }

        $name = $index['name'] ?? 'index_' . implode('_', $columns);

        $existingIndex = $this->getIndex($table, $name);

        if ($existingIndex) {
            // If the index with the same name exists, but has changed, drop it and recreate it
            if (implode(',', $existingIndex['columns']) !== implode(',', $columns)) {
                Schema::table($table->getTable(), fn(Blueprint $t) => $t->dropIndex($name));
                $this->addIndex($table, $index);
            }
        } else {
            $this->addIndex($table, $index);
        }
    }

    protected function getIndex($table, $name)
    {
        $indexes = Schema::getIndexes($table->getTable());

        foreach($indexes as $index) {
            if ($index['name'] === $name) {
                return $index;
            }
        }

        return null;
    }

    protected function hasForeignKey($table, $name)
    {
        $fks = Schema::getForeignKeys($table->getTable());

        foreach($fks as $fk) {
            if ($fk['name'] === $name) {
                return true;
            }
        }

        return false;
    }
}
