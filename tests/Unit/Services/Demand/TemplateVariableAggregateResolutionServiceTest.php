<?php

namespace Tests\Unit\Services\Demand;

use App\Models\Task\Artifact;
use App\Models\Template\TemplateVariable;
use App\Services\Demand\TemplateVariableResolutionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateVariableAggregateResolutionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // ==========================================
    // INTEGRATION TESTS (Aggregate + Formatting)
    // ==========================================

    #[Test]
    public function integration_sum_with_currency_formatting(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_SUM,
            'multi_value_separator'      => ', ',
            'value_format_type'          => TemplateVariable::FORMAT_CURRENCY,
            'currency_code'              => 'USD',
            'decimal_places'             => 2,
        ]);

        $artifact1 = Artifact::factory()->create([
            'text_content' => '$100.50',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => '$250.75',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => '$149.25',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('$500.50', $result); // Sum: 100.5 + 250.75 + 149.25 = 500.5, formatted as USD
    }

    #[Test]
    public function integration_avg_with_decimal_formatting(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_AVG,
            'multi_value_separator'      => ', ',
            'value_format_type'          => TemplateVariable::FORMAT_DECIMAL,
            'decimal_places'             => 3,
        ]);

        $artifact1 = Artifact::factory()->create([
            'text_content' => '10.5',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => '20.7',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => '15.2',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('15.467', $result); // Avg: (10.5 + 20.7 + 15.2) / 3 = 15.4666..., formatted to 3 decimals
    }

    #[Test]
    public function integration_max_with_integer_formatting(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_MAX,
            'multi_value_separator'      => ', ',
            'value_format_type'          => TemplateVariable::FORMAT_INTEGER,
        ]);

        $artifact1 = Artifact::factory()->create([
            'text_content' => '1234.67',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => '5678.90',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact3 = Artifact::factory()->create([
            'text_content' => '3456.12',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('5,679', $result); // Max: 5678.90, rounded to integer with thousands separator
    }

    #[Test]
    public function integration_with_empty_values_returns_empty_string(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_SUM,
            'multi_value_separator'      => ', ',
            'value_format_type'          => TemplateVariable::FORMAT_CURRENCY,
            'currency_code'              => 'USD',
            'decimal_places'             => 2,
        ]);

        $artifacts = collect([]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        $this->assertEquals('', $result);
    }

    #[Test]
    public function integration_with_all_non_numeric_and_formatting_returns_zero(): void
    {
        // Given
        $variable = TemplateVariable::factory()->artifactMapped()->create([
            'artifact_categories'        => null,
            'artifact_fragment_selector' => null,
            'multi_value_strategy'       => TemplateVariable::STRATEGY_SUM,
            'multi_value_separator'      => ', ',
            'value_format_type'          => TemplateVariable::FORMAT_CURRENCY,
            'currency_code'              => 'USD',
            'decimal_places'             => 2,
        ]);

        $artifact1 = Artifact::factory()->create([
            'text_content' => 'not a number',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifact2 = Artifact::factory()->create([
            'text_content' => 'also not a number',
            'team_id'      => $this->user->currentTeam->id,
        ]);

        $artifacts = collect([$artifact1, $artifact2]);

        // When
        $result = app(TemplateVariableResolutionService::class)->resolveVariable($variable, $artifacts);

        // Then
        // Note: formatValue() returns early for empty values including "0" (empty("0") is true in PHP)
        $this->assertEquals('0', $result);
    }
}
