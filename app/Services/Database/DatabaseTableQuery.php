<?php

namespace App\Services\Database;

use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class DatabaseTableQuery extends Builder
{
    protected array $tableSchema;
    protected array $loadedRecords = [];

    public function __construct(ConnectionInterface $connection)
    {
        parent::__construct($connection);
    }

    public function setTable($prefix, $tableName, $tableSchema): static
    {
        $this->tableSchema = $tableSchema;
        $this->connection->setTablePrefix($prefix);
        $this->from($tableName);

        return $this;
    }

    public function getFields()
    {
        return $this->tableSchema['fields'] ?? [];
    }

    public function isAutoRef()
    {
        return $this->tableSchema['auto_ref'] ?? false;
    }

    public function computeRef($record): string
    {
        return implode(':', array_map(fn($field) => $record[$field] ?? '', $this->tableSchema['auto_ref']));
    }

    public function prepareRecord(object $record = null): ?object
    {
        if ($record) {
            foreach($this->getFields() as $fieldName => $fieldDefinition) {
                $type = $fieldDefinition['type'] ?? '';
                if ($type === 'json') {
                    $record->$fieldName = json_decode($record->$fieldName, true);
                }
            }
        }

        return $record;
    }

    public function get($columns = ['*']): Collection
    {
        $results = parent::get($columns);

        foreach($results as $result) {
            $this->prepareRecord($result);
        }

        return $results;
    }

    /**
     * Find a record by one or more unique fields
     * @param int|string $id
     * @param string[]   $columns
     */
    public function find($id, $columns = ['*']): object
    {
        if (!array_key_exists($id, $this->loadedRecords)) {
            $this->loadedRecords[$id] = $this->where('ref', $id)->orWhere('id', $id)->first($columns) ?? null;
        }

        return $this->loadedRecords[$id];
    }

    public function create(array $record)
    {
        // Assign auto ref fields
        if ($this->isAutoRef()) {
            $record['ref'] = $this->computeRef($record);
        }

        if (empty($record['ref'])) {
            throw new Exception("Missing ref field in record: " . json_encode($record));
        }

        foreach($this->getFields() as $fieldName => $fieldDefinition) {
            if ($fieldName === 'timestamps' && $fieldDefinition === true) {
                $record['created_at'] = $record['created_at'] ?? now()->toDateTimeString();
                $record['updated_at'] = $record['updated_at'] ?? now()->toDateTimeString();
            }

            if (($fieldDefinition['type'] ?? '') == 'json') {
                $record[$fieldName] = json_encode($record[$fieldName]);
            }
        }

        $this->updateOrInsert(['ref' => $record['ref']], $record);
    }
}
