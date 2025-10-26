<?php

namespace App\Console\Commands;

use App\Models\Schema\SchemaDefinition;
use App\Models\Team\Team;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectRelationship;
use Faker\Factory as Faker;
use Illuminate\Console\Command;

class SeedTeamObjectsCommand extends Command
{
    protected $signature = 'team-objects:seed
                            {team : The team ID or name to seed objects for}
                            {schema : The schema definition ID or name to use}
                            {--count=1 : Number of root objects to create}
                            {--depth=3 : Maximum nesting depth for relationships}';

    protected $description = 'Seed random team objects using a specific schema definition structure';

    protected $faker;

    protected $team;

    protected $schema;

    protected $schemaProperties = [];

    protected $createdObjects   = [];

    protected $objectTypes      = [
        'Demand', 'Provider', 'Facility', 'Diagnosis', 'Procedure',
        'Medication', 'Laboratory', 'Radiology', 'Document', 'Address',
        'Insurance', 'Claim', 'Authorization', 'Referral', 'Appointment',
    ];

    protected $relationshipNames = [
        'providers', 'facilities', 'diagnoses', 'procedures', 'medications',
        'laboratories', 'radiology_services', 'documents', 'addresses',
        'insurances', 'claims', 'authorizations', 'referrals', 'appointments',
        'primary_provider', 'primary_facility', 'parent', 'children', 'related_to',
    ];

    protected $attributeNames = [
        'status', 'priority', 'code', 'description', 'notes', 'amount',
        'quantity', 'unit', 'frequency', 'duration', 'start_date', 'end_date',
        'is_active', 'is_primary', 'category', 'subcategory', 'severity',
        'risk_level', 'compliance_score', 'quality_rating', 'cost_estimate',
        'approval_status', 'review_notes', 'external_id', 'reference_number',
    ];

    public function handle()
    {
        $this->faker = Faker::create();

        // Find the team
        $teamInput  = $this->argument('team');
        $this->team = is_numeric($teamInput)
            ? Team::find($teamInput)
            : Team::where('name', $teamInput)->first();

        if (!$this->team) {
            $this->error("Team not found: {$teamInput}");

            return 1;
        }

        // Find the schema definition
        $schemaInput  = $this->argument('schema');
        $this->schema = is_numeric($schemaInput)
            ? SchemaDefinition::find($schemaInput)
            : SchemaDefinition::where('team_id', $this->team->id)
                ->where('name', $schemaInput)
                ->first();

        if (!$this->schema) {
            $this->error("Schema definition not found: {$schemaInput}");

            return 1;
        }

        if ($this->schema->team_id !== $this->team->id) {
            $this->error('Schema definition does not belong to the specified team');

            return 1;
        }

        $this->info("Seeding team objects for team: {$this->team->name}");
        $this->info("Using schema: {$this->schema->name} ({$this->schema->type})");

        // Parse schema properties
        $this->parseSchemaProperties();

        // Create root objects
        $rootCount = (int)$this->option('count');
        $maxDepth  = (int)$this->option('depth');

        $this->info("Creating {$rootCount} root objects with max depth {$maxDepth}...");

        $progressBar = $this->output->createProgressBar($rootCount);
        $progressBar->start();

        for ($i = 0; $i < $rootCount; $i++) {
            $rootObject             = $this->createTeamObject(null, 0, $maxDepth);
            $this->createdObjects[] = $rootObject;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Create some cross-references between unrelated objects
        $this->createCrossReferences();

        $totalObjects       = TeamObject::where('team_id', $this->team->id)->count();
        $totalAttributes    = TeamObjectAttribute::whereExists(function ($query) {
            $query->select('*')
                ->from('team_objects')
                ->whereColumn('team_objects.id', 'team_object_attributes.team_object_id')
                ->where('team_objects.team_id', $this->team->id);
        })->count();
        $totalRelationships = TeamObjectRelationship::whereExists(function ($query) {
            $query->select('*')
                ->from('team_objects')
                ->whereColumn('team_objects.id', 'team_object_relationships.team_object_id')
                ->where('team_objects.team_id', $this->team->id);
        })->count();

        $this->newLine();
        $this->info('Seeding complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Objects', $totalObjects],
                ['Total Attributes', $totalAttributes],
                ['Total Relationships', $totalRelationships],
                ['Average Attributes/Object', round($totalAttributes / max($totalObjects, 1), 2)],
                ['Average Relationships/Object', round($totalRelationships / max($totalObjects, 1), 2)],
            ]
        );

        return 0;
    }

    protected function parseSchemaProperties()
    {
        $schema                 = $this->schema->schema ?? [];
        $this->schemaProperties = $schema['properties'] ?? [];

        if (empty($this->schemaProperties)) {
            $this->error('Schema definition has no properties defined');
            exit(1);
        }

        $attributeCount    = 0;
        $relationshipCount = 0;

        foreach ($this->schemaProperties as $name => $property) {
            $type = $property['type'] ?? 'string';
            if ($type === 'array' && ($property['items']['type'] ?? '') === 'object') {
                $relationshipCount++;
            } elseif ($type === 'object') {
                $relationshipCount++;
            } else {
                $attributeCount++;
            }
        }

        $this->info("Schema parsed: {$attributeCount} attributes, {$relationshipCount} relationships");
    }

    protected function determineObjectType($propertySchema = null)
    {
        if ($propertySchema) {
            // Check if this is an array of objects
            if (($propertySchema['type'] ?? '') === 'array') {
                $items = $propertySchema['items'] ?? [];
                if (($items['type'] ?? '') === 'object' && isset($items['title'])) {
                    return $items['title'];
                }
            }
            // Check if this is a direct object with a title
            elseif (($propertySchema['type'] ?? '') === 'object' && isset($propertySchema['title'])) {
                return $propertySchema['title'];
            }
        }

        // Fall back to main schema title
        return $this->schema->schema['title'] ?? 'Object';
    }

    protected function getSchemaProperties($propertySchema = null)
    {
        if ($propertySchema) {
            // Handle array of objects - get properties from items
            if (($propertySchema['type'] ?? '') === 'array') {
                $items = $propertySchema['items'] ?? [];

                return $items['properties'] ?? [];
            }
            // Handle direct object - get its properties
            elseif (($propertySchema['type'] ?? '') === 'object') {
                return $propertySchema['properties'] ?? [];
            }
        }

        // Fall back to root schema properties
        return $this->schemaProperties;
    }

    protected function createTeamObject($parentObject = null, $currentDepth = 0, $maxDepth = 3, $propertySchema = null)
    {
        // Determine the object type from the property schema or fall back to main schema
        $objectType = $this->determineObjectType($propertySchema);

        $object = TeamObject::create([
            'team_id'              => $this->team->id,
            'schema_definition_id' => $this->schema->id,
            'root_object_id'       => $parentObject?->root_object_id ?? $parentObject?->id,
            'type'                 => $objectType,
            'name'                 => $this->generateObjectName($objectType),
            'description'          => $this->faker->sentence(rand(5, 15)),
            'date'                 => $this->faker->optional(0.6)->dateTimeBetween('-1 year', 'now'),
            'url'                  => $this->faker->optional(0.3)->url(),
            'meta'                 => $this->generateMetaData(),
        ]);

        // Create attributes
        $this->createAttributes($object, $propertySchema);

        // Create relationships based on schema definition
        if ($currentDepth < $maxDepth) {
            $this->createSchemaBasedRelationships($object, $currentDepth, $maxDepth, $propertySchema);
        }

        return $object;
    }

    protected function generateObjectName($type)
    {
        $prefixes = [
            'Demand'        => ['Request', 'Order', 'Requisition'],
            'Provider'      => ['Dr.', 'Nurse', 'Specialist'],
            'Facility'      => ['Medical Center', 'Hospital', 'Clinic'],
            'Diagnosis'     => ['ICD-', 'DX-', 'Condition'],
            'Procedure'     => ['CPT-', 'PROC-', 'Surgery'],
            'Medication'    => ['Med-', 'RX-', 'Drug'],
            'Laboratory'    => ['Lab-', 'Test-', 'Panel'],
            'Radiology'     => ['RAD-', 'Imaging-', 'X-Ray'],
            'Document'      => ['DOC-', 'File-', 'Report'],
            'Address'       => ['Location-', 'Site-', 'Address'],
            'Insurance'     => ['Policy-', 'Plan-', 'Coverage'],
            'Claim'         => ['CLM-', 'Bill-', 'Invoice'],
            'Authorization' => ['AUTH-', 'Approval-', 'PA-'],
            'Referral'      => ['REF-', 'Transfer-', 'Consult'],
            'Appointment'   => ['APPT-', 'Visit-', 'Schedule'],
        ];

        $prefix = $this->faker->randomElement($prefixes[$type] ?? [$type]);
        $suffix = strtoupper($this->faker->bothify('##??##'));

        return "{$prefix} {$this->faker->lastName()} {$suffix}";
    }

    protected function generateMetaData()
    {
        if ($this->faker->boolean(70)) {
            return [
                'source_system'  => $this->faker->randomElement(['EHR', 'CRM', 'BILLING', 'LAB', 'RIS']),
                'import_date'    => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                'version'        => $this->faker->numerify('v#.#.#'),
                'tags'           => $this->faker->words(rand(1, 5)),
                'custom_field_1' => $this->faker->word(),
                'custom_field_2' => $this->faker->randomNumber(5),
            ];
        }

        return null;
    }

    protected function createAttributes($object, $propertySchema = null)
    {
        // Get properties from the passed schema or fall back to root schema
        $schemaProperties = $this->getSchemaProperties($propertySchema);

        // Create attributes only for non-relationship properties in the schema
        $attributeProperties = array_filter($schemaProperties, function ($property) {
            $type = $property['type'] ?? 'string';

            return !($type === 'object' || ($type === 'array' && ($property['items']['type'] ?? '') === 'object'));
        });

        foreach ($attributeProperties as $attrName => $property) {
            $schemaType = $property['type'] ?? 'string';

            TeamObjectAttribute::create([
                'team_object_id' => $object->id,
                'name'           => $attrName,
                'text_value'     => $schemaType !== 'array' && $schemaType !== 'object'
                    ? $this->generateAttributeValue($schemaType, $attrName, $property)
                    : null,
                'json_value'     => $schemaType === 'array' || $schemaType === 'object'
                    ? $this->generateJsonAttributeValue($schemaType, $property)
                    : null,
                'confidence'     => $this->faker->randomElement(['High', 'Medium', 'Low', null]),
                'reason'         => $this->faker->optional(0.7)->sentence(rand(5, 15)),
            ]);
        }
    }

    protected function createSchemaBasedRelationships($object, $currentDepth, $maxDepth, $propertySchema = null)
    {
        // Get properties from the passed schema or fall back to root schema
        $schemaProperties = $this->getSchemaProperties($propertySchema);

        // Create relationships only for object/array properties in the schema
        $relationshipProperties = array_filter($schemaProperties, function ($property) {
            $type = $property['type'] ?? 'string';

            return $type === 'object' || ($type === 'array' && ($property['items']['type'] ?? '') === 'object');
        });

        foreach ($relationshipProperties as $relationName => $property) {
            $type = $property['type'] ?? 'string';

            if ($type === 'array') {
                // Create multiple related objects for array relationships
                $numRelated = rand(1, 3); // Random number of related objects
                for ($i = 0; $i < $numRelated; $i++) {
                    $relatedObject = $this->createTeamObject($object, $currentDepth + 1, $maxDepth, $property);

                    TeamObjectRelationship::create([
                        'team_object_id'         => $object->id,
                        'related_team_object_id' => $relatedObject->id,
                        'relationship_name'      => $relationName,
                    ]);
                }
            } else {
                // Create single related object for object relationships
                $relatedObject = $this->createTeamObject($object, $currentDepth + 1, $maxDepth, $property);

                TeamObjectRelationship::create([
                    'team_object_id'         => $object->id,
                    'related_team_object_id' => $relatedObject->id,
                    'relationship_name'      => $relationName,
                ]);
            }
        }
    }

    protected function generateAttributeValue($type, $name, $property = [])
    {
        // Check for enum values in schema
        if (isset($property['enum'])) {
            return $this->faker->randomElement($property['enum']);
        }

        switch ($type) {
            case 'boolean':
                return $this->faker->boolean() ? 'true' : 'false';

            case 'number':
            case 'integer':
                $min = $property['minimum'] ?? 1;
                $max = $property['maximum'] ?? 10000;

                if (str_contains($name, 'amount') || str_contains($name, 'cost')) {
                    return (string)$this->faker->randomFloat(2, max($min, 10), min($max, 10000));
                }
                if (str_contains($name, 'score') || str_contains($name, 'rating')) {
                    return (string)$this->faker->numberBetween(max($min, 1), min($max, 100));
                }

                return (string)$this->faker->numberBetween($min, min($max, 99999));

            case 'string':
            default:
                // Check for format specifications
                $format = $property['format'] ?? null;

                if ($format === 'date') {
                    return $this->faker->date();
                }
                if ($format === 'date-time') {
                    return $this->faker->dateTime()->format('c');
                }
                if ($format === 'email') {
                    return $this->faker->email();
                }
                if ($format === 'uri') {
                    return $this->faker->url();
                }

                // Contextual generation based on name
                if (str_contains($name, 'date')) {
                    return $this->faker->date();
                }
                if (str_contains($name, 'status')) {
                    return $this->faker->randomElement(['active', 'pending', 'completed', 'cancelled', 'on-hold']);
                }
                if (str_contains($name, 'code') || str_contains($name, 'id')) {
                    return strtoupper($this->faker->bothify('???-####-??'));
                }
                if (str_contains($name, 'notes') || str_contains($name, 'description')) {
                    return $this->faker->paragraph(rand(1, 3));
                }
                if (str_contains($name, 'email')) {
                    return $this->faker->email();
                }
                if (str_contains($name, 'phone')) {
                    return $this->faker->phoneNumber();
                }
                if (str_contains($name, 'address')) {
                    return $this->faker->address();
                }

                $maxLength = $property['maxLength'] ?? 255;
                $minLength = $property['minLength'] ?? 3;

                if ($maxLength > 100) {
                    return $this->faker->paragraph(rand(1, 3));
                }

                return $this->faker->sentence(rand(max($minLength, 3), min($maxLength / 10, 8)));
        }
    }

    protected function generateJsonAttributeValue($type, $property = [])
    {
        if ($type === 'array') {
            $items     = $property['items']    ?? [];
            $itemType  = $items['type']        ?? 'string';
            $minItems  = $property['minItems'] ?? 1;
            $maxItems  = $property['maxItems'] ?? 5;
            $arraySize = rand($minItems, $maxItems);

            $array = [];
            for ($i = 0; $i < $arraySize; $i++) {
                if ($itemType === 'string') {
                    $array[] = $this->faker->word();
                } elseif ($itemType === 'number') {
                    $array[] = $this->faker->randomFloat(2, 1, 100);
                } elseif ($itemType === 'boolean') {
                    $array[] = $this->faker->boolean();
                } else {
                    $array[] = $this->faker->word();
                }
            }

            return $array;
        }

        // Object type - generate based on schema properties if available
        $objectProperties = $property['properties'] ?? [];
        $object           = [];

        if (!empty($objectProperties)) {
            foreach ($objectProperties as $propName => $propDef) {
                $propType          = $propDef['type'] ?? 'string';
                $object[$propName] = $this->generateAttributeValue($propType, $propName, $propDef);
            }
        } else {
            // Default object structure
            $object = [
                'field1' => $this->faker->word(),
                'field2' => $this->faker->randomNumber(3),
                'field3' => $this->faker->boolean(),
            ];
        }

        return $object;
    }

    protected function createCrossReferences()
    {
        if (count($this->createdObjects) < 10) {
            return;
        }

        $this->info("\nCreating cross-references between objects...");

        $numCrossRefs = min(20, (int)(count($this->createdObjects) * 0.3));

        for ($i = 0; $i < $numCrossRefs; $i++) {
            $object1 = $this->faker->randomElement($this->createdObjects);
            $object2 = $this->faker->randomElement($this->createdObjects);

            // Don't create self-references or duplicate relationships
            if ($object1->id === $object2->id) {
                continue;
            }

            // Check if relationship already exists
            $exists = TeamObjectRelationship::where('team_object_id', $object1->id)
                ->where('related_team_object_id', $object2->id)
                ->exists();

            if (!$exists) {
                TeamObjectRelationship::create([
                    'team_object_id'         => $object1->id,
                    'related_team_object_id' => $object2->id,
                    'relationship_name'      => $this->faker->randomElement(['related_to', 'associated_with', 'linked_to']),
                ]);
            }
        }
    }
}
