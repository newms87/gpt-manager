<?php

namespace Tests\Unit\Services;

use App\Services\ValueFormattingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;

class ValueFormattingServiceTest extends AuthenticatedTestCase
{
    // ==========================================
    // INTEGER FORMATTING TESTS
    // ==========================================

    #[Test]
    public function format_integer_rounds_and_adds_thousands_separator(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatInteger('1234.789');

        // Then
        $this->assertEquals('1,235', $result);
    }

    #[Test]
    public function format_integer_with_negative_numbers(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatInteger('-1234.567');

        // Then
        $this->assertEquals('-1,235', $result);
    }

    // ==========================================
    // DECIMAL FORMATTING TESTS
    // ==========================================

    #[Test]
    public function format_decimal_with_default_two_decimal_places(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatDecimal('151.666');

        // Then
        $this->assertEquals('151.67', $result);
    }

    #[Test]
    public function format_decimal_with_zero_decimal_places(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatDecimal('123.789', 0);

        // Then
        $this->assertEquals('124', $result);
    }

    #[Test]
    public function format_decimal_with_four_decimal_places(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatDecimal('123.456789', 4);

        // Then
        $this->assertEquals('123.4568', $result);
    }

    #[Test]
    public function format_decimal_with_thousands_separator(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatDecimal('1234.567', 2);

        // Then
        $this->assertEquals('1,234.57', $result);
    }

    // ==========================================
    // CURRENCY FORMATTING TESTS
    // ==========================================

    #[Test]
    public function format_currency_usd_with_default_settings(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatCurrency('1234.56', 'USD', 2);

        // Then
        $this->assertEquals('$1,234.56', $result);
    }

    #[Test]
    public function format_currency_eur(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatCurrency('1234.56', 'EUR', 2);

        // Then
        $this->assertEquals('1,234.56 €', $result);
    }

    #[Test]
    public function format_currency_gbp(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatCurrency('1234.56', 'GBP', 2);

        // Then
        $this->assertEquals('£1,234.56', $result);
    }

    #[Test]
    public function format_currency_with_custom_decimal_places(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatCurrency('1234.56', 'USD', 0);

        // Then
        $this->assertEquals('$1,235', $result);
    }

    #[Test]
    public function format_currency_with_negative_amounts(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatCurrency('-1234.56', 'USD', 2);

        // Then
        $this->assertEquals('$-1,234.56', $result);
    }

    // ==========================================
    // PERCENTAGE FORMATTING TESTS
    // ==========================================

    #[Test]
    public function format_percentage_with_decimal_input(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatPercentage('0.45', 2);

        // Then
        $this->assertEquals('45.00%', $result);
    }

    #[Test]
    public function format_percentage_with_whole_number_input(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatPercentage('45', 2);

        // Then
        $this->assertEquals('45.00%', $result);
    }

    #[Test]
    public function format_percentage_with_custom_decimal_places(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatPercentage('0.4567', 0);

        // Then
        $this->assertEquals('46%', $result);
    }

    // ==========================================
    // DATE FORMATTING TESTS
    // ==========================================

    #[Test]
    public function format_date_with_iso_format(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatDate('2025-01-01');

        // Then
        $this->assertEquals('January 1st, 2025', $result);
    }

    #[Test]
    public function format_date_with_various_dates(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatDate('2025-03-21');

        // Then
        $this->assertEquals('March 21st, 2025', $result);
    }

    #[Test]
    public function format_date_with_invalid_date_returns_original(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatDate('not a date');

        // Then
        $this->assertEquals('not a date', $result);
    }

    // ==========================================
    // TEXT FORMATTING TESTS
    // ==========================================

    #[Test]
    public function format_text_returns_value_as_is(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatText('1234.56 some text');

        // Then
        $this->assertEquals('1234.56 some text', $result);
    }

    // ==========================================
    // MAIN FORMAT METHOD TESTS
    // ==========================================

    #[Test]
    public function format_method_with_integer_type(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->format('1234.789', 'integer');

        // Then
        $this->assertEquals('1,235', $result);
    }

    #[Test]
    public function format_method_with_decimal_type_and_options(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->format('1234.567', 'decimal', ['decimals' => 2]);

        // Then
        $this->assertEquals('1,234.57', $result);
    }

    #[Test]
    public function format_method_with_currency_type_and_options(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->format('1234.56', 'currency', [
            'currencyCode' => 'EUR',
            'decimals' => 2,
        ]);

        // Then
        $this->assertEquals('1,234.56 €', $result);
    }

    #[Test]
    public function format_method_with_percentage_type_and_options(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->format('0.45', 'percentage', ['decimals' => 2]);

        // Then
        $this->assertEquals('45.00%', $result);
    }

    #[Test]
    public function format_method_with_date_type(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->format('2025-01-01', 'date');

        // Then
        $this->assertEquals('January 1st, 2025', $result);
    }

    #[Test]
    public function format_method_with_text_type(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->format('some text', 'text');

        // Then
        $this->assertEquals('some text', $result);
    }

    #[Test]
    public function format_method_with_unknown_type_defaults_to_text(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->format('some text', 'unknown-type');

        // Then
        $this->assertEquals('some text', $result);
    }

    // ==========================================
    // EDGE CASE TESTS
    // ==========================================

    #[Test]
    public function format_with_null_value_returns_empty_string(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatText(null);

        // Then
        $this->assertEquals('', $result);
    }

    #[Test]
    public function format_integer_with_non_numeric_returns_original(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatInteger('not a number');

        // Then
        $this->assertEquals('not a number', $result);
    }

    #[Test]
    public function format_decimal_with_non_numeric_returns_original(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatDecimal('not a number', 2);

        // Then
        $this->assertEquals('not a number', $result);
    }

    #[Test]
    public function format_currency_with_non_numeric_returns_original(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatCurrency('not a number', 'USD', 2);

        // Then
        $this->assertEquals('not a number', $result);
    }

    #[Test]
    public function format_percentage_with_non_numeric_returns_original(): void
    {
        // Given
        $service = app(ValueFormattingService::class);

        // When
        $result = $service->formatPercentage('not a number', 2);

        // Then
        $this->assertEquals('not a number', $result);
    }
}
