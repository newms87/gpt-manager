<template>
    <InfoDialog
        v-if="isShowing"
        class="ui-mode"
        :title="`Template Variables - ${template?.name || 'Template'}`"
        content-class="w-[85vw] h-[85vh] overflow-hidden bg-white"
        @close="$emit('close')"
    >
        <div class="h-full flex flex-col">
            <!-- Header Section -->
            <div class="bg-gradient-to-br from-slate-50 to-slate-100 p-6 rounded-xl shadow-xl flex-shrink-0 border border-slate-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <VariablesIcon class="w-6 text-blue-400" />
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">Template Variables</h3>
                            <p class="text-sm text-slate-600">
                                {{ template?.template_url || "No URL set" }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <LabelPillWidget
                            :label="`${variableCount} variable${variableCount !== 1 ? 's' : ''}`"
                            color="blue"
                            size="sm"
                        />
                        <ActionButton
                            type="refresh"
                            label="Fetch from Template"
                            size="sm"
                            color="blue-invert"
                            :action="refreshVariablesAction"
                            :target="template"
                            :disabled="!template?.template_url"
                            tooltip="Fetch variables from Google Doc template"
                        />
                    </div>
                </div>
            </div>

            <!-- Variables List -->
            <div class="flex-grow overflow-hidden flex flex-col mt-6">
                <div class="flex-grow overflow-y-auto px-6 pb-6">
                    <!-- Loading State -->
                    <div
                        v-if="refreshVariablesAction.isApplying.value"
                        class="flex flex-col items-center justify-center py-16"
                    >
                        <QSpinner size="lg" color="blue" />
                        <div class="text-slate-600 mt-4">Fetching template variables...</div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else-if="variableCount === 0"
                        class="flex flex-col items-center justify-center py-16 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200"
                    >
                        <VariablesIcon class="w-16 text-slate-400 mb-4" />
                        <div class="text-lg text-slate-600 mb-2">No template variables found</div>
                        <div class="text-sm text-slate-500 text-center mb-4">
                            <template v-if="template?.template_url">
                                Click "Fetch from Template" above to scan your Google Doc for variables like <code
                                v-text="'{{variable_name}}'"
                            ></code>
                            </template>
                            <template v-else>
                                Add a Google Docs URL to your template, then click "Fetch from Template" to scan for
                                variables
                            </template>
                        </div>
                    </div>

                    <!-- Variables Grid -->
                    <div v-else class="space-y-3">
                        <div
                            v-for="variable in sortedVariables"
                            :key="variable.id || variable.name"
                            class="bg-white rounded-lg p-4 border border-slate-200 hover:border-slate-300 hover:shadow-md transition-all cursor-pointer"
                            @click="onEditVariable(variable)"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3 flex-grow">
                                    <div class="bg-blue-100 text-blue-700 px-3 py-1 rounded-md font-mono text-sm">
                                        {{ formatVariableName(variable.name) }}
                                    </div>
                                    <LabelPillWidget
                                        :label="getMappingTypeLabel(variable.mapping_type)"
                                        :color="getMappingTypeColor(variable.mapping_type)"
                                        size="sm"
                                    />
                                    <div v-if="variable.description" class="text-sm text-slate-500 truncate flex-grow">
                                        {{ variable.description }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <ActionButton
                                        type="edit"
                                        size="xs"
                                        color="sky"
                                        tooltip="Configure variable mapping"
                                        @click.stop="onEditVariable(variable)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </InfoDialog>

    <!-- Variable Editor Dialog -->
    <TemplateVariableEditor
        v-if="editingVariable"
        :variable="editingVariable"
        :template-id="template?.id"
        @close="editingVariable = null"
        @save="onSaveVariable"
    />
</template>

<script setup lang="ts">
import { FaSolidCodeBranch as VariablesIcon } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, InfoDialog, LabelPillWidget } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { dxTemplateDefinition } from "../config";
import type { TemplateDefinition, TemplateVariable } from "../types";
import TemplateVariableEditor from "./TemplateVariableEditor.vue";

const props = defineProps<{
    template?: TemplateDefinition | null;
    isShowing?: boolean;
}>();

const emit = defineEmits<{
    "close": [];
}>();

// Actions
const refreshVariablesAction = dxTemplateDefinition.getAction("fetch-template-variables");

// State
const editingVariable = ref<TemplateVariable | null>(null);

// Computed
const variables = computed(() => props.template?.template_variables || []);
const variableCount = computed(() => variables.value.length);

// Variables are sorted alphabetically by backend, but ensure it here too
const sortedVariables = computed(() => {
    return [...variables.value].sort((a, b) => a.name.localeCompare(b.name));
});

// Methods
const formatVariableName = (name: string) => {
    return `{{${name}}}`;
};

const getMappingTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        ai: "AI",
        artifact: "Artifact",
        team_object: "Team Object"
    };
    return labels[type] || type;
};

const getMappingTypeColor = (type: string) => {
    const colors: Record<string, string> = {
        ai: "sky",
        artifact: "blue",
        team_object: "green"
    };
    return colors[type] || "slate";
};

const onEditVariable = (variable: TemplateVariable) => {
    editingVariable.value = { ...variable };
};

const onSaveVariable = async () => {
    // The editor component handles the save, just close the dialog
    editingVariable.value = null;

    // Refresh the template to get updated variables
    if (props.template) {
        await refreshVariablesAction.trigger(props.template);
    }
};
</script>
