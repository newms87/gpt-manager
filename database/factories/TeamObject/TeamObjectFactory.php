<?php

namespace Database\Factories\TeamObject;

use App\Models\Schema\SchemaDefinition;
use App\Models\Team\Team;
use App\Models\TeamObject\TeamObject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamObject\TeamObject>
 */
class TeamObjectFactory extends Factory
{
    protected $model = TeamObject::class;

    protected static $objectTypes = [
        'Demand', 'Provider', 'Facility', 'Diagnosis', 'Procedure', 
        'Medication', 'Laboratory', 'Radiology', 'Document', 'Address',
        'Insurance', 'Claim', 'Authorization', 'Referral', 'Appointment'
    ];

    public function definition(): array
    {
        $type = $this->faker->randomElement(self::$objectTypes);
        
        return [
            'team_id' => Team::factory(),
            'schema_definition_id' => null,
            'root_object_id' => null,
            'type' => $type,
            'name' => $this->generateObjectName($type),
            'description' => $this->faker->sentence(rand(5, 15)),
            'date' => $this->faker->optional(0.6)->dateTimeBetween('-1 year', 'now'),
            'url' => $this->faker->optional(0.3)->url(),
            'meta' => $this->faker->optional(0.7)->passthrough($this->generateMetaData()),
        ];
    }

    public function withType(string $type): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'name' => $this->generateObjectName($type),
        ]);
    }

    public function withParent(TeamObject $parent): self
    {
        return $this->state(fn (array $attributes) => [
            'root_object_id' => $parent->root_object_id ?? $parent->id,
            'team_id' => $parent->team_id,
        ]);
    }

    public function withSchema(SchemaDefinition $schema): self
    {
        return $this->state(fn (array $attributes) => [
            'schema_definition_id' => $schema->id,
            'team_id' => $schema->team_id,
            'type' => $schema->schema['title'] ?? $attributes['type'],
        ]);
    }

    public function forRootObject(): self
    {
        return $this->state(fn (array $attributes) => [
            'root_object_id' => TeamObject::factory()->create()->id,
        ]);
    }

    public function forSchemaDefinition(): self
    {
        return $this->state(fn (array $attributes) => [
            'schema_definition_id' => SchemaDefinition::factory()->create()->id,
        ]);
    }

    protected function generateObjectName($type): string
    {
        $prefixes = [
            'Demand' => ['Request', 'Order', 'Requisition'],
            'Provider' => ['Dr.', 'Nurse', 'Specialist'],
            'Facility' => ['Medical Center', 'Hospital', 'Clinic'],
            'Diagnosis' => ['ICD-', 'DX-', 'Condition'],
            'Procedure' => ['CPT-', 'PROC-', 'Surgery'],
            'Medication' => ['Med-', 'RX-', 'Drug'],
            'Laboratory' => ['Lab-', 'Test-', 'Panel'],
            'Radiology' => ['RAD-', 'Imaging-', 'X-Ray'],
            'Document' => ['DOC-', 'File-', 'Report'],
            'Address' => ['Location-', 'Site-', 'Address'],
            'Insurance' => ['Policy-', 'Plan-', 'Coverage'],
            'Claim' => ['CLM-', 'Bill-', 'Invoice'],
            'Authorization' => ['AUTH-', 'Approval-', 'PA-'],
            'Referral' => ['REF-', 'Transfer-', 'Consult'],
            'Appointment' => ['APPT-', 'Visit-', 'Schedule']
        ];
        
        $prefix = $this->faker->randomElement($prefixes[$type] ?? [$type]);
        $suffix = strtoupper($this->faker->bothify('##??##'));
        
        return "{$prefix} {$this->faker->lastName()} {$suffix}";
    }

    protected function generateMetaData(): array
    {
        return [
            'source_system' => $this->faker->randomElement(['EHR', 'CRM', 'BILLING', 'LAB', 'RIS']),
            'import_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'version' => $this->faker->numerify('v#.#.#'),
            'tags' => $this->faker->words(rand(1, 5)),
            'custom_field_1' => $this->faker->word(),
            'custom_field_2' => $this->faker->randomNumber(5)
        ];
    }
}