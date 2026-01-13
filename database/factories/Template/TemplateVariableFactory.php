<?php

namespace Database\Factories\Template;

use App\Models\Schema\SchemaAssociation;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Template\TemplateVariable>
 */
class TemplateVariableFactory extends Factory
{
    protected $model = TemplateVariable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_definition_id' => TemplateDefinition::factory(),
            'name'                   => $this->faker->unique()->word(),
            'description'            => $this->faker->optional()->sentence(),
            'mapping_type'           => $this->faker->randomElement([
                TemplateVariable::MAPPING_TYPE_AI,
                TemplateVariable::MAPPING_TYPE_ARTIFACT,
                TemplateVariable::MAPPING_TYPE_TEAM_OBJECT,
            ]),
            'artifact_categories'               => null,
            'artifact_fragment_selector'        => null,
            'team_object_schema_association_id' => null,
            'ai_instructions'                   => null,
            'multi_value_strategy'              => TemplateVariable::STRATEGY_JOIN,
            'multi_value_separator'             => ', ',
        ];
    }

    /**
     * Indicate that the variable uses AI mapping.
     */
    public function aiMapped(): static
    {
        return $this->state(fn(array $attributes) => [
            'mapping_type'                      => TemplateVariable::MAPPING_TYPE_AI,
            'ai_instructions'                   => $this->faker->paragraph(),
            'artifact_categories'               => null,
            'artifact_fragment_selector'        => null,
            'team_object_schema_association_id' => null,
        ]);
    }

    /**
     * Indicate that the variable uses artifact mapping.
     */
    public function artifactMapped(): static
    {
        return $this->state(fn(array $attributes) => [
            'mapping_type'               => TemplateVariable::MAPPING_TYPE_ARTIFACT,
            'artifact_categories'        => $this->faker->randomElements(['medical', 'legal', 'financial'], 2),
            'artifact_fragment_selector' => [
                'type'     => 'css_selector',
                'selector' => '.content .section',
            ],
            'ai_instructions'                   => null,
            'team_object_schema_association_id' => null,
        ]);
    }

    /**
     * Indicate that the variable uses team object mapping.
     */
    public function teamObjectMapped(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'mapping_type'                      => TemplateVariable::MAPPING_TYPE_TEAM_OBJECT,
                'team_object_schema_association_id' => SchemaAssociation::factory(),
                'artifact_categories'               => null,
                'artifact_fragment_selector'        => null,
                'ai_instructions'                   => null,
            ];
        });
    }

    /**
     * Set a specific multi-value strategy.
     */
    public function withStrategy(string $strategy): static
    {
        return $this->state(fn(array $attributes) => [
            'multi_value_strategy' => $strategy,
        ]);
    }

    /**
     * Set the variable to use the 'first' strategy.
     */
    public function firstStrategy(): static
    {
        return $this->state(fn(array $attributes) => [
            'multi_value_strategy' => TemplateVariable::STRATEGY_FIRST,
        ]);
    }

    /**
     * Set the variable to use the 'unique' strategy.
     */
    public function uniqueStrategy(): static
    {
        return $this->state(fn(array $attributes) => [
            'multi_value_strategy' => TemplateVariable::STRATEGY_UNIQUE,
        ]);
    }

    /**
     * Set the variable to be for a specific template definition.
     */
    public function forTemplate(TemplateDefinition $template): static
    {
        return $this->state(fn(array $attributes) => [
            'template_definition_id' => $template->id,
        ]);
    }
}
