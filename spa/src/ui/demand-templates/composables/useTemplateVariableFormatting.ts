import { useDebounceFn } from "@vueuse/core";
import { computed } from "vue";
import type { TemplateVariable } from "../types";

/**
 * Shared composable for template variable formatting configuration
 * Used by ArtifactMappingConfig and TeamObjectMappingConfig
 */
export function useTemplateVariableFormatting(
    modelValue: { value: TemplateVariable },
    emit: {
        (e: "update:multi-value-strategy", value: string): void;
        (e: "update:multi-value-separator", value: string): void;
    }
) {
    // Multi-value strategy options
    const multiValueStrategyOptions = [
        { label: "Join (concatenate all values)", value: "join" },
        { label: "First (use first value only)", value: "first" },
        { label: "Unique (join unique values only)", value: "unique" },
        { label: "MAX (maximum value)", value: "max" },
        { label: "MIN (minimum value)", value: "min" },
        { label: "AVG (average value)", value: "avg" },
        { label: "SUM (sum of values)", value: "sum" }
    ];

    // Format type options
    const formatTypeOptions = [
        { label: "Text (no formatting)", value: "text" },
        { label: "Integer (whole number)", value: "integer" },
        { label: "Decimal (with decimal places)", value: "decimal" },
        { label: "Currency ($1,234.56)", value: "currency" },
        { label: "Percentage (45%)", value: "percentage" },
        { label: "Date (readable format)", value: "date" }
    ];

    // Computed properties
    const showSeparator = computed(() => {
        const strategy = modelValue.value.multi_value_strategy;
        return strategy === "join" || strategy === "unique";
    });

    const showDecimalPlaces = computed(() => {
        const formatType = modelValue.value.value_format_type;
        return formatType === "decimal" || formatType === "currency" || formatType === "percentage";
    });

    const showCurrencyCode = computed(() => {
        return modelValue.value.value_format_type === "currency";
    });

    // Update methods
    const updateMultiValueStrategy = (value: string) => {
        modelValue.value.multi_value_strategy = value as any;
        emit("update:multi-value-strategy", value);

        // Set default separator if switching to join/unique
        if ((value === "join" || value === "unique") && !modelValue.value.multi_value_separator) {
            modelValue.value.multi_value_separator = ", ";
            emit("update:multi-value-separator", ", ");
        }
    };

    const updateSeparatorImmediate = (value: string) => {
        const separatorValue = value || undefined;
        modelValue.value.multi_value_separator = separatorValue;
        emit("update:multi-value-separator", separatorValue);
    };

    const updateSeparator = useDebounceFn(updateSeparatorImmediate, 500);

    const updateFormatType = (value: string) => {
        modelValue.value.value_format_type = value as any;
    };

    const updateDecimalPlaces = (value: string) => {
        const numValue = parseInt(value);
        if (numValue >= 0 && numValue <= 4) {
            modelValue.value.decimal_places = numValue;
        }
    };

    const updateCurrencyCode = (value: string) => {
        modelValue.value.currency_code = value.toUpperCase();
    };

    return {
        // Options
        multiValueStrategyOptions,
        formatTypeOptions,

        // Computed
        showSeparator,
        showDecimalPlaces,
        showCurrencyCode,

        // Methods
        updateMultiValueStrategy,
        updateSeparator,
        updateFormatType,
        updateDecimalPlaces,
        updateCurrencyCode
    };
}
