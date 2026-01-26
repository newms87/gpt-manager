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
            <SchemaAndFragmentSelector
                v-model="localSchema"
                v-model:fragment="localFragment"
                :can-select-schema="true"
                :can-select-fragment="true"
                :previewable="false"
                can-edit-schema
                dialog
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

        <!-- Formatting Fields -->
        <TemplateVariableFormattingFields
            v-model="modelValue"
            multi-value-description="How to handle multiple matching TeamObjects."
            @update:multi-value-strategy="emit('update:multi-value-strategy', $event)"
            @update:multi-value-separator="emit('update:multi-value-separator', $event)"
        />
    </div>
</template>

<script setup lang="ts">
import {
    FaSolidCircleInfo,
    FaSolidDatabase,
    FaSolidPuzzlePiece,
    FaSolidChevronRight
} from "danx-icon";
import { ref, watch } from "vue";
import SchemaAndFragmentSelector from "@/components/Modules/SchemaEditor/SchemaAndFragmentSelector.vue";
import TemplateVariableFormattingFields from "./TemplateVariableFormattingFields.vue";
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
</script>
