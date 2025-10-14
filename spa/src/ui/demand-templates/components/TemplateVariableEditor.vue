<template>
    <InfoDialog
        :model-value="true"
        class="ui-mode"
        :title="`Configure Variable: ${variable.name || 'Variable'}`"
        content-class="w-[70vw] max-w-4xl h-[85vh] overflow-hidden bg-white"
        @close="$emit('close')"
    >
        <div class="h-full flex flex-col overflow-hidden">
            <div class="flex-grow overflow-y-auto p-6 space-y-6">
                <!-- Error Display -->
                <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <div class="flex items-center gap-2 text-red-700">
                        <FaSolidCircleExclamation class="w-4" />
                        <span class="text-sm font-medium">{{ error }}</span>
                    </div>
                </div>

                <!-- Variable Name -->
                <div>
                    <label class="text-sm font-medium text-slate-700 mb-2 block">
                        Variable Name
                    </label>
                    <TextField
                        v-model="localVariable.name"
                        placeholder="e.g., patient_name, claim_number"
                        readonly
                        class="bg-slate-50"
                    />
                    <div class="text-xs text-slate-500 mt-1">
                        Variable name is set from the Google Doc template and cannot be changed
                    </div>
                </div>

                <!-- Mapping Type -->
                <div>
                    <label class="text-sm font-medium text-slate-700 mb-2 block">
                        Mapping Type <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-3">
                        <div
                            v-for="type in mappingTypes"
                            :key="type.value"
                            class="flex-1 p-4 rounded-lg border-2 cursor-pointer transition-all"
                            :class="localVariable.mapping_type === type.value
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-slate-200 hover:border-slate-300'"
                            @click="onMappingTypeChange(type.value)"
                        >
                            <div class="flex items-center gap-2 mb-2">
                                <component :is="type.icon" class="w-5" :class="type.iconColor" />
                                <span class="font-semibold text-slate-800">{{ type.label }}</span>
                            </div>
                            <div class="text-xs text-slate-600">{{ type.description }}</div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="text-sm font-medium text-slate-700 mb-2 block">
                        Description
                    </label>
                    <TextField
                        v-model="localVariable.description"
                        placeholder="Optional description for this variable"
                        @update:model-value="debouncedUpdateDescription"
                    />
                </div>

                <!-- Conditional Configuration Panels -->
                <div class="bg-slate-50 rounded-lg p-6">
                    <AiMappingConfig
                        v-if="localVariable.mapping_type === 'ai'"
                        v-model="localVariable"
                        @update:ai-instructions="debouncedUpdateAiInstructions"
                    />
                    <ArtifactMappingConfig
                        v-if="localVariable.mapping_type === 'artifact'"
                        v-model="localVariable"
                        @update:artifact-categories="updateArtifactCategories"
                        @update:artifact-fragment-selector="updateArtifactFragmentSelector"
                        @update:multi-value-strategy="updateMultiValueStrategy"
                        @update:multi-value-separator="updateMultiValueSeparator"
                    />
                    <TeamObjectMappingConfig
                        v-if="localVariable.mapping_type === 'team_object'"
                        v-model="localVariable"
                        @update:schema-association="updateSchemaAssociation"
                        @update:multi-value-strategy="updateMultiValueStrategy"
                        @update:multi-value-separator="updateMultiValueSeparator"
                    />
                </div>
            </div>
        </div>
    </InfoDialog>
</template>

<script setup lang="ts">
import { useDebounceFn } from "@vueuse/core";
import {
    FaSolidBrain as AiIcon,
    FaSolidCircleExclamation,
    FaSolidDatabase as TeamObjectIcon,
    FaSolidFile as ArtifactIcon
} from "danx-icon";
import { InfoDialog, TextField } from "quasar-ui-danx";
import { ref, watch } from "vue";
import { useTemplateVariables } from "../composables";
import type { TemplateVariable } from "../types";
import AiMappingConfig from "./AiMappingConfig.vue";
import ArtifactMappingConfig from "./ArtifactMappingConfig.vue";
import TeamObjectMappingConfig from "./TeamObjectMappingConfig.vue";

const props = defineProps<{
    variable: TemplateVariable;
    templateId?: number;
}>();

const emit = defineEmits<{
    "close": [];
    "save": [variable: TemplateVariable];
}>();

// Composable
const { updateVariable, isLoading, error } = useTemplateVariables();

// State
const localVariable = ref<TemplateVariable>({ ...props.variable });

// Mapping types configuration
const mappingTypes = [
    {
        value: "ai",
        label: "AI",
        icon: AiIcon,
        iconColor: "text-purple-500",
        description: "AI resolves based on context"
    },
    {
        value: "artifact",
        label: "Artifact",
        icon: ArtifactIcon,
        iconColor: "text-blue-500",
        description: "Extract from uploaded artifacts"
    },
    {
        value: "team_object",
        label: "Team Object",
        icon: TeamObjectIcon,
        iconColor: "text-green-500",
        description: "Extract from TeamObject data"
    }
];

// Watch for changes to variable prop
watch(() => props.variable, (newVariable) => {
    localVariable.value = { ...newVariable };
});

// Methods
const formatVariableName = (name: string) => {
    return `{{${name}}}`;
};

const onMappingTypeChange = (newType: "ai" | "artifact" | "team_object") => {
    localVariable.value.mapping_type = newType;

    // Prepare fields to clear based on type
    const clearFields: Partial<TemplateVariable> = { mapping_type: newType };

    if (newType !== "ai") {
        clearFields.ai_instructions = undefined;
        localVariable.value.ai_instructions = undefined;
    }
    if (newType !== "artifact") {
        clearFields.artifact_categories = undefined;
        clearFields.artifact_fragment_selector = undefined;
        localVariable.value.artifact_categories = undefined;
        localVariable.value.artifact_fragment_selector = undefined;
    }
    if (newType !== "team_object") {
        clearFields.team_object_schema_association_id = undefined;
        localVariable.value.team_object_schema_association_id = undefined;
        localVariable.value.schema_association = undefined;
    }

    // Send only the changed fields
    updateVariable(localVariable.value.id, clearFields);
};

// Individual field update handlers
const updateDescription = (description: string) => {
    updateVariable(localVariable.value.id, { description });
};

const updateAiInstructions = (ai_instructions: string) => {
    updateVariable(localVariable.value.id, { ai_instructions });
};

const updateArtifactCategories = (artifact_categories: string[] | undefined) => {
    updateVariable(localVariable.value.id, { artifact_categories });
};

const updateArtifactFragmentSelector = (artifact_fragment_selector: any) => {
    updateVariable(localVariable.value.id, { artifact_fragment_selector });
};

const updateMultiValueStrategy = (multi_value_strategy: string) => {
    updateVariable(localVariable.value.id, { multi_value_strategy });
};

const updateMultiValueSeparator = (multi_value_separator: string) => {
    updateVariable(localVariable.value.id, { multi_value_separator });
};

const updateSchemaAssociation = (team_object_schema_association_id: number | undefined) => {
    updateVariable(localVariable.value.id, { team_object_schema_association_id });
};

// Debounced handlers for text inputs
const debouncedUpdateDescription = useDebounceFn(updateDescription, 500);
const debouncedUpdateAiInstructions = useDebounceFn(updateAiInstructions, 500);
</script>
