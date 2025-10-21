<?php

namespace Tests\Unit\Services;

use App\Services\ArrayAggregationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class ArrayAggregationServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    // ==========================================
    // MAX STRATEGY TESTS
    // ==========================================

    #[Test]
    public function max_with_numeric_values_returns_maximum(): void
    {
        // Given
        $values = ['100', '250', '75'];

        // When
        $result = app(ArrayAggregationService::class)->max($values);

        // Then
        $this->assertEquals('250', $result);
    }

    #[Test]
    public function max_with_currency_values_returns_maximum(): void
    {
        // Given
        $values = ['$100.50', '$250.75', '$75.25'];

        // When
        $result = app(ArrayAggregationService::class)->max($values);

        // Then
        $this->assertEquals('250.75', $result);
    }

    #[Test]
    public function max_with_date_strings_returns_latest_date(): void
    {
        // Given
        $values = ['2025-01-15', '2025-03-20', '2025-02-10'];

        // When
        $result = app(ArrayAggregationService::class)->max($values);

        // Then
        $this->assertEquals('2025-03-20', $result);
    }

    #[Test]
    public function max_with_empty_array_returns_zero(): void
    {
        // Given
        $values = [];

        // When
        $result = app(ArrayAggregationService::class)->max($values);

        // Then
        $this->assertEquals('0', $result);
    }

    #[Test]
    public function max_with_all_non_numeric_values_returns_zero(): void
    {
        // Given
        $values = ['not a number', 'also not a number'];

        // When
        $result = app(ArrayAggregationService::class)->max($values);

        // Then
        $this->assertEquals('0', $result);
    }

    #[Test]
    public function max_with_mixed_numeric_and_non_numeric_skips_non_numeric(): void
    {
        // Given
        $values = ['100', 'not a number', '250'];

        // When
        $result = app(ArrayAggregationService::class)->max($values);

        // Then
        $this->assertEquals('250', $result);
    }

    // ==========================================
    // MIN STRATEGY TESTS
    // ==========================================

    #[Test]
    public function min_with_numeric_values_returns_minimum(): void
    {
        // Given
        $values = ['100', '75', '250'];

        // When
        $result = app(ArrayAggregationService::class)->min($values);

        // Then
        $this->assertEquals('75', $result);
    }

    #[Test]
    public function min_with_currency_values_returns_minimum(): void
    {
        // Given
        $values = ['$100.50', '$75.25', '$250.75'];

        // When
        $result = app(ArrayAggregationService::class)->min($values);

        // Then
        $this->assertEquals('75.25', $result);
    }

    #[Test]
    public function min_with_date_strings_returns_earliest_date(): void
    {
        // Given
        $values = ['2025-03-20', '2025-01-15', '2025-02-10'];

        // When
        $result = app(ArrayAggregationService::class)->min($values);

        // Then
        $this->assertEquals('2025-01-15', $result);
    }

    #[Test]
    public function min_with_negative_numbers_works_correctly(): void
    {
        // Given
        $values = ['100', '-50', '25'];

        // When
        $result = app(ArrayAggregationService::class)->min($values);

        // Then
        $this->assertEquals('-50', $result);
    }

    // ==========================================
    // AVG STRATEGY TESTS
    // ==========================================

    #[Test]
    public function avg_with_integers_calculates_correct_average(): void
    {
        // Given
        $values = ['100', '200', '150'];

        // When
        $result = app(ArrayAggregationService::class)->avg($values);

        // Then
        $this->assertEquals('150', $result); // (100 + 200 + 150) / 3 = 150
    }

    #[Test]
    public function avg_with_decimals_calculates_correct_average(): void
    {
        // Given
        $values = ['10.5', '20.5', '14.0'];

        // When
        $result = app(ArrayAggregationService::class)->avg($values);

        // Then
        $this->assertEquals('15', $result); // (10.5 + 20.5 + 14.0) / 3 = 15
    }

    #[Test]
    public function avg_skips_non_numeric_values(): void
    {
        // Given
        $values = ['100', 'not a number', '200'];

        // When
        $result = app(ArrayAggregationService::class)->avg($values);

        // Then
        $this->assertEquals('150', $result); // (100 + 200) / 2 = 150
    }

    #[Test]
    public function avg_with_single_value_returns_that_value(): void
    {
        // Given
        $values = ['42'];

        // When
        $result = app(ArrayAggregationService::class)->avg($values);

        // Then
        $this->assertEquals('42', $result);
    }

    #[Test]
    public function avg_with_empty_array_returns_zero(): void
    {
        // Given
        $values = [];

        // When
        $result = app(ArrayAggregationService::class)->avg($values);

        // Then
        $this->assertEquals('0', $result);
    }

    // ==========================================
    // SUM STRATEGY TESTS
    // ==========================================

    #[Test]
    public function sum_with_integers_calculates_correct_sum(): void
    {
        // Given
        $values = ['100', '200', '50'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('350', $result); // 100 + 200 + 50 = 350
    }

    #[Test]
    public function sum_with_currency_values_returns_sum(): void
    {
        // Given
        $values = ['$100.00', '$200.50'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('300.5', $result); // 100 + 200.5 = 300.5
    }

    #[Test]
    public function sum_with_percentages_returns_sum(): void
    {
        // Given
        $values = ['50%', '25%'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('75', $result); // 50 + 25 = 75
    }

    #[Test]
    public function sum_skips_non_numeric_values(): void
    {
        // Given
        $values = ['100', 'not a number', '200'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('300', $result); // 100 + 200 = 300
    }

    #[Test]
    public function sum_with_all_non_numeric_returns_zero(): void
    {
        // Given
        $values = ['not a number', 'also not a number'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('0', $result);
    }

    // ==========================================
    // VALUE PARSING TESTS
    // ==========================================

    #[Test]
    public function parsing_currency_with_dollar_sign_and_commas(): void
    {
        // Given
        $values = ['$1,234.56'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('1234.56', $result);
    }

    #[Test]
    public function parsing_multiple_currency_symbols(): void
    {
        // Given
        $values = ['€1,000.00', '£500.50', '¥250.25'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('1750.75', $result); // 1000 + 500.5 + 250.25
    }

    #[Test]
    public function parsing_numbers_with_commas_and_spaces(): void
    {
        // Given
        $values = ['1 234 567.89'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('1234567.89', $result);
    }

    #[Test]
    public function parsing_percentages_strips_percent_sign(): void
    {
        // Given
        $values = ['50%'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('50', $result);
    }

    #[Test]
    public function parsing_negative_numbers_with_currency(): void
    {
        // Given
        $values = ['-$123.45'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('-123.45', $result);
    }

    #[Test]
    public function parsing_scientific_notation(): void
    {
        // Given
        $values = ['1.5e3', '2.5E2'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('1750', $result); // 1500 + 250
    }

    #[Test]
    public function parsing_invalid_strings_returns_zero_when_all_invalid(): void
    {
        // Given
        $values = ['totally invalid', 'not a number at all'];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('0', $result);
    }

    // ==========================================
    // NATIVE TYPE TESTS
    // ==========================================

    #[Test]
    public function max_with_native_integers(): void
    {
        // Given
        $values = [100, 250, 75];

        // When
        $result = app(ArrayAggregationService::class)->max($values);

        // Then
        $this->assertEquals('250', $result);
    }

    #[Test]
    public function sum_with_native_floats(): void
    {
        // Given
        $values = [100.5, 200.75, 50.25];

        // When
        $result = app(ArrayAggregationService::class)->sum($values);

        // Then
        $this->assertEquals('351.5', $result);
    }

    #[Test]
    public function avg_with_mixed_types(): void
    {
        // Given
        $values = [100, '200', 150.5];

        // When
        $result = app(ArrayAggregationService::class)->avg($values);

        // Then
        $this->assertEquals('150.16666666667', $result); // (100 + 200 + 150.5) / 3 = 150.166...
    }
}
