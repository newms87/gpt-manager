<?php

namespace App\Services\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Yaml\Yaml;

class DatabaseSchemaMapper
{
    protected string $prefix = '';

    public function map($schemaFile, $prefix = '')
    {
        $this->prefix = $prefix;
        $schema       = Yaml::parseFile($schemaFile);

        foreach($schema['tables'] as $tableName => $tableDefinition) {
            $tableName = $prefix . '__' . $tableName;
            if (!Schema::hasTable($tableName)) {
                $this->createTable($tableName, $tableDefinition);
            } else {
                $this->updateTable($tableName, $tableDefinition);
            }
        }
    }

    protected function createTable($tableName, $definition)
    {
        $fields  = $definition['fields'] ?? [];
        $indexes = $definition['indexes'] ?? [];
        Schema::create($tableName, function (Blueprint $table) use ($fields, $indexes) {
            $this->addColumns($table, $fields);
            $this->addIndexes($table, $indexes);
        });
    }

    protected function updateTable($tableName, $definition)
    {
        $fields  = $definition['fields'] ?? [];
        $indexes = $definition['indexes'] ?? [];
        Schema::table($tableName, function (Blueprint $table) use ($fields, $indexes) {
            $this->updateColumns($table, $fields);
            $this->updateIndexes($table, $indexes);
        });
    }

    protected function addColumns(Blueprint $table, array $fields)
    {
        foreach($fields as $fieldName => $fieldDefinition) {
            if ($fieldName === 'id' && $fieldDefinition === true) {
                $table->id();
                continue;
            }
            if ($fieldName === 'timestamps' && $fieldDefinition === true) {
                $table->timestamps();
                continue;
            }
            if ($fieldName === 'softDeletes' && $fieldDefinition === true) {
                $table->softDeletes();
                continue;
            }

            $this->addColumn($table, $fieldName, $fieldDefinition);
        }
    }

    protected function updateColumns(Blueprint $table, $fields)
    {
        $existingColumns = Schema::getColumnListing($table->getTable());
        $definedColumns  = array_keys($fields);

        foreach($fields as $fieldName => $fieldDefinition) {
            if (!in_array($fieldName, $existingColumns)) {
                $this->addColumn($table, $fieldName, $fieldDefinition);
            } else {
                $this->updateColumn($table, $fieldName, $fieldDefinition);
            }
        }

        foreach($existingColumns as $existingColumn) {
            if (!in_array($existingColumn, $definedColumns)) {
                $table->dropColumn($existingColumn);
            }
        }
    }

    protected function addColumn(Blueprint $table, string $name, array $definition)
    {
        $column = $table->{$definition['type']}($name);

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
            $column->unique();
        }
        if (!empty($definition['auto_increment'])) {
            $column->autoIncrement();
        }
        if (!empty($definition['default'])) {
            $column->default($definition['default']);
        }
        if ($definition['type'] === 'foreignId') {
            $foreignKey = $definition['foreign_key'] ?? null;
            if (!$foreignKey) {
                throw new \Exception("foreign_key is required when setting foreignId type");
            }

            [$foreignTable, $foreignColumn] = explode('.', $foreignKey);
            $foreignTable = $this->prefix . '__' . $foreignTable;
            $column->constrained($foreignTable, $foreignColumn);
        }

        return $column;
    }

    protected function updateColumn(Blueprint $table, string $name, array $definition)
    {
        $currentColumnType = Schema::getColumnType($table->getTable(), $name);

        if ($currentColumnType !== $definition['type']) {
            $this->addColumn($table, $name, $definition)->change();
        }
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

    protected function updateIndex(Blueprint $table, $index)
    {
        $name = $index['name'] ?? null;

        dump('updating index', Schema::getIndexes($table->getTable()), $index);
        $existingIndex = Schema::getIndexes($table->getTable())[$name] ?? null;

        if ($existingIndex) {
            // If the index with the same name exists, but has changed, drop it and recreate it
            if ($existingIndex->getColumns() !== implode(',', $index['columns'] ?? [])) {
                $table->dropIndex($name);
                $this->addIndex($table, $index);
            }
        } else {
            $this->addIndex($table, $index);
        }
    }

    protected function addIndex(Blueprint $table, $index)
    {
        $name    = $index['name'] ?? null;
        $columns = $index['columns'] ?? [];

        if ($index['unique']) {
            $table->unique($columns, $name);
        } else {
            $table->index($columns, $name);
        }
    }
}
