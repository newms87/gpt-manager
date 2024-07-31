<?php

namespace App\Services\Database;

use Exception;
use Illuminate\Support\Facades\Log;

class DatabaseRecordMapper
{
    protected ?SchemaManager $schema = null;

    public function setSchema(string $prefix, string $schemaFile): static
    {
        $this->schema = new SchemaManager($prefix, $schemaFile);

        return $this;
    }

    /**
     * @param array $entries
     * @return void
     * @throws Exception
     */
    public function map(array $entries): void
    {
        if (!$this->schema) {
            throw new Exception('Schema was not set');
        }

        Log::debug("Writing " . count($entries) . " entries to database");

        foreach($entries as $entry) {
            $tableName = $entry['table'];
            $records   = $entry['records'] ?? [];
            Log::debug("\t$tableName: " . count($records) . " records");
            $relationFieldValues = $this->getRelatedFieldValues($entry['relations'] ?? []);

            foreach($records as $record) {
                $record += $relationFieldValues;
                $this->schema->query($tableName)->create($record);
            }
        }
    }

    /**
     * Get the field value pairs for all related fields.
     * ie: ['user_id' => 1, 'role_id' => 2]
     *
     * @param array $relations  An array of related fields and their table and ref values
     *                          ie: ['user_id' => ['table' => 'users', 'ref' => 'user-1']]
     * @return array
     * @throws Exception
     */
    public function getRelatedFieldValues(array $relations): array
    {
        $fieldValues = [];

        foreach($relations as $fieldName => $relation) {
            // If the relationship value is hard coded, use it as is
            if (!is_array($relation)) {
                $fieldValues[$fieldName] = $relation;
                continue;
            }

            $record = $this->schema->query($relation['table'])->find($relation['ref']);

            if (!$record) {
                throw new Exception("Record should be created for relation: " . json_encode($relation));
            }

            $fieldValues[$fieldName] = $record->id;
        }

        return $fieldValues;
    }
}
