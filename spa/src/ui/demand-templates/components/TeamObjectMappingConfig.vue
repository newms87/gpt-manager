<template>
    <div class="space-y-4">
        <div class="flex items-start gap-2 text-sm text-slate-600 bg-green-50 border border-green-200 rounded-lg p-3">
            <FaSolidCircleInfo class="w-4 mt-0.5 text-green-500 flex-shrink-0" />
            <div>
                Extract data from TeamObject using a schema definition and optional fragment selector.
            </div>
        </div>

        <!-- Schema Selection -->
        <div>
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                Schema Definition <span class="text-red-500">*</span>
            </label>
            <SchemaEditorToolbox
                v-model="localSchema"
                v-model:fragment="localFragment"
                :can-select="true"
                :can-select-fragment="true"
                :previewable="false"
                :editable="false"
                :clearable="true"
                button-color="bg-green-700"
                placeholder="(Select a schema)"
                @update:model-value="updateSchema"
                @update:fragment="updateFragment"
            />
            <div class="text-xs text-slate-500 mt-1">
                Select a schema definition to extract data from. Optionally select a fragment for a subset of the schema.
            </div>
        </div>

        <!-- Preview Selected Schema/Fragment -->
        <div v-if="localSchema" class="bg-white rounded-lg border border-slate-200 p-3">
            <div class="text-xs font-medium text-slate-600 mb-2">Selected Configuration:</div>
            <div class="flex items-center gap-2 text-sm">
                <FaSolidDatabase class="w-3 text-green-600" />
                <span class="font-medium">{{ localSchema.name }}</span>
                <template v-if="localFragment">
                    <FaSolidChevronRight class="w-2 text-slate-400" />
                    <FaSolidPuzzlePiece class="w-3 text-green-600" />
                    <span>{{ localFragment.name }}</span>
                </template>
                <template v-else>
                    <FaSolidChevronRight class="w-2 text-slate-400" />
                    <span class="text-slate-500 text-xs">(Full Schema)</span>
                </template>
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
                How to handle multiple matching TeamObjects.
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
import {
    FaSolidCircleInfo,
    FaSolidDatabase,
    FaSolidPuzzlePiece,
    FaSolidChevronRight
} from "danx-icon";
import { SelectField, TextField } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox.vue";
import type { TemplateVariable } from "../types";
import type { SchemaDefinition, SchemaFragment } from "@/types";

const modelValue = defineModel<TemplateVariable>({ required: true });

const emit = defineEmits<{
    "update:schema-association": [value: number | undefined];
    "update:multi-value-strategy": [value: string];
    "update:multi-value-separator": [value: string];
}>();

// Local state for schema/fragment selection
const localSchema = ref<SchemaDefinition | null>(
    modelValue.value.schema_association || null
);
const localFragment = ref<SchemaFragment | null>(null);

// Multi-value strategy options
const multiValueStrategyOptions = [
    { label: "Join (concatenate all values)", value: "join" },
    { label: "First (use first value only)", value: "first" },
    { label: "Unique (join unique values only)", value: "unique" }
];

// Computed
const showSeparator = computed(() => {
    const strategy = modelValue.value.multi_value_strategy;
    return strategy === "join" || strategy === "unique";
});

// Watch for external changes
watch(() => modelValue.value.schema_association, (newValue) => {
    if (newValue && newValue !== localSchema.value) {
        localSchema.value = newValue;
    }
});

// Methods
const updateSchema = (schema: SchemaDefinition | null) => {
    localSchema.value = schema;
    modelValue.value.schema_association = schema || undefined;
    modelValue.value.team_object_schema_association_id = schema?.id || undefined;
    emit("update:schema-association", schema?.id || undefined);
};

const updateFragment = (fragment: SchemaFragment | null) => {
    localFragment.value = fragment;
    // Fragment selector is stored within the schema association
    // Update if needed based on your backend structure
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
