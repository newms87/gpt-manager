<template>
    <div class="space-y-4">
        <div class="flex items-start gap-2 text-sm text-slate-600 bg-blue-50 border border-blue-200 rounded-lg p-3">
            <FaSolidCircleInfo class="w-4 mt-0.5 text-blue-500 flex-shrink-0" />
            <div>
                Extract data from uploaded artifacts. Filter by categories and specify which field to extract using a fragment selector.
            </div>
        </div>

        <!-- Categories -->
        <div>
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                Artifact Categories (Optional)
            </label>
            <TextField
                :model-value="categoriesString"
                placeholder="e.g., medical_records, insurance_documents"
                @update:model-value="updateCategories"
            />
            <div class="text-xs text-slate-500 mt-1">
                Comma-separated list of categories to filter artifacts. Leave empty to include all artifacts.
            </div>
        </div>

        <!-- Fragment Selector -->
        <div>
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                Fragment Selector (Optional)
            </label>
            <FragmentSelectorConfigField
                v-model="localFragmentSelector"
                :delay="300"
                @update:model-value="updateFragmentSelector"
            />
            <div class="text-xs text-slate-500 mt-1">
                YAML configuration to specify which field to extract (text_content, json_content, meta) and optional nested path.
            </div>
        </div>

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
                How to handle multiple matching artifacts.
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
    </div>
</template>

<script setup lang="ts">
import { useDebounceFn } from "@vueuse/core";
import { FaSolidCircleInfo } from "danx-icon";
import { SelectField, TextField } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";
import FragmentSelectorConfigField from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/Fields/FragmentSelectorConfigField.vue";
import type { TemplateVariable } from "../types";
import type { FragmentSelector } from "@/types/prompts";

const modelValue = defineModel<TemplateVariable>({ required: true });

const emit = defineEmits<{
    "update:artifact-categories": [value: string[] | undefined];
    "update:artifact-fragment-selector": [value: FragmentSelector | undefined];
    "update:multi-value-strategy": [value: string];
    "update:multi-value-separator": [value: string];
}>();

// Local state for fragment selector
const localFragmentSelector = ref<FragmentSelector | string | null>(
    modelValue.value.artifact_fragment_selector || null
);

// Multi-value strategy options
const multiValueStrategyOptions = [
    { label: "Join (concatenate all values)", value: "join" },
    { label: "First (use first value only)", value: "first" },
    { label: "Unique (join unique values only)", value: "unique" }
];

// Computed
const categoriesString = computed(() => {
    return modelValue.value.artifact_categories?.join(", ") || "";
});

const showSeparator = computed(() => {
    const strategy = modelValue.value.multi_value_strategy;
    return strategy === "join" || strategy === "unique";
});

// Watch for external changes
watch(() => modelValue.value.artifact_fragment_selector, (newValue) => {
    if (newValue !== localFragmentSelector.value) {
        localFragmentSelector.value = newValue || null;
    }
});

// Methods
const updateCategoriesImmediate = (value: string) => {
    const categories = value
        .split(",")
        .map(c => c.trim())
        .filter(c => c.length > 0);

    const categoryValue = categories.length > 0 ? categories : undefined;
    modelValue.value.artifact_categories = categoryValue;
    emit("update:artifact-categories", categoryValue);
};

const updateCategories = useDebounceFn(updateCategoriesImmediate, 500);

const updateFragmentSelector = (value: FragmentSelector | string | null) => {
    localFragmentSelector.value = value;
    const selectorValue = value as FragmentSelector || undefined;
    modelValue.value.artifact_fragment_selector = selectorValue;
    emit("update:artifact-fragment-selector", selectorValue);
};

const updateMultiValueStrategy = (value: "join" | "first" | "unique") => {
    modelValue.value.multi_value_strategy = value;
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
</script>
