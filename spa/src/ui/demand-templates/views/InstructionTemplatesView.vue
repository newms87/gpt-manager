<template>
    <UiMainLayout>
        <template #header>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800">
                            Instruction Templates
                        </h1>
                        <p class="text-slate-600 mt-1">
                            Create and manage instruction templates for writing the medical treatment summaries
                        </p>
                    </div>

                    <ActionButton
                        type="create"
                        label="New Template"
                        size="lg"
                        color="sky"
                        rounded
                        :action="createAction"
                        :input="newTemplateData"
                    />
                </div>
            </div>
        </template>

        <!-- Main Content -->
        <div class="space-y-6">
            <!-- Loading State -->
            <div v-if="isLoading" class="flex justify-center py-12">
                <QSpinner size="lg" color="blue" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!visibleTemplates.length" class="text-center py-12">
                <div class="bg-white rounded-lg p-8 max-w-md mx-auto border border-slate-200 shadow-sm">
                    <FaSolidFileLines class="w-16 h-16 text-slate-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-slate-800 mb-2">No templates yet</h3>
                    <p class="text-slate-600 mb-4">Get started by creating your first instruction template</p>
                    <ActionButton
                        class="mt-4"
                        type="create"
                        label="Create Template"
                        color="sky"
                        :action="createAction"
                        :input="newTemplateData"
                    />
                </div>
            </div>

            <!-- Templates List -->
            <div v-else class="space-y-4">
                <InstructionTemplateCard
                    v-for="template in visibleTemplates"
                    :key="template.id"
                    :template="template"
                    @update="handleTemplateUpdate"
                    @delete="handleTemplateDelete"
                />
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <ConfirmDialog
            v-if="templateToDelete"
            class="ui-mode"
            title="Delete Template?"
            :content="`Are you sure you want to delete &quot;${templateToDelete.name}&quot;? This action cannot be undone.`"
            color="negative"
            @confirm="confirmDelete"
            @close="templateToDelete = null"
        />
    </UiMainLayout>
</template>

<script setup lang="ts">
import { dxWorkflowInput } from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/config";
import type { WorkflowInput } from "@/types";
import { FaSolidFileLines } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, ConfirmDialog } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";
import { UiMainLayout } from "../../shared";
import InstructionTemplateCard from "../components/InstructionTemplateCard.vue";

// Local state management
const instructionTemplates = ref<WorkflowInput[]>([]);
const isLoading = ref(false);
const templateToDelete = ref<WorkflowInput | null>(null);

// Load instruction templates with proper filtering
const loadInstructionTemplates = async () => {
    try {
        isLoading.value = true;
        const response = await dxWorkflowInput.routes.list({
            filter: {
                "associations.associable_type": "App\\Models\\Demand\\UiDemand",
                "associations.category": "write_demand_instructions"
            }
        });

        instructionTemplates.value = response.data;
    } catch (error) {
        console.error("Error loading instruction templates:", error);
        instructionTemplates.value = [];
    } finally {
        isLoading.value = false;
    }
};

// Load templates on component mount
onMounted(loadInstructionTemplates);

// Actions with local state refresh
const createAction = dxWorkflowInput.getAction("create", { onFinish: loadInstructionTemplates });
const updateAction = dxWorkflowInput.getAction("update");
const deleteAction = dxWorkflowInput.getAction("delete", { onFinish: loadInstructionTemplates });

// Computed properties
const visibleTemplates = computed(() =>
    instructionTemplates.value.filter((template: WorkflowInput) => !template.deleted_at)
);

const generateTemplateName = () => {
    const count = instructionTemplates.value.length + 1;
    return `Instruction Template ${count}`;
};

const newTemplateData = computed(() => ({
    name: generateTemplateName(),
    description: "",
    content: "",
    associations: [{
        category: "write_demand_instructions",
        associable_type: "App\\Models\\Demand\\UiDemand",
        associable_id: null
    }]
}));

// Event handlers
const handleTemplateUpdate = (template: WorkflowInput, data: Partial<WorkflowInput>) => {
    updateAction.trigger(template, data);
};

const handleTemplateDelete = (template: WorkflowInput) => {
    templateToDelete.value = template;
};

const confirmDelete = () => {
    if (templateToDelete.value) {
        deleteAction.trigger(templateToDelete.value);
        templateToDelete.value = null;
    }
};
</script>
