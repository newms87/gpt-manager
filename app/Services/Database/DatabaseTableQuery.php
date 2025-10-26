<?php

namespace App\Services\Database;

use Exception;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class DatabaseTableQuery extends Builder
{
    protected array $tableSchema;

    protected array $loadedRecords = [];

    public function __construct()
    {
        $factory    = new ConnectionFactory(app());
        $connection = $factory->make(config('database.connections.mysql'));

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

    public function hasField($name): bool
    {
        return !empty($this->getFields()[$name]);
    }

    public function isAutoRef()
    {
        return $this->tableSchema['auto_ref'] ?? false;
    }

    public function computeRef($record): string
    {
        return implode(':', array_map(fn($field) => $record[$field] ?? '', $this->tableSchema['auto_ref']));
    }

    public function prepareRecord(?object $record = null): ?object
    {
        if ($record) {
            foreach ($this->getFields() as $fieldName => $fieldDefinition) {
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

        foreach ($results as $result) {
            $this->prepareRecord($result);
        }

        return $results;
    }

    /**
     * Find a record by one or more unique fields
     *
     * @param  int|string  $id
     * @param  string[]  $columns
     */
    public function find($id, $columns = ['*']): object
    {
        if (!array_key_exists($id, $this->loadedRecords)) {
            if ($this->hasField('ref')) {
                $this->loadedRecords[$id] = $this->where(fn(Builder $builder) => $builder->where('ref', $id)->orWhere('id', $id))->first($columns) ?? null;
            } else {
                $this->loadedRecords[$id] = $this->where('id', $id)->first($columns) ?? null;
            }
        }

        return $this->loadedRecords[$id];
    }

    /**
     * Create a new record
     */
    public function createOrUpdate(array $attributes, array $updates = []): object
    {
        $record = (array)$this->where($attributes)->first() ?: [];

        $processedUpdates = $this->processFields($attributes + $updates + $record);

        if ($record) {
            $this->update($processedUpdates);
            $id = $record['id'];
        } else {
            $id = $this->insertGetId($processedUpdates);
        }

        return $this->find($id);
    }

    /**
     * Create a new record ensuring the ref field is unique
     */
    public function createOrUpdateWithRef(array $record): object
    {
        // Assign auto ref fields
        if ($this->isAutoRef()) {
            $record['ref'] = $this->computeRef($record);
        }

        if (empty($record['ref'])) {
            throw new Exception('Missing ref field in record: ' . json_encode($record));
        }

        // Fill in previously created record data
        $record += (array)$this->where('ref', $record['ref'])->first() ?: [];

        $record = $this->processFields($record);

        $this->updateOrInsert(['ref' => $record['ref']], $record);

        return $this->find($record['ref']);
    }

    /**
     * Cast fields to their respective types and make sure all required fields are present
     */
    public function processFields($record)
    {
        foreach ($this->getFields() as $fieldName => $fieldDefinition) {
            if ($fieldName === 'timestamps' && $fieldDefinition === true) {
                if (empty($record['created_at'])) {
                    $record['created_at'] = $record['created_at'] ?? now()->toDateTimeString();
                }

                $record['updated_at'] = $record['updated_at'] ?? now()->toDateTimeString();
            }

            if (($fieldDefinition['type'] ?? '') === 'json' && isset($record[$fieldName])) {
                $record[$fieldName] = json_encode($record[$fieldName]);
            }
        }

        return $record;
    }
}
