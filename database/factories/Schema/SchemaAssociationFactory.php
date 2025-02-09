<?php

namespace Database\Factories\Schema;

use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\TaskDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchemaAssociationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schema_definition_id' => SchemaDefinition::factory(),
            'schema_fragment_id'   => SchemaFragment::factory(),
            'object_type'          => Taskdefinition::class,
            'object_id'            => TaskDefinition::factory(),
            'category'             => 'grouping',
        ];
    }

    public function withSchema(SchemaDefinition|array $schema = [], SchemaFragment|array $fragment = []): static
    {
        return $this->state([
            'schema_definition_id' => $schema instanceof SchemaDefinition ? $schema : SchemaDefinition::factory()->create(['schema' => $schema]),
            'schema_fragment_id'   => $fragment instanceof SchemaFragment ? $fragment : SchemaFragment::factory()->create(['fragment_selector' => $fragment]),
        ]);
    }
}
