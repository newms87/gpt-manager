<template>
    <div class="template-management-list">
        <!-- Loading State -->
        <div v-if="isLoadingTemplates" class="flex justify-center py-12">
            <QSpinner size="lg" color="blue" />
        </div>

        <!-- Empty State -->
        <div v-else-if="!displayTemplates.length" class="text-center py-12">
            <div class="bg-white rounded-lg p-8 max-w-md mx-auto border border-slate-200 shadow-sm">
                <FaSolidClipboard class="w-16 h-16 text-slate-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-slate-800 mb-2">{{ emptyStateTitle }}</h3>
                <p class="text-slate-600 mb-4">{{ emptyStateDescription }}</p>
                <ActionButton
                    v-if="showCreateButton"
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
            <!-- Optional Create Button Header -->
            <div v-if="showCreateButton && displayTemplates.length > 0" class="flex justify-between items-center mb-4">
                <div class="text-sm text-gray-600">
                    Click on any template to edit its properties
                </div>
                <ActionButton
                    type="create"
                    label="Create New Template"
                    color="sky"
                    :action="createAction"
                    :input="newTemplateData"
                />
            </div>

            <!-- Template Cards -->
            <TemplateCard
                v-for="template in displayTemplates"
                :key="template.id"
                :template="template"
                @update="handleTemplateUpdate"
                @delete="handleTemplateDelete"
            />
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
    </div>
</template>

<script setup lang="ts">
import { FaSolidClipboard } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, ConfirmDialog } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";
import { dxDemandTemplate } from "../config";
import { useDemandTemplates } from "../composables/useDemandTemplates";
import type { DemandTemplate } from "../types";
import TemplateCard from "./TemplateCard.vue";

const props = withDefaults(defineProps<{
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    showCreateButton?: boolean;
    isLoading?: boolean;
    templates?: DemandTemplate[];
}>(), {
    emptyStateTitle: "No templates yet",
    emptyStateDescription: "Get started by creating your first document template",
    showCreateButton: true,
    isLoading: false,
    templates: undefined
});

// Composable for template management
const { activeTemplates, isLoading: composableLoading, loadActiveTemplates } = useDemandTemplates();

// State
const templateToDelete = ref<DemandTemplate | null>(null);

// Computed
const isLoadingTemplates = computed(() => props.isLoading || composableLoading.value);
const displayTemplates = computed(() => props.templates ?? activeTemplates.value);

const newTemplateData = computed(() => ({
    name: generateTemplateName(),
    description: "",
    template_url: "",
    is_active: true
}));

// Actions
const createAction = dxDemandTemplate.getAction("create", {
    onFinish: loadActiveTemplates
});
const updateAction = dxDemandTemplate.getAction("update");
const deleteAction = dxDemandTemplate.getAction("delete", {
    onFinish: loadActiveTemplates
});

// Methods
const generateTemplateName = () => {
    const count = displayTemplates.value.length + 1;
    return `Template ${count}`;
};

const handleTemplateUpdate = (template: DemandTemplate, data: Partial<DemandTemplate>) => {
    updateAction.trigger(template, data);
};

const handleTemplateDelete = (template: DemandTemplate) => {
    templateToDelete.value = template;
};

const confirmDelete = async () => {
    if (templateToDelete.value) {
        await deleteAction.trigger(templateToDelete.value);
        templateToDelete.value = null;
    }
};

// Load templates on mount (only if not passed via props)
onMounted(() => {
    if (!props.templates) {
        loadActiveTemplates();
    }
});
</script>
