<template>
    <div class="space-y-4">
        <!-- Multi-value Strategy -->
        <div>
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                Multi-value Strategy
            </label>
            <SelectField
                :model-value="modelValue.multi_value_strategy || 'join'"
                :options="multiValueStrategyOptions"
                @update:model-value="updateMultiValueStrategy"
            />
            <div class="text-xs text-slate-500 mt-1">
                {{ multiValueDescription }}
            </div>
        </div>

        <!-- Separator (shown only for join/unique) -->
        <div v-if="showSeparator">
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                Separator
            </label>
            <TextField
                :model-value="modelValue.multi_value_separator || ', '"
                placeholder="e.g., , or ; or newline"
                @update:model-value="updateSeparator"
            />
            <div class="text-xs text-slate-500 mt-1">
                Separator to use when joining multiple values.
            </div>
        </div>

        <!-- Value Formatting Section -->
        <div>
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                Value Format Type
            </label>
            <SelectField
                :model-value="modelValue.value_format_type || 'text'"
                :options="formatTypeOptions"
                @update:model-value="updateFormatType"
            />
            <div class="text-xs text-slate-500 mt-1">
                How to format the output value
            </div>
        </div>

        <!-- Decimal Places (conditional) -->
        <div v-if="showDecimalPlaces">
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                Decimal Places
            </label>
            <TextField
                :model-value="modelValue.decimal_places?.toString() || '2'"
                type="number"
                min="0"
                max="4"
                @update:model-value="updateDecimalPlaces"
            />
            <div class="text-xs text-slate-500 mt-1">
                Number of decimal places (0-4)
            </div>
        </div>

        <!-- Currency Code (conditional) -->
        <div v-if="showCurrencyCode">
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                Currency Code
            </label>
            <TextField
                :model-value="modelValue.currency_code || 'USD'"
                placeholder="e.g., USD, EUR, GBP"
                maxlength="3"
                @update:model-value="updateCurrencyCode"
            />
            <div class="text-xs text-slate-500 mt-1">
                3-letter currency code (ISO 4217)
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { SelectField, TextField } from "quasar-ui-danx";
import { useTemplateVariableFormatting } from "../composables/useTemplateVariableFormatting";
import type { TemplateVariable } from "../types";

const props = withDefaults(defineProps<{
    multiValueDescription?: string;
}>(), {
    multiValueDescription: "How to handle multiple matching values."
});

const modelValue = defineModel<TemplateVariable>({ required: true });

const emit = defineEmits<{
    "update:multi-value-strategy": [value: string];
    "update:multi-value-separator": [value: string];
}>();

const {
    multiValueStrategyOptions,
    formatTypeOptions,
    showSeparator,
    showDecimalPlaces,
    showCurrencyCode,
    updateMultiValueStrategy,
    updateSeparator,
    updateFormatType,
    updateDecimalPlaces,
    updateCurrencyCode
} = useTemplateVariableFormatting(modelValue, emit);
</script>
